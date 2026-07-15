<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('company_permissions')->where('permission_key', 'orders.assign_delivery')->where('allowed', true)->orderBy('id')->each(function (object $permission): void {
            DB::table('company_permissions')->updateOrInsert(
                ['company_id' => $permission->company_id, 'permission_key' => 'deliveries.assign'],
                ['allowed' => true, 'assigned_by' => $permission->assigned_by, 'created_at' => now(), 'updated_at' => now()]
            );
        });

        DB::table('users')->whereNotNull('business_id')->orderBy('id')->select('id', 'permissions')->each(function (object $user): void {
            $permissions = is_array($user->permissions) ? $user->permissions : json_decode((string) $user->permissions, true);
            if (!is_array($permissions) || !in_array('orders.assign_delivery', $permissions, true)) return;
            $permissions = collect($permissions)->map(fn ($permission) => $permission === 'orders.assign_delivery' ? 'deliveries.assign' : $permission)->unique()->values()->all();
            DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode($permissions), 'updated_at' => now()]);
        });
    }

    public function down(): void {}
};
