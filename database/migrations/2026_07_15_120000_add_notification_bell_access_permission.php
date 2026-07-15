<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Notifications remain a top-bar feature, rather than a sidebar module.
     * This definition is deliberately a single company/staff permission so a
     * company can turn the bell off without deleting its notification history.
     */
    public function up(): void
    {
        DB::table('permission_definitions')->updateOrInsert(
            ['permission_key' => 'notifications.view'],
            [
                'module' => 'notifications',
                'permission_type' => 'module',
                'label' => 'Notification Access',
                'description' => 'Show and use the notification bell and notification history.',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        DB::table('permission_definitions')
            ->where('permission_key', 'notifications.view')
            ->update(['status' => 'inactive', 'updated_at' => now()]);

        Cache::forget('tradeflow.permission-definition-keys');
    }
};
