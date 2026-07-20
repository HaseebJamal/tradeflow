<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OBSOLETE_KEYS = [
        'sales.create',
        'sales.edit',
        'sales.update_status',
        'sales.invoice_void',
        // The dedicated sales_returns module is the authoritative return gate.
        'sales.returns',
    ];

    public function up(): void
    {
        // Keep historical company and staff assignments intact. Inactive
        // definitions are deliberately excluded from both permission UIs.
        DB::table('permission_definitions')
            ->whereIn('permission_key', self::OBSOLETE_KEYS)
            ->update(['status' => 'inactive', 'updated_at' => now()]);

        DB::table('permission_definitions')
            ->where('permission_key', 'sales.view')
            ->update(['label' => 'View Sales', 'updated_at' => now()]);

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        DB::table('permission_definitions')
            ->whereIn('permission_key', self::OBSOLETE_KEYS)
            ->update(['status' => 'active', 'updated_at' => now()]);

        DB::table('permission_definitions')
            ->where('permission_key', 'sales.view')
            ->update(['label' => 'Enable Sales', 'updated_at' => now()]);

        Cache::forget('tradeflow.permission-definition-keys');
    }
};
