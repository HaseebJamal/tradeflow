<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('business_id')->orderBy('id')->select('id', 'permissions')->each(function (object $user): void {
            $permissions = is_array($user->permissions) ? $user->permissions : json_decode((string) $user->permissions, true);
            if (!is_array($permissions)) {
                return;
            }

            if (in_array('purchase_returns.process', $permissions, true)) {
                $permissions[] = 'purchase_returns.view';
            }

            if (in_array('sales_returns.process', $permissions, true)) {
                $permissions[] = 'sales_returns.view';
            }

            DB::table('users')->where('id', $user->id)->update([
                'permissions' => json_encode(array_values(array_unique($permissions))),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Permission history is intentionally preserved.
    }
};
