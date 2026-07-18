<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'line_subtotal')) {
                $table->decimal('line_subtotal', 14, 2)->default(0)->after('unit_price');
            }
            if (!Schema::hasColumn('order_items', 'discount_rate')) {
                $table->unsignedInteger('discount_rate')->default(0)->after('line_subtotal');
            }
            if (!Schema::hasColumn('order_items', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 2)->default(0)->after('discount_rate');
            }
            if (!Schema::hasColumn('order_items', 'tax_rate')) {
                $table->unsignedInteger('tax_rate')->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('order_items', 'tax_amount')) {
                $table->decimal('tax_amount', 14, 2)->default(0)->after('tax_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            foreach (['tax_amount', 'tax_rate', 'discount_amount', 'discount_rate', 'line_subtotal'] as $column) {
                if (Schema::hasColumn('order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
