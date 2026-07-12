<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'parent_user_id')) $table->foreignId('parent_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('users', 'created_by')) $table->foreignId('created_by')->nullable()->after('parent_user_id')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('users', 'last_seen_at')) $table->timestamp('last_seen_at')->nullable()->after('last_login_at');
            if (!Schema::hasColumn('users', 'last_activity_at')) $table->timestamp('last_activity_at')->nullable()->after('last_seen_at');
            if (!Schema::hasColumn('users', 'deleted_at')) $table->softDeletes();
        });

        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'created_by')) $table->foreignId('created_by')->nullable()->after('owner_id')->constrained('users')->nullOnDelete();
        });

        if (!Schema::hasTable('business_user_assignments')) {
            Schema::create('business_user_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('assignment_role');
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->string('status')->default('Active');
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index(['business_id', 'status']);
            });
        }

        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_role')->nullable();
                $table->string('actor_name_snapshot')->nullable();
                $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('sub_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('module')->index();
                $table->string('action')->index();
                $table->string('route_name')->nullable();
                $table->string('method')->nullable();
                $table->text('description')->nullable();
                $table->string('subject_type')->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->string('session_id')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();
                $table->index('actor_id');
                $table->index('business_id');
            });
        }

        Schema::table('support_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('support_tickets', 'ticket_number')) $table->string('ticket_number')->nullable()->after('id')->unique();
            if (!Schema::hasColumn('support_tickets', 'created_by')) $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('support_tickets', 'assigned_admin_id')) $table->foreignId('assigned_admin_id')->nullable()->after('business_id')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('support_tickets', 'assigned_sub_admin_id')) $table->foreignId('assigned_sub_admin_id')->nullable()->after('assigned_admin_id')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('support_tickets', 'type')) $table->string('type')->default('Other')->after('assigned_sub_admin_id');
            if (!Schema::hasColumn('support_tickets', 'description')) $table->text('description')->nullable()->after('message');
            if (!Schema::hasColumn('support_tickets', 'resolution')) $table->text('resolution')->nullable()->after('status');
            if (!Schema::hasColumn('support_tickets', 'first_response_at')) $table->timestamp('first_response_at')->nullable()->after('resolution');
            if (!Schema::hasColumn('support_tickets', 'resolved_at')) $table->timestamp('resolved_at')->nullable()->after('first_response_at');
            if (!Schema::hasColumn('support_tickets', 'closed_at')) $table->timestamp('closed_at')->nullable()->after('resolved_at');
            if (!Schema::hasColumn('support_tickets', 'status_index_marker')) {
                $table->index('status');
            }
        });

        if (!Schema::hasTable('ticket_messages')) {
            Schema::create('ticket_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('message');
                $table->string('attachment')->nullable();
                $table->boolean('internal_note')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('business_user_assignments');
    }
};
