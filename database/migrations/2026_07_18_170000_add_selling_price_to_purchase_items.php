<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('purchase_items', 'selling_price')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->decimal('selling_price', 14, 2)->default(0)->after('unit_cost');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_items', 'selling_price')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->dropColumn('selling_price');
            });
        }
    }
};
