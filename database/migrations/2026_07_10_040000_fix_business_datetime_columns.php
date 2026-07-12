<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'order_date')) {
            DB::statement('ALTER TABLE orders MODIFY order_date DATETIME NULL');
        }

        if (Schema::hasTable('deliveries')) {
            foreach (['assigned_at', 'started_at', 'delivered_at', 'failed_at', 'cancelled_at'] as $column) {
                if (Schema::hasColumn('deliveries', $column)) {
                    DB::statement("ALTER TABLE deliveries MODIFY {$column} DATETIME NULL");
                }
            }
        }

        if (Schema::hasTable('journal_entries') && Schema::hasColumn('journal_entries', 'posted_at')) {
            DB::statement('ALTER TABLE journal_entries MODIFY posted_at DATETIME NULL');
        }
    }

    public function down(): void
    {
        // Keep datetime columns on rollback to avoid truncating historical time values.
    }
};
