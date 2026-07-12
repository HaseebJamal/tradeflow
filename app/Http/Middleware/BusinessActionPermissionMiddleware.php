<?php

namespace App\Http\Middleware;

use App\Services\CompanyPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BusinessActionPermissionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $permission = $this->permissionFor($request->route()?->getName());
        if (!$permission) {
            return $next($request);
        }

        $user = $request->user();
        $companyPermissions = app(CompanyPermissionService::class);
        if (!$user || !$companyPermissions->allows($user, $permission)) {
            return $this->deny($request, 'This feature is not enabled for your company.');
        }

        if (!$companyPermissions->allowsUser($user, $permission)) {
            return $this->deny($request, 'You do not have permission to access this module. Please contact your business owner.');
        }

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            abort(403, $message);
        }

        return redirect()->back()->withErrors(['permission' => $message]);
    }

    private function permissionFor(?string $route): ?string
    {
        return match ($route) {
            'business.products.index', 'business.products.show' => 'products.view',
            'business.products.create', 'business.products.store' => 'products.create',
            'business.products.edit', 'business.products.update' => 'products.edit',
            'business.products.destroy' => 'products.delete',
            'business.products.archive' => 'products.archive', 'business.products.restore' => 'products.restore',
            'business.products.bulk', 'business.products.bulk.store' => 'products.bulk_import',
            'business.products.export', 'business.products.template' => 'products.export',
            'business.inventory' => 'inventory.view', 'business.inventory.adjust' => 'inventory.adjust_stock', 'business.inventory.transfer' => 'inventory.stock_transfer', 'business.inventory.alert' => 'inventory.low_stock_alerts',
            'business.customers.index', 'business.customers.show', 'business.customers.statement' => 'customers.view',
            'business.customers.store' => 'customers.create', 'business.customers.update', 'business.customers.status' => 'customers.edit',
            'business.customers.archive' => 'customers.archive', 'business.customers.restore' => 'customers.restore',
            'business.suppliers.index', 'business.suppliers.show' => 'suppliers.view',
            'business.suppliers.create', 'business.suppliers.store' => 'suppliers.create', 'business.suppliers.edit', 'business.suppliers.update' => 'suppliers.edit', 'business.suppliers.destroy' => 'suppliers.archive',
            'business.orders.index', 'business.orders.show' => 'orders.view', 'business.orders.create', 'business.orders.store' => 'orders.create',
            'business.orders.edit', 'business.orders.update' => 'orders.edit', 'business.orders.status' => 'orders.update_status',
            'business.orders.cancel' => 'orders.cancel', 'business.orders.destroy', 'business.orders.void' => 'orders.delete', 'business.orders.assignDelivery' => 'orders.assign_delivery',
            'business.pos.index', 'business.pos.history' => 'pos.view', 'business.pos.register.open' => 'pos.open_register', 'business.pos.register.close' => 'pos.close_register',
            'business.pos.sales.store' => 'pos.create_sale', 'business.pos.receipt', 'business.pos.receipt.pdf', 'business.pos.receipt.pdf.download' => 'pos.print_receipt', 'business.pos.void' => 'pos.void_sale', 'business.pos.returns' => 'pos.returns',
            'business.pos.returns.store' => 'pos.process_return', 'business.pos.report' => 'pos.reports',
            'business.payments' => 'payments.view', 'business.payments.store' => 'payments.create',
            'business.khata' => 'accounting.view', 'business.khata.journal.store' => 'accounting.create_journal',
            'business.deliveries', 'business.deliveries.show', 'business.deliveries.sheet' => 'deliveries.view',
            'business.deliveries.update', 'business.deliveries.start', 'business.deliveries.deliver', 'business.deliveries.fail', 'business.deliveries.reopen', 'business.deliveries.cancel' => 'deliveries.update_status',
            'business.invoices.index', 'business.invoices.show' => 'invoices.view', 'business.invoices.pdf', 'business.invoices.pdf.download' => 'invoices.export',
            'business.invoices.update', 'business.invoices.issue', 'business.invoices.reissue', 'business.invoices.credit-notes.store' => 'invoices.create', 'business.invoices.void' => 'invoices.void',
            'business.expenses.index' => 'expenses.view', 'business.expenses.store' => 'expenses.create', 'business.expenses.destroy' => 'expenses.delete',
            'business.reports' => 'reports.view', 'business.reports.pdf' => 'reports.export',
            'business.staff', 'business.staff.show' => 'staff.view', 'business.staff.store' => 'staff.create',
            'business.staff.edit', 'business.staff.update', 'business.staff.status', 'business.staff.archive', 'business.staff.restore', 'business.staff.reset-password', 'business.staff.destroy' => 'staff.edit',
            'business.settings' => 'settings.view', 'business.settings.business' => 'settings.update',
            default => null,
        };
    }
}
