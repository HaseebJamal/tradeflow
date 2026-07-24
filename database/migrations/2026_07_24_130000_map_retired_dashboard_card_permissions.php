<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const ALIASES = [
        'dashboard.card_pending_customer_payments' => 'dashboard.card_receivables',
        'dashboard.card_pending_supplier_payments' => 'dashboard.card_payables',
    ];

    public function up(): void
    {
        $now = now();
        $affectedCompanyIds = [];

        if (Schema::hasTable('company_permissions')) {
            DB::table('company_permissions')
                ->whereIn('permission_key', array_keys(self::ALIASES))
                ->where('allowed', true)
                ->orderBy('company_id')
                ->get(['company_id', 'permission_key'])
                ->each(function (object $permission) use ($now, &$affectedCompanyIds): void {
                    DB::table('company_permissions')->updateOrInsert(
                        [
                            'company_id' => $permission->company_id,
                            'permission_key' => self::ALIASES[$permission->permission_key],
                        ],
                        ['allowed' => true, 'updated_at' => $now]
                    );

                    $affectedCompanyIds[] = (int) $permission->company_id;
                });
        }

        if (Schema::hasTable('permission_template_items')) {
            DB::table('permission_template_items')
                ->whereIn('permission_key', array_keys(self::ALIASES))
                ->where('allowed', true)
                ->orderBy('permission_template_id')
                ->get(['permission_template_id', 'permission_key'])
                ->each(function (object $item) use ($now): void {
                    DB::table('permission_template_items')->updateOrInsert(
                        [
                            'permission_template_id' => $item->permission_template_id,
                            'permission_key' => self::ALIASES[$item->permission_key],
                        ],
                        ['allowed' => true, 'updated_at' => $now]
                    );
                });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'permissions')) {
            DB::table('users')
                ->whereNotNull('permissions')
                ->orderBy('id')
                ->get(['id', 'permissions'])
                ->each(function (object $user) use ($now): void {
                    $permissions = is_array($user->permissions)
                        ? $user->permissions
                        : json_decode((string) $user->permissions, true);

                    if (!is_array($permissions)) {
                        return;
                    }

                    $mapped = array_values(array_unique(array_map(
                        fn ($permission) => self::ALIASES[$permission] ?? $permission,
                        $permissions
                    )));

                    if ($mapped !== array_values($permissions)) {
                        DB::table('users')->where('id', $user->id)->update([
                            'permissions' => json_encode($mapped),
                            'updated_at' => $now,
                        ]);
                    }
                });
        }

        Cache::forget('tradeflow.permission-definition-keys');
        foreach (array_unique($affectedCompanyIds) as $companyId) {
            Cache::forget('tradeflow.company-permissions.'.$companyId);
        }
    }

    public function down(): void
    {
        // Canonical grants are intentionally retained: converting them back
        // would reintroduce the duplicate choices this migration retires.
        Cache::forget('tradeflow.permission-definition-keys');
    }
};
