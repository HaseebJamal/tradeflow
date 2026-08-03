<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'deliveries' => ['payment_method'],
            'khata_ledgers' => ['payment_method'],
            'orders' => ['payment_type', 'payment_method'],
            'payments' => ['method'],
            'platform_payments' => ['method'],
            'purchases' => ['payment_method'],
            'subscription_change_requests' => ['payment_method'],
            'subscriptions' => ['payment_method'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)->whereIn($column, ['JazzCash', 'JazzCash Manual', 'JazzCash manual'])->update([$column => 'Jazz Cash']);
                DB::table($table)->whereIn($column, ['Easypaisa Manual', 'Easypaisa manual'])->update([$column => 'Easypaisa']);
            }
        }
    }

    public function down(): void
    {
        // The previous labels were inconsistent, so this normalization is intentionally irreversible.
    }
};
