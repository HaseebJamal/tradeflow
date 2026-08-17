<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'discount_type')) {
                $table->string('discount_type', 12)->default('percentage')->after('line_subtotal');
            }
            if (! Schema::hasColumn('order_items', 'discount_value')) {
                $table->decimal('discount_value', 14, 2)->default(0)->after('discount_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'discount_value')) $table->dropColumn('discount_value');
            if (Schema::hasColumn('order_items', 'discount_type')) $table->dropColumn('discount_type');
        });
    }
};
