<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definitions = [
            ['sales', 'sales.view', 'module', 'Enable Sales'],
            ['sales', 'sales.create', 'action', 'Create Sales'],
            ['sales', 'sales.edit', 'action', 'Edit Sales'],
            ['sales', 'sales.update_status', 'action', 'Update Sales Status'],
            ['sales', 'sales.quotations', 'action', 'Manage Quotations'],
            ['sales', 'sales.payments', 'action', 'Manage Customer Payments and Receipts'],
            ['sales', 'sales.invoices', 'action', 'Manage Sales Invoices'],
            ['sales', 'sales.invoice_export', 'action', 'Print or Download Sales Invoices'],
            ['sales', 'sales.invoice_void', 'action', 'Void Sales Invoices'],
            ['purchase_returns', 'purchase_returns.view', 'module', 'Enable Purchase Returns'],
            ['purchase_returns', 'purchase_returns.process', 'action', 'Process Purchase Returns'],
            ['sales_returns', 'sales_returns.view', 'module', 'Enable Sales Returns'],
            ['sales_returns', 'sales_returns.process', 'action', 'Process Sales Returns'],
        ];

        foreach ($definitions as [$module, $key, $type, $label]) {
            DB::table('permission_definitions')->updateOrInsert(
                ['permission_key' => $key],
                ['module' => $module, 'permission_type' => $type, 'label' => $label, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $mapping = [
            'orders.view' => 'sales.view', 'orders.create' => 'sales.create', 'orders.edit' => 'sales.edit',
            'orders.update_status' => 'sales.update_status', 'orders.cancel' => 'sales.update_status', 'orders.delete' => 'sales.update_status',
            'orders.print_invoice' => 'sales.invoice_export',
            'payments.view' => 'sales.payments', 'payments.create' => 'sales.payments', 'payments.verify' => 'sales.payments', 'payments.reverse' => 'sales.payments',
            'invoices.view' => 'sales.invoices', 'invoices.create' => 'sales.invoices', 'invoices.print' => 'sales.invoice_export',
            'invoices.export' => 'sales.invoice_export', 'invoices.void' => 'sales.invoice_void',
            'purchases.return' => 'purchase_returns.process',
            'pos.returns' => 'sales_returns.view', 'pos.process_return' => 'sales_returns.process',
        ];

        foreach ($mapping as $legacy => $current) {
            DB::table('company_permissions')->where('permission_key', $legacy)->orderBy('id')->each(function (object $permission) use ($current): void {
                $existing = DB::table('company_permissions')
                    ->where('company_id', $permission->company_id)
                    ->where('permission_key', $current)
                    ->first();

                DB::table('company_permissions')->updateOrInsert(
                    ['company_id' => $permission->company_id, 'permission_key' => $current],
                    [
                        'allowed' => (bool) ($permission->allowed || $existing?->allowed),
                        'assigned_by' => $permission->assigned_by,
                        'created_at' => $existing?->created_at ?? now(),
                        'updated_at' => now(),
                    ]
                );
            });
        }

        // A previously enabled return action also enables its new return module.
        foreach (['purchases.return' => 'purchase_returns.view', 'pos.returns' => 'sales_returns.view', 'pos.process_return' => 'sales_returns.view'] as $legacy => $moduleKey) {
            DB::table('company_permissions')->where('permission_key', $legacy)->where('allowed', true)->orderBy('id')->each(function (object $permission) use ($moduleKey): void {
                DB::table('company_permissions')->updateOrInsert(
                    ['company_id' => $permission->company_id, 'permission_key' => $moduleKey],
                    ['allowed' => true, 'assigned_by' => $permission->assigned_by, 'created_at' => now(), 'updated_at' => now()]
                );
            });
        }

        DB::table('permission_definitions')->whereIn('module', ['orders', 'payments', 'invoices', 'notifications'])->update(['status' => 'inactive', 'updated_at' => now()]);

        DB::table('users')->whereNotNull('business_id')->orderBy('id')->select('id', 'permissions')->each(function (object $user) use ($mapping): void {
            $permissions = is_array($user->permissions) ? $user->permissions : json_decode((string) $user->permissions, true);
            if (!is_array($permissions)) return;
            $remapped = collect($permissions)->map(fn ($permission) => $mapping[strtolower((string) $permission)] ?? $permission)->unique()->values()->all();
            DB::table('users')->where('id', $user->id)->update(['permissions' => json_encode($remapped), 'updated_at' => now()]);
        });

        Cache::forget('tradeflow.permission-definition-keys');
    }

    public function down(): void
    {
        DB::table('permission_definitions')->whereIn('module', ['orders', 'payments', 'invoices', 'notifications'])->update(['status' => 'active', 'updated_at' => now()]);
        DB::table('permission_definitions')->whereIn('module', ['purchase_returns', 'sales_returns'])->delete();
        Cache::forget('tradeflow.permission-definition-keys');
    }
};
