<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('report_type')->default('Business Performance');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_expense', 14, 2)->default(0);
            $table->decimal('profit', 14, 2)->default(0);
            $table->string('status')->default('Pending Review');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('target_type');
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('TradeFlow');
            $table->string('logo')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->unsignedInteger('trial_days')->default(14);
            $table->foreignId('default_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->unsignedInteger('max_upload_size')->default(2048);
            $table->timestamps();
        });

        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'status')) {
                $table->string('status')->default('Active')->after('type');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0)->after('subscription_plan_id');
            }
            if (!Schema::hasColumn('subscriptions', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('amount');
            }
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('support_tickets', 'admin_reply')) {
                $table->text('admin_reply')->nullable()->after('message');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('business_reports');
    }
};
