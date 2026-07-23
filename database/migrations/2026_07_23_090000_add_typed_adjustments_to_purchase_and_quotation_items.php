<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_items', 'sales_quotation_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $priceColumn = $tableName === 'purchase_items' ? 'unit_cost' : 'unit_price';

                if (! Schema::hasColumn($tableName, 'discount_type')) {
                    $table->string('discount_type', 12)->default('fixed')->after($priceColumn);
                }
                if (! Schema::hasColumn($tableName, 'discount_value')) {
                    $table->decimal('discount_value', 15, 2)->default(0)->after('discount_type');
                }
                if (! Schema::hasColumn($tableName, 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_value');
                }
                if (! Schema::hasColumn($tableName, 'tax_type')) {
                    $table->string('tax_type', 12)->default('fixed')->after('discount_amount');
                }
                if (! Schema::hasColumn($tableName, 'tax_value')) {
                    $table->decimal('tax_value', 15, 2)->default(0)->after('tax_type');
                }
                if (! Schema::hasColumn($tableName, 'tax_amount')) {
                    $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_value');
                }
            });

            DB::table($tableName)->update([
                'discount_type' => 'fixed',
                'discount_value' => DB::raw('COALESCE(discount_amount, 0)'),
                'tax_type' => 'fixed',
                'tax_value' => DB::raw('COALESCE(tax_amount, 0)'),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['purchase_items', 'sales_quotation_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $candidates = ['discount_value', 'discount_type', 'tax_value', 'tax_type'];
                if ($tableName === 'sales_quotation_items') {
                    $candidates[] = 'discount_amount';
                    $candidates[] = 'tax_amount';
                }
                $columns = array_filter($candidates, fn (string $column) => Schema::hasColumn($tableName, $column));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
