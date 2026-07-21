<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('deliveries', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->after('business_id')->constrained()->nullOnDelete();
                $table->index(['business_id', 'invoice_id']);
            }

            // Historical deliveries retain their source order. New POS deliveries
            // are invoice-backed and no longer require an order_id value.
            if (Schema::hasColumn('deliveries', 'order_id')) {
                $table->foreignId('order_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            if (Schema::hasColumn('deliveries', 'invoice_id')) {
                $table->dropIndex(['business_id', 'invoice_id']);
                $table->dropConstrainedForeignId('invoice_id');
            }
        });
    }
};
