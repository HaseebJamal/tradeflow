<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_items', 'free_quantity')) {
                $table->decimal('free_quantity', 15, 3)->default(0)->after('quantity');
            }
        });
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            foreach (['paid_accepted_quantity', 'free_accepted_quantity', 'paid_damaged_quantity', 'free_damaged_quantity', 'paid_rejected_quantity', 'free_rejected_quantity'] as $column) {
                if (! Schema::hasColumn('goods_receipt_items', $column)) $table->decimal($column, 15, 3)->nullable();
            }
        });
        Schema::table('purchase_return_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_return_items', 'paid_quantity')) $table->decimal('paid_quantity', 15, 3)->nullable()->after('quantity');
            if (! Schema::hasColumn('purchase_return_items', 'free_quantity')) $table->decimal('free_quantity', 15, 3)->default(0)->after('paid_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_return_items', 'free_quantity')) $table->dropColumn('free_quantity');
            if (Schema::hasColumn('purchase_return_items', 'paid_quantity')) $table->dropColumn('paid_quantity');
        });
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            foreach (['paid_accepted_quantity', 'free_accepted_quantity', 'paid_damaged_quantity', 'free_damaged_quantity', 'paid_rejected_quantity', 'free_rejected_quantity'] as $column) if (Schema::hasColumn('goods_receipt_items', $column)) $table->dropColumn($column);
        });
        Schema::table('purchase_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_items', 'free_quantity')) $table->dropColumn('free_quantity');
        });
    }
};
