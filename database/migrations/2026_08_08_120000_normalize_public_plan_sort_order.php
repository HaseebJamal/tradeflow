<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep the public plan order in the existing sort_order column.  The
     * landing page already orders by this column; assigning the canonical
     * values here makes its output deterministic without coupling cards to
     * prices, limits, or plan IDs.
     */
    public function up(): void
    {
        if (! Schema::hasTable('subscription_plans') || ! Schema::hasColumn('subscription_plans', 'sort_order')) {
            return;
        }

        $priorities = [
            'basic' => 1,
            'standard' => 2,
            'premium' => 3,
            'ultimate' => 4,
            // Preserve the legacy spelling in existing data without renaming it.
            'ulitimate' => 4,
        ];

        foreach ($priorities as $name => $sortOrder) {
            DB::table('subscription_plans')
                ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
                ->update(['sort_order' => $sortOrder]);
        }

        // Unrecognised future plans remain dynamic and are displayed after the
        // established public plans until an administrator assigns a position.
        DB::table('subscription_plans')
            ->where('sort_order', 0)
            ->update(['sort_order' => 999]);
    }

    public function down(): void
    {
        // Do not discard administrator-managed ordering on rollback.
    }
};
