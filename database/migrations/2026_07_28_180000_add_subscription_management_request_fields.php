<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (! Schema::hasColumn('subscriptions', 'cancellation_scheduled_at')) {
                    $table->date('cancellation_scheduled_at')->nullable()->after('cancelled_at');
                }
                if (! Schema::hasColumn('subscriptions', 'cancellation_reason')) {
                    $table->string('cancellation_reason', 500)->nullable()->after('cancellation_scheduled_at');
                }
            });
        }

        if (Schema::hasTable('subscription_change_requests')) {
            Schema::table('subscription_change_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('subscription_change_requests', 'effective_at')) {
                    $table->date('effective_at')->nullable()->after('ends_at');
                }
            });

            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE subscription_change_requests MODIFY type VARCHAR(40) NOT NULL');
            }
        }

        if (Schema::hasTable('permission_definitions')) {
            foreach ([
                'subscriptions.manage' => 'Manage Subscription',
                'subscriptions.change_billing_cycle' => 'Change Subscription Billing Cycle',
                'subscriptions.change_payment_method' => 'Change Subscription Payment Method',
                'subscriptions.resume_cancellation' => 'Resume Scheduled Subscription Cancellation',
            ] as $key => $label) {
                DB::table('permission_definitions')->updateOrInsert(
                    ['permission_key' => $key],
                    [
                        'module' => 'subscriptions',
                        'permission_type' => 'action',
                        'label' => $label,
                        'status' => 'active',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            Cache::forget('tradeflow.permission-definition-keys');
        }
    }

    public function down(): void
    {
        // Subscription history and permissions are intentionally retained.
    }
};
