<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('permission_definitions')->updateOrInsert(
            ['permission_key' => 'notifications.view'],
            [
                'module' => 'notifications',
                'permission_type' => 'module',
                'label' => 'Enable Notifications',
                'description' => 'Receive company access, approval, and platform updates.',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('businesses')->select('id', 'owner_id')->orderBy('id')->each(function (object $business): void {
            DB::table('company_permissions')->updateOrInsert(
                ['company_id' => $business->id, 'permission_key' => 'notifications.view'],
                ['allowed' => true, 'assigned_by' => $business->owner_id, 'created_at' => now(), 'updated_at' => now()]
            );
        });

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        DB::table('company_permissions')->where('permission_key', 'notifications.view')->delete();
        DB::table('permission_definitions')->where('permission_key', 'notifications.view')->delete();
        Cache::forget('tradeflow.permission-definition-keys');
    }
};
