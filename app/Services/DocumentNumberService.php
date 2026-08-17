<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    private const TYPES = [
        'sales' => ['scope' => 'invoice', 'prefix' => 'INV', 'sources' => [['orders', 'order_number'], ['invoices', 'invoice_number']]],
        'pos_hold' => ['scope' => 'pos_hold', 'prefix' => 'HOLD', 'sources' => [['held_pos_sales', 'hold_number']]],
        'purchase' => ['scope' => 'purchase_invoice', 'prefix' => 'PINV', 'sources' => [['purchases', 'purchase_number'], ['purchase_invoices', 'invoice_number']]],
        'supplier_invoice' => ['scope' => 'purchase_invoice', 'prefix' => 'PINV', 'sources' => [['purchases', 'purchase_number'], ['purchase_invoices', 'invoice_number']]],
        'sales_return' => ['scope' => 'sales_return', 'prefix' => 'SR', 'sources' => [['sales_returns', 'return_number']]],
        'purchase_return' => ['scope' => 'purchase_return', 'prefix' => 'PR', 'sources' => [['purchase_returns', 'return_number']]],
        'goods_receipt' => ['scope' => 'goods_receipt', 'prefix' => 'GRN', 'sources' => [['goods_receipts', 'grn_number']]],
        'delivery' => ['scope' => 'delivery', 'prefix' => 'DEL'],
        'payment' => ['scope' => 'payment_receipt', 'prefix' => 'RCPT', 'sources' => [['payments', 'reference_number']]],
        'credit_note' => ['scope' => 'credit_note', 'prefix' => 'CN', 'sources' => [['credit_notes', 'credit_note_number']]],
        'debit_note' => ['scope' => 'debit_note', 'prefix' => 'DN'],
        'stock_count' => ['scope' => 'stock_count', 'prefix' => 'STK', 'sources' => [['stock_counts', 'reference']]],
        'balance_adjustment' => ['scope' => 'balance_adjustment', 'prefix' => 'ADJ', 'sources' => [['balance_adjustments', 'reference']]],
    ];

    /**
     * A locked business/type counter makes reference allocation safe when two
     * users create the same kind of document at the same time. The date column
     * remains a fixed legacy bucket solely for backwards schema compatibility.
     */
    public function next(int $businessId, string $type): string
    {
        if ($businessId < 1 || ! isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException('Unsupported document number type.');
        }

        return DB::transaction(function () use ($businessId, $type) {
            $definition = self::TYPES[$type];
            $scope = $definition['scope'];
            $sequenceDate = '2000-01-01';
            DB::table('document_number_counters')->insertOrIgnore([
                'business_id' => $businessId,
                'scope' => $scope,
                'number_date' => $sequenceDate,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = DB::table('document_number_counters')
                ->where('business_id', $businessId)
                ->where('scope', $scope)
                ->where('number_date', $sequenceDate)
                ->lockForUpdate()
                ->first();

            // Earlier builds stored a date/time value in the counter itself.
            // It is not part of the new short format, so continue only from a
            // valid six-digit sequence or from an existing short document.
            $storedSequence = (int) $counter->last_number;
            $storedSequence = $storedSequence <= 999999 ? $storedSequence : 0;
            $next = max($storedSequence, $this->highestExistingSequence($businessId, $definition)) + 1;
            DB::table('document_number_counters')
                ->where('id', $counter->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $definition['prefix'].'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * A previous deployment may already have short numbers without a counter
     * row. Start above those records while leaving every historical value as-is.
     */
    private function highestExistingSequence(int $businessId, array $definition): int
    {
        $prefix = preg_quote($definition['prefix'], '/');
        $highest = 0;

        foreach ($definition['sources'] ?? [] as [$table, $column]) {
            foreach (DB::table($table)
                ->where('business_id', $businessId)
                ->where($column, 'like', $definition['prefix'].'-%')
                ->pluck($column) as $number) {
                if (preg_match('/^'.$prefix.'-(\d{6})$/', (string) $number, $matches)) {
                    $highest = max($highest, (int) $matches[1]);
                }
            }
        }

        return $highest;
    }
}
