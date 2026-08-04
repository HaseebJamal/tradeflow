<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Correct legacy cached balances without changing the original purchase
     * totals or historical receipt, return, and payment records.
     */
    public function up(): void
    {
        DB::table('purchases')->orderBy('id')->chunkById(100, function ($purchases): void {
            foreach ($purchases as $purchase) {
                $receiptAdjustments = (float) DB::table('purchase_items')
                    ->where('purchase_id', $purchase->id)
                    ->selectRaw('COALESCE(SUM(CASE WHEN quantity > 0 THEN (line_total / quantity) * (COALESCE(damaged_quantity, 0) + COALESCE(rejected_quantity, 0)) ELSE 0 END), 0) AS amount')
                    ->value('amount');
                $returnAdjustments = (float) DB::table('purchase_return_items as item')
                    ->join('purchase_returns as purchase_return', 'purchase_return.id', '=', 'item.purchase_return_id')
                    ->where('purchase_return.business_id', $purchase->business_id)
                    ->where('purchase_return.purchase_id', $purchase->id)
                    ->sum('item.line_total');
                $paid = round((float) DB::table('supplier_payments')
                    ->where('business_id', $purchase->business_id)
                    ->where('purchase_id', $purchase->id)
                    ->sum('amount'), 2);
                $netLiability = in_array($purchase->status, ['Draft', 'Cancelled'], true)
                    ? 0.0
                    : max(0, round((float) $purchase->grand_total - $receiptAdjustments - $returnAdjustments, 2));
                $balance = max(0, round($netLiability - $paid, 2));
                $status = $purchase->status === 'Cancelled'
                    ? ($paid > 0 ? 'Refund Due' : 'Unpaid')
                    : ($purchase->status === 'Draft'
                        ? 'Unpaid'
                        : ($paid > $netLiability + 0.009
                            ? 'Refund Due'
                            : ($balance <= 0.009 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid'))));

                DB::table('purchases')->where('id', $purchase->id)->update([
                    'paid_amount' => $paid,
                    'balance' => $balance,
                    'payment_status' => $status,
                    'updated_at' => now(),
                ]);
                DB::table('purchase_invoices')
                    ->where('business_id', $purchase->business_id)
                    ->where('purchase_id', $purchase->id)
                    ->update([
                        'paid_amount' => $paid,
                        'balance' => $balance,
                        'status' => $status,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    public function down(): void
    {
        // Forward-only: the old cached balance calculation was incorrect.
    }
};
