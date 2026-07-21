<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $keys = DB::table('permission_definitions')->where('module', 'pos')->pluck('permission_key');

        DB::table('businesses')->select('id')->orderBy('id')->each(function (object $business) use ($keys): void {
            foreach ($keys as $key) {
                DB::table('company_permissions')->updateOrInsert(
                    ['company_id' => $business->id, 'permission_key' => $key],
                    ['allowed' => true, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });
    }

    public function down(): void
    {
        DB::table('company_permissions')->where('permission_key', 'like', 'pos.%')->delete();
    }
};
