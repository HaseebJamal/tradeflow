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
        'sales_return' => ['scope' => 'sales_return', 'prefix' => 'SRN', 'sources' => [['sales_returns', 'return_number']]],
        'purchase_return' => ['scope' => 'purchase_return', 'prefix' => 'PRN', 'sources' => [['purchase_returns', 'return_number']]],
        'delivery' => ['scope' => 'delivery', 'prefix' => 'DEL'],
        'payment' => ['scope' => 'payment', 'prefix' => 'PAY'],
        'credit_note' => ['scope' => 'credit_note', 'prefix' => 'CN', 'sources' => [['credit_notes', 'credit_note_number']]],
        'debit_note' => ['scope' => 'debit_note', 'prefix' => 'DN'],
    ];

    /**
     * A locked, type-scoped counter provides a short, globally unique number
     * even if two documents are created at the same time. The legacy date
     * column is retained as a fixed sequence bucket for schema compatibility.
     */
    public function next(string $type): string
    {
        if (!isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException('Unsupported document number type.');
        }

        return DB::transaction(function () use ($type) {
            $definition = self::TYPES[$type];
            $scope = $definition['scope'];
            $sequenceDate = '2000-01-01';
            DB::table('document_number_counters')->insertOrIgnore([
                'scope' => $scope,
                'number_date' => $sequenceDate,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $counter = DB::table('document_number_counters')
                ->where('scope', $scope)
                ->where('number_date', $sequenceDate)
                ->lockForUpdate()
                ->first();

            // Earlier builds stored a date/time value in the counter itself.
            // It is not part of the new short format, so continue only from a
            // valid six-digit sequence or from an existing short document.
            $storedSequence = (int) $counter->last_number;
            $storedSequence = $storedSequence <= 999999 ? $storedSequence : 0;
            $next = max($storedSequence, $this->highestExistingSequence($definition)) + 1;
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
    private function highestExistingSequence(array $definition): int
    {
        $prefix = preg_quote($definition['prefix'], '/');
        $highest = 0;

        foreach ($definition['sources'] ?? [] as [$table, $column]) {
            foreach (DB::table($table)->where($column, 'like', $definition['prefix'].'-%')->pluck($column) as $number) {
                if (preg_match('/^'.$prefix.'-(\d{6})$/', (string) $number, $matches)) {
                    $highest = max($highest, (int) $matches[1]);
                }
            }
        }

        return $highest;
    }
}
