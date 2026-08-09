<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_payments', 'subscription_plan_id')) $table->foreignId('subscription_plan_id')->nullable()->after('subscription_id')->constrained('subscription_plans')->nullOnDelete();
            if (! Schema::hasColumn('platform_payments', 'billing_cycle')) $table->string('billing_cycle', 20)->nullable()->after('subscription_plan_id');
            if (! Schema::hasColumn('platform_payments', 'transaction_reference')) $table->string('transaction_reference', 120)->nullable()->after('reference_number');
            if (! Schema::hasColumn('platform_payments', 'payment_proof')) $table->string('payment_proof')->nullable()->after('transaction_reference');
            if (! Schema::hasColumn('platform_payments', 'submitted_at')) $table->timestamp('submitted_at')->nullable()->after('paid_at');
            if (! Schema::hasColumn('platform_payments', 'verified_at')) $table->timestamp('verified_at')->nullable()->after('submitted_at');
            if (! Schema::hasColumn('platform_payments', 'verified_by')) $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('platform_payments', 'rejection_reason')) $table->text('rejection_reason')->nullable()->after('verified_by');
            if (! Schema::hasColumn('platform_payments', 'period_starts_at')) $table->date('period_starts_at')->nullable()->after('rejection_reason');
            if (! Schema::hasColumn('platform_payments', 'period_ends_at')) $table->date('period_ends_at')->nullable()->after('period_starts_at');
        });
    }

    public function down(): void
    {
        // Payment records are audit evidence and must not be removed by rollback.
    }
};
