<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('default_paid_access_days')->default(30)->after('trial_days');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->dropColumn('default_paid_access_days');
        });
    }
};
