<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'low_stock_alert_qty')) {
                $table->unsignedInteger('low_stock_alert_qty')->default(10)->after('stock_quantity');
            }
        });

        if (Schema::hasTable('inventories') && Schema::hasColumn('inventories', 'low_stock_alert')) {
            DB::statement('UPDATE products p LEFT JOIN inventories i ON i.product_id = p.id SET p.low_stock_alert_qty = COALESCE(i.low_stock_alert, p.low_stock_alert_qty)');
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'low_stock_alert_qty')) {
                $table->dropColumn('low_stock_alert_qty');
            }
        });
    }
};
