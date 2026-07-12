<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permission_definitions')->updateOrInsert(
            ['permission_key' => 'expenses.delete'],
            ['module' => 'expenses', 'permission_type' => 'action', 'label' => 'Delete Expenses', 'description' => 'Delete an expense record', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('permission_definitions')->where('permission_key', 'expenses.delete')->delete();
    }
};
