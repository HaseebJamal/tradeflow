<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'actor_id')) $table->foreignId('actor_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('audit_logs', 'actor_role')) $table->string('actor_role')->nullable()->after('actor_id');
            if (!Schema::hasColumn('audit_logs', 'target_user_id')) $table->foreignId('target_user_id')->nullable()->after('business_id')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('audit_logs', 'description')) $table->text('description')->nullable()->after('action');
            if (!Schema::hasColumn('audit_logs', 'user_agent')) $table->text('user_agent')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        //
    }
};
