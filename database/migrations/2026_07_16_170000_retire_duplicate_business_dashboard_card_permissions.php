<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Preserve historic company/template assignments while removing retired
        // cards from active permission screens and runtime definition lookups.
        if (!Schema::hasTable('permission_definitions')) {
            return;
        }

        DB::table('permission_definitions')
            ->whereIn('permission_key', [
                'dashboard.card_pending_customer_payments',
                'dashboard.card_pending_supplier_payments',
            ])
            ->update(['status' => 'inactive', 'updated_at' => now()]);

        DB::table('permission_definitions')
            ->where('permission_key', 'dashboard.card_profit_loss')
            ->update(['label' => 'Show Total Profit / Loss card', 'updated_at' => now()]);

        DB::table('permission_definitions')
            ->where('permission_key', 'dashboard.card_monthly_profit')
            ->update(['label' => 'Show Monthly Profit / Loss card', 'updated_at' => now()]);

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        if (!Schema::hasTable('permission_definitions')) {
            return;
        }

        DB::table('permission_definitions')
            ->whereIn('permission_key', [
                'dashboard.card_pending_customer_payments',
                'dashboard.card_pending_supplier_payments',
            ])
            ->update(['status' => 'active', 'updated_at' => now()]);

        DB::table('permission_definitions')
            ->where('permission_key', 'dashboard.card_profit_loss')
            ->update(['label' => 'Show Profit / Loss card', 'updated_at' => now()]);

        DB::table('permission_definitions')
            ->where('permission_key', 'dashboard.card_monthly_profit')
            ->update(['label' => 'Show Monthly Profit card', 'updated_at' => now()]);

        Cache::forget('tradeflow.permission-definition-keys');
    }
};
