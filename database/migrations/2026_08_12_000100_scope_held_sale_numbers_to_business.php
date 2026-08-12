<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('held_pos_sales', function (Blueprint $table): void {
            $table->dropUnique(['hold_number']);
            $table->unique(['business_id', 'hold_number'], 'held_pos_sales_business_hold_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('held_pos_sales', function (Blueprint $table): void {
            $table->dropUnique('held_pos_sales_business_hold_number_unique');
            $table->unique('hold_number');
        });
    }
};
