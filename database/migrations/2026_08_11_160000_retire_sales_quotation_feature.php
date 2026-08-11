<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retire the feature without deleting historical quotation records or
     * tables. Existing records remain intact for audit/database safety but
     * are no longer exposed as an active workspace permission.
     */
    public function up(): void
    {
        if (Schema::hasTable('permission_definitions')) {
            DB::table('permission_definitions')
                ->where('permission_key', 'sales.quotations')
                ->update(['status' => 'inactive', 'updated_at' => now()]);
        }

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        if (Schema::hasTable('permission_definitions')) {
            DB::table('permission_definitions')
                ->where('permission_key', 'sales.quotations')
                ->update(['status' => 'active', 'updated_at' => now()]);
        }

        Cache::forget('tradeflow.permission-definition-keys');
    }
};
