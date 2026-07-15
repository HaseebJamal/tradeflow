<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $salesKeys = [
            'orders', 'orders.view', 'orders.create', 'orders.edit', 'orders.update_status', 'orders.status',
            'orders.cancel', 'orders.delete', 'orders.print_invoice', 'payments', 'payments.view',
            'payments.record', 'payments.create', 'payments.verify', 'payments.reverse', 'invoices',
            'invoices.view', 'invoices.create', 'invoices.print', 'invoices.export', 'invoices.void',
        ];

        DB::table('company_permissions')->whereIn('permission_key', $salesKeys)->where('allowed', true)
            ->orderBy('id')->each(function (object $permission): void {
                DB::table('company_permissions')->updateOrInsert(
                    ['company_id' => $permission->company_id, 'permission_key' => 'sales.view'],
                    ['allowed' => true, 'assigned_by' => $permission->assigned_by, 'created_at' => now(), 'updated_at' => now()]
                );
            });

        $mapping = [
            'orders' => 'sales.view', 'orders.view' => 'sales.view', 'orders.create' => 'sales.create',
            'orders.edit' => 'sales.edit', 'orders.update_status' => 'sales.update_status', 'orders.status' => 'sales.update_status',
            'orders.cancel' => 'sales.update_status', 'orders.delete' => 'sales.update_status', 'orders.print_invoice' => 'sales.invoice_export',
            'payments' => 'sales.payments', 'payments.view' => 'sales.payments', 'payments.record' => 'sales.payments',
            'payments.create' => 'sales.payments', 'payments.verify' => 'sales.payments', 'payments.reverse' => 'sales.payments',
            'invoices' => 'sales.invoices', 'invoices.view' => 'sales.invoices', 'invoices.create' => 'sales.invoices',
            'invoices.print' => 'sales.invoice_export', 'invoices.export' => 'sales.invoice_export', 'invoices.void' => 'sales.invoice_void',
            'purchases.return' => 'purchase_returns.process', 'pos.returns' => 'sales_returns.view', 'pos.process_return' => 'sales_returns.process',
        ];

        DB::table('users')->whereNotNull('business_id')->orderBy('id')->select('id', 'permissions')->each(function (object $user) use ($mapping): void {
            $permissions = is_array($user->permissions) ? $user->permissions : json_decode((string) $user->permissions, true);
            if (!is_array($permissions)) {
                return;
            }

            $permissions = collect($permissions)
                ->map(fn ($permission) => $mapping[strtolower((string) $permission)] ?? $permission)
                ->unique()->values()->all();

            DB::table('users')->where('id', $user->id)->update([
                'permissions' => json_encode($permissions),
                'updated_at' => now(),
            ]);
        });

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        Cache::forget('tradeflow.permission-definition-keys');
    }
};
