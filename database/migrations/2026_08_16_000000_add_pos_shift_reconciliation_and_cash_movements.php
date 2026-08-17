<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_registers', function (Blueprint $table): void {
            foreach (['cash_sales', 'cash_refunds', 'cash_in', 'cash_out'] as $column) {
                if (! Schema::hasColumn('pos_registers', $column)) {
                    $table->decimal($column, 14, 2)->default(0);
                }
            }
        });

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'pos_register_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->foreignId('pos_register_id')->nullable()->after('order_id')->constrained('pos_registers')->nullOnDelete();
                $table->index(['business_id', 'pos_register_id', 'method']);
            });
        }

        if (Schema::hasTable('sales_returns') && ! Schema::hasColumn('sales_returns', 'pos_register_id')) {
            Schema::table('sales_returns', function (Blueprint $table): void {
                $table->foreignId('pos_register_id')->nullable()->after('order_id')->constrained('pos_registers')->nullOnDelete();
                $table->index(['business_id', 'pos_register_id', 'refund_method']);
            });
        }

        if (! Schema::hasTable('pos_register_cash_movements')) {
            Schema::create('pos_register_cash_movements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pos_register_id')->constrained('pos_registers')->cascadeOnDelete();
                $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
                $table->string('type', 20);
                $table->decimal('amount', 14, 2);
                $table->string('reason', 500);
                $table->string('reference', 120)->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();
            });
        }

        if (! $this->indexExists('pos_register_cash_movements', 'prcm_business_register_type_idx')) {
            Schema::table('pos_register_cash_movements', function (Blueprint $table): void {
                $table->index(['business_id', 'pos_register_id', 'type'], 'prcm_business_register_type_idx');
            });
        }

        DB::table('permission_definitions')->updateOrInsert(
            ['permission_key' => 'pos.cash_movement'],
            ['module' => 'pos', 'permission_type' => 'action', 'label' => 'Record Cash In / Out', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        );

        // Enable the company-level ceiling for existing POS-enabled companies.
        // Staff still require this newly explicit action in their own role.
        DB::table('businesses')->orderBy('id')->each(function (object $business): void {
            DB::table('company_permissions')->updateOrInsert(
                ['company_id' => $business->id, 'permission_key' => 'pos.cash_movement'],
                ['allowed' => true, 'assigned_by' => null, 'created_at' => now(), 'updated_at' => now()],
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_register_cash_movements');

        if (Schema::hasTable('sales_returns') && Schema::hasColumn('sales_returns', 'pos_register_id')) {
            Schema::table('sales_returns', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('pos_register_id');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'pos_register_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('pos_register_id');
            });
        }

        Schema::table('pos_registers', function (Blueprint $table): void {
            $columns = collect(['cash_sales', 'cash_refunds', 'cash_in', 'cash_out'])
                ->filter(fn (string $column) => Schema::hasColumn('pos_registers', $column))
                ->all();
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        DB::table('permission_definitions')->where('permission_key', 'pos.cash_movement')->delete();
        DB::table('company_permissions')->where('permission_key', 'pos.cash_movement')->delete();
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
