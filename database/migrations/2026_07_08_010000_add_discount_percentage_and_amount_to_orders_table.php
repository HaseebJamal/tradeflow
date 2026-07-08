<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'discount_percentage')) {
                $table->decimal('discount_percentage', 5, 2)->default(0)->after('discount');
            }
            if (!Schema::hasColumn('orders', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_percentage');
            }
        });

        DB::statement('UPDATE orders SET discount_percentage = COALESCE(discount, 0) WHERE discount_percentage = 0');
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }
            if (Schema::hasColumn('orders', 'discount_percentage')) {
                $table->dropColumn('discount_percentage');
            }
        });
    }
};
