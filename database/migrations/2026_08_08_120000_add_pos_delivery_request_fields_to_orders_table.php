<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'delivery_required')) {
                $table->boolean('delivery_required')->default(false)->after('sale_channel');
            }

            if (! Schema::hasColumn('orders', 'delivery_address')) {
                $table->text('delivery_address')->nullable()->after('delivery_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('orders', 'delivery_address') ? 'delivery_address' : null,
                Schema::hasColumn('orders', 'delivery_required') ? 'delivery_required' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
