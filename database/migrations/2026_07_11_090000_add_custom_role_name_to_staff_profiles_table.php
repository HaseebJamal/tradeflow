<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('staff_profiles', 'custom_role_name')) {
            Schema::table('staff_profiles', function (Blueprint $table) {
                $table->string('custom_role_name')->nullable()->after('employee_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('staff_profiles', 'custom_role_name')) {
            Schema::table('staff_profiles', function (Blueprint $table) {
                $table->dropColumn('custom_role_name');
            });
        }
    }
};
