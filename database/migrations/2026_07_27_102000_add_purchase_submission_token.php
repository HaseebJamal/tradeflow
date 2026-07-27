<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            if (!Schema::hasColumn('purchases', 'submission_token')) {
                $table->uuid('submission_token')->nullable()->after('purchase_number');
                $table->unique(['business_id', 'submission_token'], 'purchases_business_submission_token_unique');
            }
        });
    }

    public function down(): void
    {
        // Submission tokens protect already-created purchase data and are
        // intentionally retained once this workflow is in use.
    }
};
