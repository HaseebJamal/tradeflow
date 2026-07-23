<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('email_change_requests')) {
            Schema::create('email_change_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('current_email');
                $table->string('requested_email');
                $table->text('reason');
                $table->string('status')->default('Pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'status']);
                $table->index(['user_id', 'status']);
            });
        }

        DB::table('permission_definitions')->updateOrInsert(
            ['permission_key' => 'users.approve_email_change'],
            [
                'module' => 'staff',
                'permission_type' => 'action',
                'label' => 'Approve Email Changes',
                'description' => 'Approve or reject staff login email changes',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        DB::table('permission_definitions')->where('permission_key', 'users.approve_email_change')->delete();
        Schema::dropIfExists('email_change_requests');
        Cache::forget('tradeflow.permission-definition-keys');
    }
};
