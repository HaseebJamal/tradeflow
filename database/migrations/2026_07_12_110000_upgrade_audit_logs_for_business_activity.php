<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'user_name')) $table->string('user_name')->nullable()->after('user_id');
            if (!Schema::hasColumn('audit_logs', 'role')) $table->string('role')->nullable()->after('actor_role');
            if (!Schema::hasColumn('audit_logs', 'route')) $table->string('route')->nullable()->after('description');
            if (!Schema::hasColumn('audit_logs', 'record_type')) $table->string('record_type')->nullable()->after('module');
            if (!Schema::hasColumn('audit_logs', 'occurred_at')) $table->timestamp('occurred_at')->nullable()->after('user_agent');
        });

        DB::table('audit_logs')->whereNull('occurred_at')->update(['occurred_at' => DB::raw('created_at')]);

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('business_id', 'audit_logs_business_id_index');
            $table->index('user_id', 'audit_logs_user_id_index');
            $table->index('module', 'audit_logs_module_index');
            $table->index('action', 'audit_logs_action_index');
            $table->index('occurred_at', 'audit_logs_occurred_at_index');
            $table->index(['business_id', 'occurred_at'], 'audit_logs_business_occurred_index');
        });

        $permissions = [
            ['audit_logs', 'audit_logs.view', 'module', 'Enable Audit Logs'],
            ['audit_logs', 'audit_logs.export', 'action', 'Export Audit Logs'],
            ['audit_logs', 'audit_logs.view_details', 'action', 'View Audit Log Details'],
        ];

        foreach ($permissions as [$module, $key, $type, $label]) {
            DB::table('permission_definitions')->updateOrInsert(
                ['permission_key' => $key],
                ['module' => $module, 'permission_type' => $type, 'label' => $label, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_business_id_index');
            $table->dropIndex('audit_logs_user_id_index');
            $table->dropIndex('audit_logs_module_index');
            $table->dropIndex('audit_logs_action_index');
            $table->dropIndex('audit_logs_occurred_at_index');
            $table->dropIndex('audit_logs_business_occurred_index');
        });
    }
};
