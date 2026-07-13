<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_permissions')->where('permission_key', 'payments.create')->get()->each(function ($permission) {
            DB::table('company_permissions')->updateOrInsert(
                ['company_id' => $permission->company_id, 'permission_key' => 'sales.payments'],
                [
                    'allowed' => $permission->allowed,
                    'assigned_by' => $permission->assigned_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        });

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        // Sales permissions are retained because they may have been managed independently.
    }
};
