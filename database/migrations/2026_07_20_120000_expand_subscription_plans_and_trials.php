<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'slug')) $table->string('slug')->nullable()->unique()->after('name');
            if (! Schema::hasColumn('subscription_plans', 'short_description')) $table->string('short_description', 255)->nullable()->after('slug');
            if (! Schema::hasColumn('subscription_plans', 'monthly_price')) $table->unsignedBigInteger('monthly_price')->nullable()->after('price');
            if (! Schema::hasColumn('subscription_plans', 'yearly_price')) $table->unsignedBigInteger('yearly_price')->nullable()->after('monthly_price');
            if (! Schema::hasColumn('subscription_plans', 'trial_days')) $table->unsignedInteger('trial_days')->default(14)->after('yearly_price');
            if (! Schema::hasColumn('subscription_plans', 'included_modules')) $table->json('included_modules')->nullable()->after('order_limit');
            if (! Schema::hasColumn('subscription_plans', 'features')) $table->json('features')->nullable()->after('included_modules');
            if (! Schema::hasColumn('subscription_plans', 'is_public')) $table->boolean('is_public')->default(true)->after('features');
            if (! Schema::hasColumn('subscription_plans', 'is_recommended')) $table->boolean('is_recommended')->default(false)->after('is_public');
            if (! Schema::hasColumn('subscription_plans', 'sort_order')) $table->unsignedInteger('sort_order')->default(0)->after('is_recommended');
            if (! Schema::hasColumn('subscription_plans', 'archived_at')) $table->timestamp('archived_at')->nullable()->after('status');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'billing_cycle')) $table->string('billing_cycle', 20)->default('Monthly')->after('subscription_plan_id');
            if (! Schema::hasColumn('subscriptions', 'trial_start_at')) $table->date('trial_start_at')->nullable()->after('ends_at');
            if (! Schema::hasColumn('subscriptions', 'trial_end_at')) $table->date('trial_end_at')->nullable()->after('trial_start_at');
            if (! Schema::hasColumn('subscriptions', 'cancelled_at')) $table->timestamp('cancelled_at')->nullable()->after('trial_end_at');
            if (! Schema::hasColumn('subscriptions', 'renewed_at')) $table->timestamp('renewed_at')->nullable()->after('cancelled_at');
            if (! Schema::hasColumn('subscriptions', 'auto_renew')) $table->boolean('auto_renew')->default(false)->after('renewed_at');
            if (! Schema::hasColumn('subscriptions', 'payment_status')) $table->string('payment_status', 30)->default('Pending')->after('payment_method');
            if (! Schema::hasColumn('subscriptions', 'payment_reference')) $table->string('payment_reference', 120)->nullable()->after('payment_status');
            if (! Schema::hasColumn('subscriptions', 'note')) $table->text('note')->nullable()->after('payment_reference');
        });
    }

    public function down(): void
    {
        // The upgrade is intentionally non-destructive to preserve live plan data.
    }
};
