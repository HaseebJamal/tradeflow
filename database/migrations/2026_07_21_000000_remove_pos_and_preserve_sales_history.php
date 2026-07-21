<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pos_returns') && ! Schema::hasTable('sales_returns')) {
            Schema::rename('pos_returns', 'sales_returns');
        }

        if (Schema::hasTable('pos_return_items') && ! Schema::hasTable('sales_return_items')) {
            Schema::rename('pos_return_items', 'sales_return_items');
        }

        if (Schema::hasTable('sales_return_items') && Schema::hasColumn('sales_return_items', 'pos_return_id')) {
            $foreignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'sales_return_items')
                ->where('COLUMN_NAME', 'pos_return_id')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->value('CONSTRAINT_NAME');

            if ($foreignKey && preg_match('/^[A-Za-z0-9_]+$/', $foreignKey)) {
                DB::statement('ALTER TABLE `sales_return_items` DROP FOREIGN KEY `'.$foreignKey.'`');
            }

            Schema::table('sales_return_items', function (Blueprint $table): void {
                $table->renameColumn('pos_return_id', 'sales_return_id');
            });
            Schema::table('sales_return_items', function (Blueprint $table): void {
                $table->foreign('sales_return_id')->references('id')->on('sales_returns')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('orders')) {
            DB::table('orders')->where('sale_channel', 'pos')->update(['sale_channel' => 'sales', 'updated_at' => now()]);
        }

        if (Schema::hasTable('pos_payments') && Schema::hasTable('payments')
            && Schema::hasColumn('payments', 'business_id')
            && Schema::hasColumn('payments', 'order_id')
            && Schema::hasColumn('payments', 'method')
            && Schema::hasColumn('payments', 'amount')) {
            DB::table('pos_payments')->orderBy('id')->each(function (object $payment): void {
                $existing = DB::table('payments')
                    ->where('business_id', $payment->business_id)
                    ->where('order_id', $payment->order_id)
                    ->where('method', $payment->method)
                    ->where('amount', $payment->amount);

                if (Schema::hasColumn('payments', 'reference_number')) {
                    $existing->when($payment->reference_number, fn ($query) => $query->where('reference_number', $payment->reference_number), fn ($query) => $query->whereNull('reference_number'));
                }

                $exists = $existing->exists();

                if (! $exists) {
                    $record = [
                        'business_id' => $payment->business_id,
                        'order_id' => $payment->order_id,
                        'method' => $payment->method,
                        'amount' => $payment->amount,
                        'created_at' => $payment->created_at ?? now(),
                        'updated_at' => $payment->updated_at ?? now(),
                    ];

                    if (Schema::hasColumn('payments', 'customer_id')) {
                        $record['customer_id'] = DB::table('orders')->where('id', $payment->order_id)->value('customer_id');
                    }
                    if (Schema::hasColumn('payments', 'transaction_reference')) {
                        $record['transaction_reference'] = $payment->reference_number;
                    }
                    if (Schema::hasColumn('payments', 'reference_number')) {
                        $record['reference_number'] = $payment->reference_number;
                    }
                    if (Schema::hasColumn('payments', 'payment_date')) {
                        $record['payment_date'] = filled($payment->created_at ?? null) ? Carbon::parse($payment->created_at)->toDateString() : now()->toDateString();
                    }
                    if (Schema::hasColumn('payments', 'status')) {
                        $record['status'] = 'Paid';
                    }

                    DB::table('payments')->insert($record);
                }
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'pos_register_id')) {
            $foreignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'orders')
                ->where('COLUMN_NAME', 'pos_register_id')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->value('CONSTRAINT_NAME');
            if ($foreignKey && preg_match('/^[A-Za-z0-9_]+$/', $foreignKey)) {
                DB::statement('ALTER TABLE `orders` DROP FOREIGN KEY `'.$foreignKey.'`');
            }
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('pos_register_id');
            });
        }

        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_registers');

        if (Schema::hasTable('permission_definitions')) {
            DB::table('permission_definitions')->where('module', 'pos')->delete();
        }
        if (Schema::hasTable('company_permissions')) {
            DB::table('company_permissions')->where('permission_key', 'like', 'pos.%')->delete();
            DB::table('company_permissions')->where('permission_key', 'dashboard.quick_pos_sale')->delete();
        }

        if (Schema::hasTable('permission_definitions')) {
            DB::table('permission_definitions')->where('permission_key', 'dashboard.quick_pos_sale')->delete();
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'permissions')) {
            DB::table('users')->whereNotNull('permissions')->orderBy('id')->each(function (object $user): void {
                $permissions = is_array($user->permissions) ? $user->permissions : json_decode((string) $user->permissions, true);

                if (! is_array($permissions)) {
                    return;
                }

                $filtered = array_values(array_filter($permissions, static fn ($permission): bool => ! str_starts_with(strtolower((string) $permission), 'pos.')));

                if ($filtered !== $permissions) {
                    DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode($filtered)]);
                }
            });
        }
    }

    public function down(): void
    {
        // POS is intentionally retired. Historical sales/returns stay preserved.
    }
};
