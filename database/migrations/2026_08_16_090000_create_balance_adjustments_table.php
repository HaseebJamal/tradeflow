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
        if (! Schema::hasTable('balance_adjustments')) {
            Schema::create('balance_adjustments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('party_type', 20);
                $table->unsignedBigInteger('party_id');
                $table->string('reference', 32);
                $table->string('adjustment_type', 40);
                $table->decimal('amount', 14, 2);
                $table->decimal('previous_balance', 14, 2);
                $table->decimal('new_balance', 14, 2);
                $table->string('reason', 100);
                $table->string('external_reference', 255)->nullable();
                $table->text('notes')->nullable();
                $table->uuid('submission_token')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reverses_adjustment_id')->nullable()->constrained('balance_adjustments')->nullOnDelete();
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();

                $table->unique(['business_id', 'reference'], 'balance_adjustments_business_reference_unique');
                $table->unique(['business_id', 'submission_token'], 'balance_adjustments_business_token_unique');
                $table->index(['business_id', 'party_type', 'party_id'], 'balance_adjustments_party_idx');
            });
        }

        foreach ([
            ['customers', 'customers.adjust_balance', 'Adjust Customer Balance'],
            ['suppliers', 'suppliers.adjust_balance', 'Adjust Supplier Balance'],
        ] as [$module, $key, $label]) {
            DB::table('permission_definitions')->updateOrInsert(
                ['permission_key' => $key],
                ['module' => $module, 'permission_type' => 'action', 'label' => $label, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
            );
        }

        // These new controls are deliberately a company-level opt-in ceiling.
        // Business owners receive them only where their company has Customers
        // or Suppliers enabled; staff must still be explicitly assigned them.
        DB::table('company_permissions')->whereIn('permission_key', ['customers.view', 'customers'])->where('allowed', true)->orderBy('id')->each(function (object $permission): void {
            DB::table('company_permissions')->updateOrInsert(
                ['company_id' => $permission->company_id, 'permission_key' => 'customers.adjust_balance'],
                ['allowed' => true, 'assigned_by' => $permission->assigned_by, 'created_at' => now(), 'updated_at' => now()],
            );
            Cache::forget('tradeflow.company-permissions.'.$permission->company_id);
        });
        DB::table('company_permissions')->whereIn('permission_key', ['suppliers.view', 'suppliers'])->where('allowed', true)->orderBy('id')->each(function (object $permission): void {
            DB::table('company_permissions')->updateOrInsert(
                ['company_id' => $permission->company_id, 'permission_key' => 'suppliers.adjust_balance'],
                ['allowed' => true, 'assigned_by' => $permission->assigned_by, 'created_at' => now(), 'updated_at' => now()],
            );
            Cache::forget('tradeflow.company-permissions.'.$permission->company_id);
        });
        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        DB::table('company_permissions')->whereIn('permission_key', ['customers.adjust_balance', 'suppliers.adjust_balance'])->delete();
        DB::table('permission_definitions')->whereIn('permission_key', ['customers.adjust_balance', 'suppliers.adjust_balance'])->delete();
        Cache::forget('tradeflow.permission-definition-keys');
        Schema::dropIfExists('balance_adjustments');
    }
};
