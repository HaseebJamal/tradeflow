<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'plan_selection_source')) {
                $table->string('plan_selection_source', 20)->nullable()->after('selected_billing_cycle');
            }
        });
    }

    public function down(): void
    {
        // Historical registration source data is intentionally preserved.
    }
};
