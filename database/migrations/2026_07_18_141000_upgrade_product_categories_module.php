<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'description')) $table->text('description')->nullable()->after('status');
            if (!Schema::hasColumn('categories', 'created_by')) $table->foreignId('created_by')->nullable()->after('business_id')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('categories', 'deleted_at')) $table->softDeletes();
        });

        if (Schema::hasTable('permission_definitions')) {
            foreach ([
                ['categories.view', 'module', 'View Categories'],
                ['categories.create', 'action', 'Create Categories'],
                ['categories.edit', 'action', 'Edit Categories'],
                ['categories.status', 'action', 'Activate / Deactivate Categories'],
                ['categories.archive', 'action', 'Archive / Restore Categories'],
                ['categories.delete', 'action', 'Delete Categories'],
            ] as [$key, $type, $label]) {
                DB::table('permission_definitions')->updateOrInsert(['permission_key' => $key], ['module' => 'categories', 'permission_type' => $type, 'label' => $label, 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]);
            }
            Cache::forget('tradeflow.permission-definition-keys');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permission_definitions')) DB::table('permission_definitions')->where('module', 'categories')->delete();
    }
};
