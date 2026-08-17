<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'standard_unit_price')) $table->decimal('standard_unit_price', 14, 2)->nullable()->after('unit_price');
            if (! Schema::hasColumn('order_items', 'is_price_overridden')) $table->boolean('is_price_overridden')->default(false)->after('standard_unit_price');
            if (! Schema::hasColumn('order_items', 'price_override_reason')) $table->string('price_override_reason', 500)->nullable()->after('is_price_overridden');
        });
        DB::table('permission_definitions')->updateOrInsert(['permission_key' => 'pos.override_price'], ['module' => 'pos', 'permission_type' => 'action', 'label' => 'Override Price', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $companyIds = DB::table('company_permissions')->where('permission_key', 'pos.custom_price')->where('allowed', true)->pluck('company_id');
        DB::table('company_permissions')->where('permission_key', 'pos.custom_price')->where('allowed', true)->orderBy('id')->each(function (object $permission): void {
            DB::table('company_permissions')->updateOrInsert(['company_id' => $permission->company_id, 'permission_key' => 'pos.override_price'], ['allowed' => true, 'assigned_by' => $permission->assigned_by, 'created_at' => now(), 'updated_at' => now()]);
        });
        // Keep current staff access intact while moving the capability to its
        // clearer, auditable name. New assignments are managed as
        // `pos.override_price` only.
        DB::table('users')->whereNotNull('permissions')->orderBy('id')->each(function (object $user): void {
            $permissions = json_decode((string) $user->permissions, true);
            if (! is_array($permissions) || ! in_array('pos.custom_price', $permissions, true) || in_array('pos.override_price', $permissions, true)) {
                return;
            }

            $permissions[] = 'pos.override_price';
            DB::table('users')->where('id', $user->id)->update([
                'permissions' => json_encode(array_values(array_unique($permissions))),
                'updated_at' => now(),
            ]);
        });
        $companyIds->each(fn (int $companyId) => Cache::forget('tradeflow.company-permissions.'.$companyId));
        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        DB::table('company_permissions')->where('permission_key', 'pos.override_price')->delete();
        DB::table('permission_definitions')->where('permission_key', 'pos.override_price')->delete();
        Cache::forget('tradeflow.permission-definition-keys');
        Schema::table('order_items', function (Blueprint $table) {
            foreach (['price_override_reason', 'is_price_overridden', 'standard_unit_price'] as $column) if (Schema::hasColumn('order_items', $column)) $table->dropColumn($column);
        });
    }
};
