<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $newActions = ['purchases.edit', 'purchases.confirm'];

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'permissions')) {
            DB::table('users')->whereNotNull('permissions')->orderBy('id')->get(['id', 'permissions'])->each(function (object $user) use ($newActions): void {
                $permissions = is_array($user->permissions) ? $user->permissions : json_decode((string) $user->permissions, true);
                if (!is_array($permissions) || !in_array('purchases.create', $permissions, true)) return;
                $mapped = array_values(array_unique([...$permissions, ...$newActions]));
                if ($mapped !== $permissions) DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode($mapped), 'updated_at' => now()]);
            });
        }

        if (Schema::hasTable('permission_template_items')) {
            DB::table('permission_template_items')->where('permission_key', 'purchases.create')->where('allowed', true)->get(['permission_template_id'])->each(function (object $item) use ($newActions): void {
                foreach ($newActions as $key) {
                    DB::table('permission_template_items')->updateOrInsert(
                        ['permission_template_id' => $item->permission_template_id, 'permission_key' => $key],
                        ['allowed' => true, 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            });
        }
    }

    public function down(): void
    {
        // Keep the granted actions; removing them would unexpectedly revoke
        // access from existing staff roles.
    }
};
