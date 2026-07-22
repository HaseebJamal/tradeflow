<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_change_requests') && ! Schema::hasColumn('subscription_change_requests', 'admin_note')) {
            Schema::table('subscription_change_requests', function (Blueprint $table) {
                $table->text('admin_note')->nullable()->after('note');
            });
        }
    }

    public function down(): void
    {
        // Historical review notes remain preserved.
    }
};
