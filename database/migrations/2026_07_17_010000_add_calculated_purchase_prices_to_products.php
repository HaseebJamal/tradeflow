<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'latest_purchase_price')) {
                $table->decimal('latest_purchase_price', 14, 2)->nullable()->after('purchase_cost');
            }

            if (!Schema::hasColumn('products', 'average_purchase_price')) {
                $table->decimal('average_purchase_price', 14, 2)->nullable()->after('latest_purchase_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = array_filter(['average_purchase_price', 'latest_purchase_price'], fn (string $column) => Schema::hasColumn('products', $column));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
