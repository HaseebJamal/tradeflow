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
        if (!Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->string('unit_name');
                $table->string('short_code', 20);
                $table->string('unit_type', 30);
                $table->string('status', 20)->default('Active');
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();
                $table->unique(['business_id', 'short_code']);
                $table->index(['business_id', 'status']);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->after('category_id')->constrained('units')->nullOnDelete();
            }
        });

        if (Schema::hasTable('permission_definitions')) {
            foreach ([
                ['units.view', 'module', 'View Units'],
                ['units.create', 'action', 'Create Units'],
                ['units.edit', 'action', 'Edit Units'],
                ['units.status', 'action', 'Activate / Deactivate Units'],
                ['units.archive', 'action', 'Archive / Restore Units'],
                ['units.delete', 'action', 'Delete Units'],
            ] as [$key, $type, $label]) {
                DB::table('permission_definitions')->updateOrInsert(
                    ['permission_key' => $key],
                    ['module' => 'units', 'permission_type' => $type, 'label' => $label, 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]
                );
            }
            Cache::forget('tradeflow.permission-definition-keys');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permission_definitions')) {
            DB::table('permission_definitions')->where('module', 'units')->delete();
            Cache::forget('tradeflow.permission-definition-keys');
        }

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'unit_id')) {
                $table->dropConstrainedForeignId('unit_id');
            }
        });

        Schema::dropIfExists('units');
    }
};
