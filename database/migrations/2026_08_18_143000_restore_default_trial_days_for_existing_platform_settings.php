<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Older platform-setting rows can contain a zero-day trial. A zero-day
     * trial is not a supported registration state and blocks every public
     * registration, so restore the application's documented default.
     */
    public function up(): void
    {
        if (! Schema::hasTable('platform_settings')) {
            return;
        }

        DB::table('platform_settings')
            ->where('trial_days', '<', 1)
            ->update([
                'trial_days' => 14,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Do not reinstate an invalid trial configuration.
    }
};
