<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $actions = ['deliveries.record_collection', 'deliveries.update_status', 'deliveries.upload_proof'];

        DB::table('company_permissions')
            ->whereIn('permission_key', $actions)
            ->where('allowed', true)
            ->orderBy('company_id')
            ->select('company_id', 'assigned_by')
            ->distinct()
            ->each(function (object $permission): void {
                DB::table('company_permissions')->updateOrInsert(
                    ['company_id' => $permission->company_id, 'permission_key' => 'deliveries.view'],
                    ['allowed' => true, 'assigned_by' => $permission->assigned_by, 'created_at' => now(), 'updated_at' => now()]
                );
                Cache::forget('tradeflow.company-permissions.'.$permission->company_id);
            });

        DB::table('users')
            ->whereNotNull('business_id')
            ->whereNotNull('permissions')
            ->orderBy('id')
            ->select('id', 'permissions')
            ->each(function (object $user) use ($actions): void {
                $permissions = is_array($user->permissions) ? $user->permissions : json_decode((string) $user->permissions, true);
                if (!is_array($permissions) || !array_intersect($actions, $permissions) || in_array('deliveries.view', $permissions, true)) {
                    return;
                }

                $permissions[] = 'deliveries.view';
                DB::table('users')->where('id', $user->id)->update([
                    'permissions' => json_encode(array_values(array_unique($permissions))),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // The dependency is safe to retain because historical staff access
        // should not be narrowed by a rollback.
    }
};
