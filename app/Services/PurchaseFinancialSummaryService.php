<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseRefundSettlement;
use App\Models\SupplierPayment;

/**
 * Keeps the purchase's denormalized payment summary aligned with its
 * auditable receipt, return, and supplier-payment records.
 */
class PurchaseFinancialSummaryService
{
    /**
     * The original purchase total is never changed. Rejected/damaged receipt
     * quantities and returned accepted stock reduce only the supplier
     * liability; payments continue to represent money actually paid.
     *
     * Call this while the purchase is locked inside the surrounding
     * transaction whenever receipt, return, or payment records change.
     */
    public function sync(Purchase $purchase): Purchase
    {
        $purchase = Purchase::query()
            ->where('business_id', $purchase->business_id)
            ->lockForUpdate()
            ->findOrFail($purchase->id);

        $summary = $this->summary($purchase);
        $updates = [
            'paid_amount' => $summary['paid_amount'],
            'balance' => $summary['balance'],
            'payment_status' => $summary['payment_status'],
        ];

        if (auth()->check()) {
            $updates['updated_by'] = auth()->id();
        }

        $purchase->update($updates);
        PurchaseInvoice::query()
            ->where('business_id', $purchase->business_id)
            ->where('purchase_id', $purchase->id)
            ->update([
                'paid_amount' => $summary['paid_amount'],
                'balance' => $summary['balance'],
                'status' => $summary['payment_status'],
                'updated_at' => now(),
            ]);

        return $purchase->fresh();
    }

    /** @return array{gross_total: float, receipt_adjustments: float, return_adjustments: float, net_liability: float, paid_amount: float, balance: float, payment_status: string} */
    public function summary(Purchase $purchase): array
    {
        $gross = $this->money($purchase->grand_total);

        // A non-accepted quantity was never received into inventory and must
        // not remain payable. Use the saved line total so discounts/tax are
        // retained rather than recomputing from a display-only unit cost.
        $receiptAdjustments = $this->money(PurchaseItem::query()
            ->leftJoin('goods_receipt_items as receipt_item', 'receipt_item.purchase_item_id', '=', 'purchase_items.id')
            ->where('purchase_items.purchase_id', $purchase->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN purchase_items.quantity > 0 THEN (purchase_items.line_total / purchase_items.quantity) * (CASE WHEN receipt_item.paid_damaged_quantity IS NULL AND receipt_item.paid_rejected_quantity IS NULL THEN (COALESCE(receipt_item.damaged_quantity, 0) + COALESCE(receipt_item.rejected_quantity, 0)) ELSE (COALESCE(receipt_item.paid_damaged_quantity, 0) + COALESCE(receipt_item.paid_rejected_quantity, 0)) END) ELSE 0 END), 0) AS amount')
            ->value('amount'));

        // A purchase return is a later reversal of stock that was accepted.
        // Return items already snapshot their authoritative line value.
        $returnAdjustments = $this->money(PurchaseReturnItem::query()
            ->whereHas('purchaseReturn', fn ($query) => $query
                ->where('business_id', $purchase->business_id)
                ->where('purchase_id', $purchase->id))
            ->sum('line_total'));

        $netLiability = max(0, $this->money($gross - $receiptAdjustments - $returnAdjustments));
        $paid = $this->money(SupplierPayment::query()
            ->where('business_id', $purchase->business_id)
            ->where('purchase_id', $purchase->id)
            ->sum('amount'));
        $refundSettled = $this->money(PurchaseRefundSettlement::query()
            ->where('business_id', $purchase->business_id)
            ->where('purchase_id', $purchase->id)
            ->sum('amount'));

        if (in_array($purchase->status, ['Draft', 'Cancelled'], true)) {
            $netLiability = 0.0;
        }

        $balance = max(0, $this->money($netLiability - $paid));
        $status = $this->paymentStatus($purchase, $netLiability, $paid, $refundSettled, $balance);

        return [
            'gross_total' => $gross,
            'receipt_adjustments' => $receiptAdjustments,
            'return_adjustments' => $returnAdjustments,
            'net_liability' => $netLiability,
            'paid_amount' => $paid,
            'refund_settled' => $refundSettled,
            'balance' => $balance,
            'payment_status' => $status,
        ];
    }

    private function paymentStatus(Purchase $purchase, float $netLiability, float $paid, float $refundSettled, float $balance): string
    {
        if ($purchase->status === 'Cancelled') {
            return $paid > 0 ? 'Refund Due' : 'Unpaid';
        }

        if ($purchase->status === 'Draft') {
            return 'Unpaid';
        }

        if ($paid > $netLiability + $refundSettled + 0.009) {
            return 'Refund Due';
        }

        if ($balance <= 0.009) {
            return 'Paid';
        }

        return $paid > 0 ? 'Partial' : 'Unpaid';
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
