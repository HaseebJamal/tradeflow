<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COUNTER_DATE = '2000-01-01';

    /**
     * Normalize the legacy display references once.  Relationships keep using
     * their database IDs, so this changes no financial or stock data.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $aliases = [];

            $this->resequenceLinkedDocuments('orders', 'order_number', 'invoices', 'invoice_number', 'order_id', 'INV', 'invoice', $aliases);
            $this->resequenceLinkedDocuments('purchases', 'purchase_number', 'purchase_invoices', 'invoice_number', 'purchase_id', 'PINV', 'purchase_invoice', $aliases);
            $this->resequenceSimpleDocuments('sales_returns', 'return_number', 'SR', 'sales_return', $aliases);
            $this->resequenceSimpleDocuments('purchase_returns', 'return_number', 'PR', 'purchase_return', $aliases);
            $this->resequenceSimpleDocuments('held_pos_sales', 'hold_number', 'HOLD', 'pos_hold', $aliases);
            $this->resequenceSimpleDocuments('goods_receipts', 'grn_number', 'GRN', 'goods_receipt', $aliases);
            $this->preserveLegacyPaymentReferences();
            $this->resequenceSimpleDocuments('payments', 'reference_number', 'RCPT', 'payment_receipt', $aliases);

            // Audit, notification, and ledger records are historical text, not
            // document links. Update only unambiguous references for the same
            // business; all actual links remain protected by foreign keys/IDs.
            $this->replaceRecordedReferences($aliases);
        });
    }

    public function down(): void
    {
        // A resequence intentionally has no destructive rollback: it would
        // reintroduce ambiguous legacy references and could collide with new
        // numbers created after this migration.
    }

    private function resequenceLinkedDocuments(
        string $primaryTable,
        string $primaryColumn,
        string $mirrorTable,
        string $mirrorColumn,
        string $mirrorLinkColumn,
        string $prefix,
        string $scope,
        array &$aliases,
    ): void {
        if (! Schema::hasTable($primaryTable) || ! Schema::hasTable($mirrorTable)) {
            return;
        }

        $primaryRows = $this->documentRows($primaryTable, $primaryColumn);
        $mirrorRows = $this->documentRows($mirrorTable, $mirrorColumn, $mirrorLinkColumn);

        $this->stageRows($primaryTable, $primaryColumn, $primaryRows);
        $this->stageRows($mirrorTable, $mirrorColumn, $mirrorRows);

        $primaryIds = [];
        foreach ($primaryRows as $row) {
            $primaryIds[(int) $row['id']] = true;
        }

        $documentsByBusiness = [];
        foreach ($primaryRows as $row) {
            if ((int) $row['business_id'] > 0) {
                $documentsByBusiness[(int) $row['business_id']][] = ['kind' => 'primary', 'row' => $row];
            }
        }
        foreach ($mirrorRows as $row) {
            if ((int) $row['business_id'] > 0 && (! $row[$mirrorLinkColumn] || ! isset($primaryIds[(int) $row[$mirrorLinkColumn]]))) {
                $documentsByBusiness[(int) $row['business_id']][] = ['kind' => 'mirror', 'row' => $row];
            }
        }

        $primaryNumbers = [];
        $mirrorNumbers = [];
        foreach ($documentsByBusiness as $businessId => $documents) {
            usort($documents, fn (array $left, array $right): int => ($left['row']['created_at'] <=> $right['row']['created_at']) ?: ($left['row']['id'] <=> $right['row']['id']));

            $sequence = 0;
            foreach ($documents as $document) {
                $number = $this->number($prefix, ++$sequence);
                $row = $document['row'];
                if ($document['kind'] === 'primary') {
                    $primaryNumbers[(int) $row['id']] = $number;
                } else {
                    $mirrorNumbers[(int) $row['id']] = $number;
                }
            }
            $this->synchronizeCounter((int) $businessId, $scope, $sequence);
        }

        foreach ($primaryRows as $row) {
            $number = $primaryNumbers[(int) $row['id']] ?? null;
            if (! $number) {
                continue;
            }
            DB::table($primaryTable)->where('id', $row['id'])->update([$primaryColumn => $number]);
            $this->rememberAlias($aliases, (int) $row['business_id'], $row[$primaryColumn], $number, $this->temporaryReference($primaryTable, (int) $row['id']));
        }

        foreach ($mirrorRows as $row) {
            $number = $row[$mirrorLinkColumn]
                ? ($primaryNumbers[(int) $row[$mirrorLinkColumn]] ?? null)
                : null;
            $number ??= $mirrorNumbers[(int) $row['id']] ?? null;
            if (! $number) {
                continue;
            }
            DB::table($mirrorTable)->where('id', $row['id'])->update([$mirrorColumn => $number]);
            $this->rememberAlias($aliases, (int) $row['business_id'], $row[$mirrorColumn], $number, $this->temporaryReference($mirrorTable, (int) $row['id']));
        }
    }

    private function resequenceSimpleDocuments(string $table, string $column, string $prefix, string $scope, array &$aliases): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = $this->documentRows($table, $column);
        $this->stageRows($table, $column, $rows);

        $sequenceByBusiness = [];
        foreach ($rows as $row) {
            $businessId = (int) $row['business_id'];
            if ($businessId < 1) {
                continue;
            }
            $sequence = ($sequenceByBusiness[$businessId] ?? 0) + 1;
            $sequenceByBusiness[$businessId] = $sequence;
            $number = $this->number($prefix, $sequence);
            DB::table($table)->where('id', $row['id'])->update([$column => $number]);
            $this->rememberAlias($aliases, $businessId, $row[$column], $number, $this->temporaryReference($table, (int) $row['id']));
        }

        foreach ($sequenceByBusiness as $businessId => $lastNumber) {
            $this->synchronizeCounter($businessId, $scope, $lastNumber);
        }
    }

    private function documentRows(string $table, string $column, ?string $linkColumn = null): array
    {
        $columns = ['id', 'business_id', $column, 'created_at'];
        if ($linkColumn) {
            $columns[] = $linkColumn;
        }

        return DB::table($table)
            ->select($columns)
            ->orderBy('business_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function stageRows(string $table, string $column, array &$rows): void
    {
        foreach ($rows as &$row) {
            $temporary = $this->temporaryReference($table, (int) $row['id']);
            DB::table($table)->where('id', $row['id'])->update([$column => $temporary]);
            $row['_temporary_reference'] = $temporary;
        }
        unset($row);
    }

    private function preserveLegacyPaymentReferences(): void
    {
        if (! Schema::hasColumn('payments', 'transaction_reference')) {
            return;
        }

        foreach (DB::table('payments')->select('id', 'reference_number', 'transaction_reference')->get() as $payment) {
            $reference = trim((string) $payment->reference_number);
            if ($reference === '' || filled($payment->transaction_reference) || preg_match('/^(?:PAY|RCPT)-\d{6}$/', $reference)) {
                continue;
            }
            DB::table('payments')->where('id', $payment->id)->update(['transaction_reference' => $reference]);
        }
    }

    private function synchronizeCounter(int $businessId, string $scope, int $lastNumber): void
    {
        DB::table('document_number_counters')->updateOrInsert(
            ['business_id' => $businessId, 'scope' => $scope, 'number_date' => self::COUNTER_DATE],
            ['last_number' => $lastNumber, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private function rememberAlias(array &$aliases, int $businessId, mixed $old, string $new, string $temporary): void
    {
        $old = trim((string) $old);
        if ($businessId < 1 || $old === '' || $old === $new) {
            return;
        }

        $existing = $aliases[$businessId][$old] ?? null;
        if ($existing !== null && $existing['new'] !== $new) {
            // Text records cannot safely disambiguate the same legacy value.
            // The FK-backed document relationships remain correct regardless.
            $aliases[$businessId][$old] = false;
            return;
        }

        $aliases[$businessId][$old] = ['temporary' => $temporary, 'new' => $new];
    }

    private function replaceRecordedReferences(array $aliases): void
    {
        foreach ($aliases as $businessId => $references) {
            foreach ($references as $old => $replacement) {
                if ($replacement === false) {
                    continue;
                }
                $this->replaceBusinessText((int) $businessId, $old, $replacement['temporary']);
            }
        }

        foreach ($aliases as $businessId => $references) {
            foreach ($references as $replacement) {
                if ($replacement === false) {
                    continue;
                }
                $this->replaceBusinessText((int) $businessId, $replacement['temporary'], $replacement['new']);
            }
        }
    }

    private function replaceBusinessText(int $businessId, string $from, string $to): void
    {
        $targets = [
            'activity_logs' => ['description', 'old_values', 'new_values'],
            'audit_logs' => ['description', 'old_values', 'new_values'],
            'journal_entries' => ['description'],
            'khata_ledgers' => ['description'],
        ];

        foreach ($targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                DB::update("UPDATE `{$table}` SET `{$column}` = REPLACE(`{$column}`, ?, ?) WHERE `business_id` = ? AND `{$column}` LIKE ?", [$from, $to, $businessId, '%'.$from.'%']);
            }
        }

        if (Schema::hasTable('journal_entry_lines')) {
            // Avoid aliases here for compatibility with the MariaDB version
            // bundled by XAMPP.
            DB::update('UPDATE `journal_entry_lines` INNER JOIN `journal_entries` ON `journal_entries`.`id` = `journal_entry_lines`.`journal_entry_id` SET `journal_entry_lines`.`description` = REPLACE(`journal_entry_lines`.`description`, ?, ?) WHERE `journal_entries`.`business_id` = ? AND `journal_entry_lines`.`description` LIKE ?', [$from, $to, $businessId, '%'.$from.'%']);
        }

        if (! Schema::hasTable('notifications')) {
            return;
        }

        foreach (DB::table('notifications')->where('data', 'like', '%'.$from.'%')->get(['id', 'data']) as $notification) {
            $data = json_decode($notification->data, true);
            if (! is_array($data) || (int) ($data['business_id'] ?? 0) !== $businessId) {
                continue;
            }
            DB::table('notifications')->where('id', $notification->id)->update(['data' => str_replace($from, $to, $notification->data)]);
        }
    }

    private function temporaryReference(string $table, int $id): string
    {
        return '__TF_RESEQUENCE_'.strtoupper($table).'_'.$id;
    }

    private function number(string $prefix, int $sequence): string
    {
        return $prefix.'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
};
