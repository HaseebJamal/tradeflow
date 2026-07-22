<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (! Schema::hasColumn('businesses', 'selected_plan_id')) {
                $table->foreignId('selected_plan_id')->nullable()->after('owner_id')->constrained('subscription_plans')->nullOnDelete();
            }
            if (! Schema::hasColumn('businesses', 'selected_billing_cycle')) $table->string('selected_billing_cycle', 20)->nullable()->after('selected_plan_id');
            if (! Schema::hasColumn('businesses', 'selected_plan_price')) $table->unsignedBigInteger('selected_plan_price')->nullable()->after('selected_billing_cycle');
            if (! Schema::hasColumn('businesses', 'selected_plan_snapshot')) $table->json('selected_plan_snapshot')->nullable()->after('selected_plan_price');
            if (! Schema::hasColumn('businesses', 'trial_eligible')) $table->boolean('trial_eligible')->default(true)->after('selected_plan_snapshot');
            if (! Schema::hasColumn('businesses', 'requested_trial_days')) $table->unsignedInteger('requested_trial_days')->nullable()->after('trial_eligible');
            if (! Schema::hasColumn('businesses', 'subscription_request_status')) $table->string('subscription_request_status', 40)->default('Pending Review')->after('requested_trial_days');
            if (! Schema::hasColumn('businesses', 'plan_selected_at')) $table->timestamp('plan_selected_at')->nullable()->after('subscription_request_status');
            if (! Schema::hasColumn('businesses', 'subscription_admin_note')) $table->text('subscription_admin_note')->nullable()->after('plan_selected_at');
        });
    }

    public function down(): void
    {
        // Registration plan history must remain intact after deployment.
    }
};
