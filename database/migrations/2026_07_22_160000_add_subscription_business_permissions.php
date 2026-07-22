<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permission_definitions')) {
            return;
        }

        $permissions = [
            'subscriptions.view' => ['View Subscription', 'module'],
            'subscriptions.request' => ['Request Subscription', 'action'],
            'subscriptions.upgrade' => ['Upgrade Subscription', 'action'],
            'subscriptions.downgrade' => ['Downgrade Subscription', 'action'],
            'subscriptions.renew' => ['Renew Subscription', 'action'],
            'subscriptions.cancel' => ['Cancel Subscription Request', 'action'],
            'subscriptions.view_history' => ['View Subscription History', 'action'],
        ];

        foreach ($permissions as $key => [$label, $type]) {
            DB::table('permission_definitions')->updateOrInsert(
                ['permission_key' => $key],
                [
                    'module' => 'subscriptions',
                    'permission_type' => $type,
                    'label' => $label,
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        // Permission records may already be assigned to companies or staff.
    }
};
