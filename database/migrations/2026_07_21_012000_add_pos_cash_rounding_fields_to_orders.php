<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'cash_received')) {
                $table->decimal('cash_received', 12, 2)->nullable()->after('paid_amount');
            }
            if (! Schema::hasColumn('orders', 'change_amount')) {
                $table->unsignedBigInteger('change_amount')->default(0)->after('cash_received');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            foreach (['change_amount', 'cash_received'] as $column) {
                if (Schema::hasColumn('orders', $column)) $table->dropColumn($column);
            }
        });
    }
};
