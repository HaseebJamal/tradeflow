<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('retailer_id')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('orders', 'stock_restored_at')) {
                $table->timestamp('stock_restored_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('stock_restored_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }

            if (Schema::hasColumn('orders', 'stock_restored_at')) {
                $table->dropColumn('stock_restored_at');
            }

            if (Schema::hasColumn('orders', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
