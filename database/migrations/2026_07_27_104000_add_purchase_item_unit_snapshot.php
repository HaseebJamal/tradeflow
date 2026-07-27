<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table): void {
            if (!Schema::hasColumn('purchase_items', 'unit_snapshot')) {
                $table->string('unit_snapshot')->nullable()->after('product_name_snapshot');
            }
        });
    }

    public function down(): void
    {
        // Kept forward-only so historical purchase item snapshots stay intact.
    }
};
