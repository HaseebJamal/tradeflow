<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // sales_returns.view/process are the live route permissions. This
        // legacy Sales-group action is only a duplicate assignment option.
        DB::table('permission_definitions')
            ->where('permission_key', 'sales.returns')
            ->update(['status' => 'inactive', 'updated_at' => now()]);

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        DB::table('permission_definitions')
            ->where('permission_key', 'sales.returns')
            ->update(['status' => 'active', 'updated_at' => now()]);

        Cache::forget('tradeflow.permission-definition-keys');
    }
};
