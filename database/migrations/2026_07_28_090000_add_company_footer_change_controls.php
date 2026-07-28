<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            if (! Schema::hasColumn('businesses', 'website')) {
                $table->string('website', 255)->nullable()->after('tax_number');
            }
        });

        Schema::table('business_document_footers', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_document_footers', 'powered_by_text')) {
                $table->string('powered_by_text', 100)->nullable()->after('show_powered_by');
            }
        });
        DB::table('business_document_footers')->whereNull('powered_by_text')->update(['powered_by_text' => 'Powered by TradeFlow']);

        Schema::create('business_footer_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('field', 50);
            $table->text('current_value')->nullable();
            $table->text('requested_value')->nullable();
            $table->text('reason');
            $table->string('status', 30)->default('Pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'field', 'status'], 'footer_change_business_field_status');
        });

        $definitions = [
            'footer_settings.view' => 'View Footer Settings',
            'footer_settings.update' => 'Update Footer Settings',
            'footer_change_requests.create' => 'Request Footer Detail Change',
            'footer_change_requests.review' => 'Review Footer Detail Changes',
        ];
        foreach ($definitions as $key => $label) {
            DB::table('permission_definitions')->updateOrInsert(
                ['permission_key' => $key],
                ['module' => 'settings', 'label' => $label, 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]
            );
        }

        DB::table('company_permissions')->orderBy('id')->eachById(function (object $permission): void {
            $targets = match (strtolower((string) $permission->permission_key)) {
                'settings.view' => ['footer_settings.view'],
                'settings.update' => ['footer_settings.update', 'footer_change_requests.create'],
                default => [],
            };
            foreach ($targets as $target) {
                DB::table('company_permissions')->updateOrInsert(
                    ['company_id' => $permission->company_id, 'permission_key' => $target],
                    ['allowed' => $permission->allowed, 'assigned_by' => $permission->assigned_by, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        DB::table('businesses')->orderBy('id')->eachById(function (object $business): void {
            Cache::forget('tradeflow.company-permissions.'.$business->id);
        });
        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        Schema::dropIfExists('business_footer_change_requests');
        Schema::table('business_document_footers', function (Blueprint $table): void {
            if (Schema::hasColumn('business_document_footers', 'powered_by_text')) $table->dropColumn('powered_by_text');
        });
        Schema::table('businesses', function (Blueprint $table): void {
            if (Schema::hasColumn('businesses', 'website')) $table->dropColumn('website');
        });
    }
};
