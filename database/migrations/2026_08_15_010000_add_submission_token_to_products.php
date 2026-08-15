<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'submission_token')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->uuid('submission_token')->nullable()->after('status');
            $table->unique(['business_id', 'submission_token'], 'products_business_submission_token_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('products', 'submission_token')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_business_submission_token_unique');
            $table->dropColumn('submission_token');
        });
    }
};
