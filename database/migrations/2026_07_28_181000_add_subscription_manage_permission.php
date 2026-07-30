<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permission_definitions')) {
            return;
        }

        DB::table('permission_definitions')->updateOrInsert(
            ['permission_key' => 'subscriptions.manage'],
            [
                'module' => 'subscriptions',
                'permission_type' => 'action',
                'label' => 'Manage Subscription',
                'status' => 'active',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        // Existing assignments must remain valid.
    }
};
