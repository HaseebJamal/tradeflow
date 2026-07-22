<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_change_requests')) {
            return;
        }

        Schema::table('subscription_change_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_change_requests', 'trial_eligible')) $table->boolean('trial_eligible')->default(false)->after('payment_method');
            if (! Schema::hasColumn('subscription_change_requests', 'trial_days')) $table->unsignedInteger('trial_days')->nullable()->after('trial_eligible');
            if (! Schema::hasColumn('subscription_change_requests', 'starts_at')) $table->date('starts_at')->nullable()->after('trial_days');
            if (! Schema::hasColumn('subscription_change_requests', 'ends_at')) $table->date('ends_at')->nullable()->after('starts_at');
        });
    }

    public function down(): void
    {
        // Historical subscription-review details must remain available.
    }
};
