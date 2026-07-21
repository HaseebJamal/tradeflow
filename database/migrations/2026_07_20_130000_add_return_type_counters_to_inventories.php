<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventories')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventories', 'sales_returned_stock')) {
                $table->integer('sales_returned_stock')->default(0)->after('returned_stock');
            }

            if (! Schema::hasColumn('inventories', 'purchase_returned_stock')) {
                $table->integer('purchase_returned_stock')->default(0)->after('sales_returned_stock');
            }
        });

        $this->backfillPurchaseReturnCounters();
        $this->backfillSalesReturnCounters();
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventories')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table): void {
            if (Schema::hasColumn('inventories', 'purchase_returned_stock')) {
                $table->dropColumn('purchase_returned_stock');
            }

            if (Schema::hasColumn('inventories', 'sales_returned_stock')) {
                $table->dropColumn('sales_returned_stock');
            }
        });
    }

    private function backfillPurchaseReturnCounters(): void
    {
        if (! Schema::hasTable('purchase_returns') || ! Schema::hasTable('purchase_return_items')) {
            return;
        }

        foreach (DB::table('purchase_return_items as item')
            ->join('purchase_returns as purchase_return', 'purchase_return.id', '=', 'item.purchase_return_id')
            ->select('purchase_return.business_id', 'item.product_id', DB::raw('SUM(item.quantity) as returned_quantity'))
            ->groupBy('purchase_return.business_id', 'item.product_id')
            ->orderBy('purchase_return.business_id')
            ->cursor() as $row) {
            DB::table('inventories')
                ->where('business_id', $row->business_id)
                ->where('product_id', $row->product_id)
                ->update(['purchase_returned_stock' => (int) $row->returned_quantity, 'updated_at' => now()]);
        }
    }

    private function backfillSalesReturnCounters(): void
    {
        if (! Schema::hasTable('pos_returns') || ! Schema::hasTable('pos_return_items') || ! Schema::hasTable('order_items')) {
            return;
        }

        foreach (DB::table('pos_return_items as item')
            ->join('pos_returns as sales_return', 'sales_return.id', '=', 'item.pos_return_id')
            ->join('order_items as order_item', 'order_item.id', '=', 'item.order_item_id')
            ->select('sales_return.business_id', 'order_item.product_id', DB::raw('SUM(item.quantity) as returned_quantity'))
            ->groupBy('sales_return.business_id', 'order_item.product_id')
            ->orderBy('sales_return.business_id')
            ->cursor() as $row) {
            DB::table('inventories')
                ->where('business_id', $row->business_id)
                ->where('product_id', $row->product_id)
                ->update(['sales_returned_stock' => (int) $row->returned_quantity, 'updated_at' => now()]);
        }
    }
};
