<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Business;
use App\Models\GoodsReceipt;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturnItem;
use App\Models\SupplierAdvanceApplication;
use App\Models\SupplierPayment;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReceivingService
{
    public function __construct(
        private AccountingService $accounting,
        private ProductPurchaseCostService $productCosts,
        private DocumentNumberService $numbers,
    ) {}

    /**
     * Records exactly one idempotent GRN. Inventory is increased only for the
     * accepted quantities, while damaged/rejected units remain off-stock.
     */
    public function record(Purchase $purchase, array $data, User $user): GoodsReceipt
    {
        return DB::transaction(function () use ($purchase, $data, $user): GoodsReceipt {
            Business::query()->lockForUpdate()->findOrFail($purchase->business_id);
            $locked = Purchase::where('business_id', $purchase->business_id)->lockForUpdate()->findOrFail($purchase->id);
            if (in_array($locked->status, ['Draft', 'Cancelled'], true) || in_array($locked->receiving_status, ['Returned', 'Fully Received'], true)) {
                throw ValidationException::withMessages(['receipt' => 'This purchase cannot receive more goods.']);
            }

            if ($existing = GoodsReceipt::where('business_id', $locked->business_id)->where('submission_token', $data['submission_token'])->first()) {
                return $existing;
            }

            $items = $locked->items()->lockForUpdate()->get()->keyBy('id');
            $processedItemIds = [];
            $lines = collect($data['items'])->map(function (array $line, int $index) use ($items, &$processedItemIds): array {
                $item = $items->get((int) ($line['purchase_item_id'] ?? 0));
                if (! $item) {
                    throw ValidationException::withMessages(["items.$index.purchase_item_id" => 'This receipt item does not belong to the selected purchase.']);
                }
                if (isset($processedItemIds[$item->id])) {
                    throw ValidationException::withMessages(["items.$index.purchase_item_id" => 'Each purchase item can only be submitted once per goods receipt.']);
                }
                $processedItemIds[$item->id] = true;

                return [
                    'index' => $index,
                    'item' => $item,
                    'accepted' => $this->quantity($line['accepted_quantity'] ?? 0),
                    'damaged' => $this->quantity($line['damaged_quantity'] ?? 0),
                    'rejected' => $this->quantity($line['rejected_quantity'] ?? 0),
                ];
            })->filter(fn (array $line): bool => $line['accepted'] > 0 || $line['damaged'] > 0 || $line['rejected'] > 0);
            if ($lines->isEmpty()) throw ValidationException::withMessages(['items' => 'Enter an accepted, damaged, or rejected quantity for at least one item.']);

            $receipt = GoodsReceipt::create([
                'business_id' => $locked->business_id,
                'purchase_id' => $locked->id,
                'supplier_id' => $locked->supplier_id,
                'grn_number' => $this->nextGrnNumber($locked->business_id),
                'submission_token' => $data['submission_token'],
                'attachment_path' => $data['attachment_path'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'created_by' => $user->id,
            ]);

            $acceptedValue = 0.0;
            $rejectedValue = 0.0;
            $products = collect();
            foreach ($lines as $line) {
                $index = $line['index'];
                $item = $line['item'];
                $accepted = $line['accepted'];
                $damaged = $line['damaged'];
                $rejected = $line['rejected'];
                $processed = round($accepted + $damaged + $rejected, 3);
                $remaining = max(0, round((float) $item->quantity - (float) $item->received_quantity - (float) $item->damaged_quantity - (float) $item->rejected_quantity, 3));
                if ($processed <= 0 || $processed > $remaining + 0.0001) {
                    throw ValidationException::withMessages(["items.$index" => 'Total processed quantity cannot exceed the remaining quantity ('.$remaining.') for '.$item->product_name_snapshot.'.']);
                }

                $lineRate = round((float) $item->line_total / max(0.001, (float) $item->quantity), 2);
                $acceptedLine = round($accepted * $lineRate, 2);
                $rejectedLine = round(($damaged + $rejected) * $lineRate, 2);
                $receipt->items()->create([
                    'purchase_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'accepted_quantity' => $accepted,
                    'damaged_quantity' => $damaged,
                    'rejected_quantity' => $rejected,
                    'unit_cost' => $item->unit_cost,
                    'line_total' => $acceptedLine,
                ]);
                $item->update([
                    'received_quantity' => round((float) $item->received_quantity + $accepted, 3),
                    'damaged_quantity' => round((float) $item->damaged_quantity + $damaged, 3),
                    'rejected_quantity' => round((float) $item->rejected_quantity + $rejected, 3),
                ]);

                if ($accepted > 0) {
                    $product = Product::where('business_id', $locked->business_id)->lockForUpdate()->findOrFail($item->product_id);
                    $before = (float) $product->stock_quantity;
                    $after = round($before + $accepted, 3);
                    $product->update(['stock_quantity' => $after, 'current_stock' => $after]);
                    Inventory::updateOrCreate(['business_id' => $locked->business_id, 'product_id' => $product->id], ['available_stock' => $after, 'low_stock_alert' => $product->low_stock_alert_qty ?? 10]);
                    StockMovement::create(['business_id' => $locked->business_id, 'product_id' => $product->id, 'goods_receipt_id' => $receipt->id, 'type' => 'purchased', 'quantity' => $accepted, 'reason' => 'Goods receipt '.$receipt->grn_number, 'user_id' => $user->id, 'created_by' => $user->id]);
                    InventoryMovement::create(['business_id' => $locked->business_id, 'product_id' => $product->id, 'goods_receipt_id' => $receipt->id, 'type' => 'PURCHASED', 'quantity' => $accepted, 'previous_stock' => $before, 'new_stock' => $after, 'note' => 'Accepted on '.$receipt->grn_number, 'created_by' => $user->id, 'movement_date' => $receipt->received_at]);
                    $products->push($product);
                }
                $acceptedValue += $acceptedLine;
                $rejectedValue += $rejectedLine;
            }

            $this->refreshReceivingStatus($locked);
            PurchaseInvoice::updateOrCreate(
                ['purchase_id' => $locked->id],
                [
                    'business_id' => $locked->business_id,
                    'supplier_id' => $locked->supplier_id,
                    'invoice_number' => $locked->invoice?->invoice_number ?? $this->numbers->next('supplier_invoice'),
                    'invoice_date' => $locked->supplier_invoice_date ?? $receipt->received_at->toDateString(),
                    'grand_total' => $locked->grand_total,
                    'paid_amount' => $locked->paid_amount,
                    'balance' => $locked->balance,
                    'status' => $locked->payment_status,
                ]
            );
            if ($acceptedValue > 0) $this->post($locked, $receipt, 'goods_receipt_inventory', $acceptedValue, [['Inventory', $acceptedValue, 0], ['Purchases', 0, $acceptedValue]]);
            if ($rejectedValue > 0) {
                $this->post($locked, $receipt, 'goods_receipt_credit', $rejectedValue, [['Accounts Payable', $rejectedValue, 0], ['Purchases', 0, $rejectedValue]]);
                $this->applySupplierCredit($locked, $rejectedValue);
            }
            $this->applyAvailableAdvances($locked, $receipt);
            $products->unique('id')->each(fn (Product $product) => $this->productCosts->refresh($product));

            return $receipt->fresh(['items.product']);
        });
    }

    public function refreshReceivingStatus(Purchase $purchase): void
    {
        $purchase->loadMissing('items.returnItems');
        $ordered = (float) $purchase->items->sum('quantity');
        $accepted = (float) $purchase->items->sum('received_quantity');
        $processed = (float) $purchase->items->sum(fn (PurchaseItem $item) => (float) $item->received_quantity + (float) $item->damaged_quantity + (float) $item->rejected_quantity);
        $returned = (float) $purchase->items->sum(fn (PurchaseItem $item) => (float) $item->returnItems->sum('quantity'));
        $status = 'Not Received';
        if ($accepted > 0 && $returned >= $accepted - 0.0001) $status = 'Returned';
        elseif ($returned > 0) $status = 'Partially Returned';
        elseif ($processed >= $ordered - 0.0001 && $ordered > 0) $status = 'Fully Received';
        elseif ($processed > 0) $status = 'Partially Received';
        $purchase->update(['receiving_status' => $status, 'received_at' => $processed > 0 ? ($purchase->received_at ?? now()) : null, 'updated_by' => auth()->id()]);
    }

    private function applySupplierCredit(Purchase $purchase, float $amount): void
    {
        $paid = (float) $purchase->paid_amount;
        $balance = max(0, round((float) $purchase->balance - $amount, 2));
        $purchase->update(['balance' => $balance, 'payment_status' => $paid > $balance + $amount ? 'Refund Due' : ($balance <= 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid'))]);
    }

    private function post(Purchase $purchase, GoodsReceipt $receipt, string $source, float $amount, array $lines): void
    {
        if (JournalEntry::where('business_id', $purchase->business_id)->where('reference_type', $source)->where('reference_id', $receipt->id)->exists()) return;
        $this->accounting->ensureDefaultAccounts($purchase->business_id);
        $accounts = Account::where('business_id', $purchase->business_id)->whereIn('name', collect($lines)->pluck(0))->pluck('id', 'name');
        $this->accounting->post($purchase->business_id, [
            'purchase_id' => $purchase->id,
            'goods_receipt_id' => $receipt->id,
            // A receipt can create inventory, supplier-credit, and advance
            // application journals in the same second. The source is part of
            // the voucher so those legitimate postings cannot collide.
            'voucher_number' => 'GRN-'.$receipt->id.'-'.str_replace('_', '-', $source),
            'entry_date' => $receipt->received_at->toDateString(),
            'reference_type' => $source,
            'reference_id' => $receipt->id,
            'description' => 'Goods receipt '.$receipt->grn_number.' for '.$purchase->purchase_number,
        ], collect($lines)->map(fn (array $line) => ['account_id' => $accounts[$line[0]], 'supplier_id' => in_array($line[0], ['Accounts Payable', 'Supplier Advances'], true) ? $purchase->supplier_id : null, 'debit' => $line[1], 'credit' => $line[2]])->all());
    }

    private function applyAvailableAdvances(Purchase $purchase, GoodsReceipt $receipt): void
    {
        $payments = SupplierPayment::where('business_id', $purchase->business_id)
            ->where('purchase_id', $purchase->id)
            ->where('is_advance', true)
            ->where('remaining_amount', '>', 0)
            ->lockForUpdate()
            ->get();
        foreach ($payments as $payment) {
            $amount = round((float) $payment->remaining_amount, 2);
            if ($amount <= 0 || SupplierAdvanceApplication::where('purchase_id', $purchase->id)->where('supplier_payment_id', $payment->id)->exists()) continue;
            $application = SupplierAdvanceApplication::create([
                'business_id' => $purchase->business_id,
                'supplier_id' => $purchase->supplier_id,
                'purchase_id' => $purchase->id,
                'supplier_payment_id' => $payment->id,
                'goods_receipt_id' => $receipt->id,
                'amount' => $amount,
                'created_by' => auth()->id(),
            ]);
            $payment->update(['applied_amount' => round((float) $payment->applied_amount + $amount, 2), 'remaining_amount' => 0]);
            $this->post($purchase, $receipt, 'supplier_advance_application_'.$application->id, $amount, [['Accounts Payable', $amount, 0], ['Supplier Advances', 0, $amount]]);
        }
    }

    private function nextGrnNumber(int $businessId): string
    {
        $last = GoodsReceipt::where('business_id', $businessId)->orderByDesc('id')->value('grn_number');
        $number = preg_match('/(\d+)$/', (string) $last, $matches) ? (int) $matches[1] + 1 : 1;
        return 'GRN-B'.$businessId.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    private function quantity(mixed $value): float
    {
        if (! preg_match('/^\d+$/', trim((string) $value))) {
            throw ValidationException::withMessages(['items' => 'Receipt quantities must be whole numbers.']);
        }

        return (float) $value;
    }
}
