<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_return_items', 'quantity')) $table->decimal('quantity', 15, 3)->change();
        });

        Schema::table('inventories', function (Blueprint $table): void {
            if (Schema::hasColumn('inventories', 'purchase_returned_stock')) $table->decimal('purchase_returned_stock', 15, 3)->default(0)->change();
        });
    }

    public function down(): void
    {
        // Decimal return quantities cannot be safely narrowed once recorded.
    }
};
