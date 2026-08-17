<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'target_stock_level')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('target_stock_level', 15, 3)->default(0)->after('low_stock_alert_qty');
            });

            // Existing low-stock configuration remains the one source of
            // truth for the reorder trigger. Seed the new target conservatively
            // from it so existing products gain a safe, small replenishment
            // recommendation instead of an arbitrary default quantity.
            DB::table('products')->update([
                'target_stock_level' => DB::raw('COALESCE(low_stock_alert_qty, 0)'),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'target_stock_level')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('target_stock_level');
            });
        }
    }
};
