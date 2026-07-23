<?php

namespace App\Http\Middleware;

use App\Services\CompanyPermissionService;
use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BusinessActionPermissionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route()?->getName();
        $permission = $this->permissionFor($route);
        if (!$permission) {
            return $next($request);
        }

        $user = $request->user();
        $companyPermissions = app(CompanyPermissionService::class);
        foreach (array_unique([$permission, ...$this->requiredPermissionsFor($route)]) as $requiredPermission) {
            if (!$user || !$companyPermissions->allows($user, $requiredPermission)) {
                return $this->deny($request, 'This feature is not enabled for your company.');
            }

            if (!$companyPermissions->allowsUser($user, $requiredPermission)) {
                return $this->deny($request, 'You do not have permission to access this module. Please contact your business owner.');
            }
        }

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->user()?->business_id) {
            AuditLog::create([
                'business_id' => $request->user()->business_id,
                'user_id' => $request->user()->id,
                'user_name' => $request->user()->name,
                'role' => $request->user()->role,
                'module' => 'Security',
                'action' => 'unauthorized_access',
                'description' => $message,
                'route' => $request->route()?->getName(),
                'occurred_at' => now(),
            ]);
        }

        if ($request->expectsJson()) {
            abort(403, $message);
        }

        // Never redirect a denied request to its referrer or to the first
        // enabled route: either can be the same protected route and create a
        // browser redirect loop. The basic business dashboard is deliberately
        // unguarded by business.permission and is safe for every approved user.
        return redirect()->route('business.dashboard')->withErrors(['permission' => $message]);
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
            'business.categories.index', 'business.categories.show' => 'categories.view',
            'business.categories.create', 'business.categories.store' => 'categories.create',
            'business.categories.edit', 'business.categories.update' => 'categories.edit',
            'business.categories.status' => 'categories.status',
            'business.categories.archive', 'business.categories.restore' => 'categories.archive',
            'business.categories.destroy' => 'categories.delete',
            'business.units.index', 'business.units.show' => 'units.view',
            'business.units.create', 'business.units.store' => 'units.create',
            'business.units.edit', 'business.units.update' => 'units.edit',
            'business.units.status' => 'units.status',
            'business.units.archive', 'business.units.restore' => 'units.archive',
            'business.units.destroy' => 'units.delete',
            'business.inventory' => 'inventory.view', 'business.inventory.adjust' => 'inventory.adjust_stock', 'business.inventory.transfer' => 'inventory.stock_transfer', 'business.inventory.alert' => 'inventory.low_stock_alerts',
            'business.customers.index', 'business.customers.show', 'business.customers.statement' => 'customers.view',
            'business.customers.store' => 'customers.create', 'business.customers.update', 'business.customers.status' => 'customers.edit',
            'business.customers.archive' => 'customers.archive', 'business.customers.restore' => 'customers.restore',
            'business.suppliers.index', 'business.suppliers.show' => 'suppliers.view',
            'business.suppliers.create', 'business.suppliers.store' => 'suppliers.create', 'business.suppliers.edit', 'business.suppliers.update' => 'suppliers.edit', 'business.suppliers.archive', 'business.suppliers.restore', 'business.suppliers.destroy' => 'suppliers.archive',
            'business.purchases.index', 'business.purchases.show' => 'purchases.view', 'business.purchases.create', 'business.purchases.store' => 'purchases.create',
            'business.purchases.receive' => 'purchases.receive', 'business.purchases.pay' => 'purchases.pay', 'business.purchases.return' => 'purchase_returns.process',
            'business.purchase-returns.index', 'business.purchase-returns.create', 'business.purchase-returns.show', 'business.purchase-returns.edit' => 'purchase_returns.view',
            'business.purchase-returns.start' => 'purchase_returns.process',
            'business.sales.quotations.index', 'business.sales.quotations.create', 'business.sales.quotations.store' => 'sales.quotations',
            'business.sales.index', 'business.sales.show', 'business.orders.index', 'business.orders.show' => 'sales.view',
            // Legacy creation URLs safely return visitors to the Sales listing.
            'business.sales.create', 'business.sales.store', 'business.orders.create', 'business.orders.store' => 'sales.view',
            'business.sales.edit', 'business.sales.update', 'business.orders.edit', 'business.orders.update' => 'sales.edit',
            'business.sales.status', 'business.orders.status' => 'sales.update_status',
            'business.sales.cancel', 'business.sales.destroy', 'business.sales.void', 'business.orders.cancel', 'business.orders.destroy', 'business.orders.void' => 'sales.update_status',
            'business.sales.returns.index', 'business.sales.returns.create', 'business.sales.returns.show', 'business.sales.returns.edit' => 'sales_returns.view',
            'business.sales.returns.start', 'business.sales.returns.process', 'business.sales.returns.store' => 'sales_returns.process',
            'business.pos.index', 'business.pos.products', 'business.pos.barcode' => 'pos.view',
            'business.pos.register.open' => 'pos.open_register',
            'business.pos.register.close' => 'pos.close_register',
            'business.pos.sales.store' => 'pos.create_sale',
            'business.pos.hold' => 'pos.hold_sale', 'business.pos.resume' => 'pos.resume_sale',
            'business.pos.history' => 'pos.view_history', 'business.pos.receipt', 'business.pos.receipt.pdf' => 'pos.print_receipt',
            'business.sales.payments.index', 'business.payments' => 'sales.payments', 'business.sales.payments.store', 'business.payments.store' => 'sales.payments',
            'business.khata' => 'accounting.view', 'business.khata.journal.store' => 'accounting.create_journal',
            'business.pos.delivery.assign' => 'deliveries.assign',
            'business.deliveries', 'business.deliveries.show', 'business.deliveries.sheet' => 'deliveries.view',
            'business.deliveries.update', 'business.deliveries.reopen', 'business.deliveries.cancel' => 'deliveries.edit',
            'business.deliveries.start', 'business.deliveries.deliver', 'business.deliveries.fail' => 'deliveries.update_status',
            'business.deliveries.proof' => 'deliveries.upload_proof',
            'business.deliveries.collection' => 'deliveries.record_collection',
            'business.sales.invoices.index', 'business.sales.invoices.show', 'business.invoices.index', 'business.invoices.show' => 'sales.invoices',
            'business.sales.invoices.pdf', 'business.sales.invoices.pdf.download', 'business.invoices.pdf', 'business.invoices.pdf.download' => 'sales.invoice_export',
            'business.sales.invoices.update', 'business.sales.invoices.issue', 'business.sales.invoices.reissue', 'business.sales.invoices.credit-notes.store', 'business.invoices.update', 'business.invoices.issue', 'business.invoices.reissue', 'business.invoices.credit-notes.store' => 'sales.invoices',
            'business.sales.invoices.void', 'business.invoices.void' => 'sales.invoices',
            'business.expenses.index' => 'expenses.view', 'business.expenses.store' => 'expenses.create', 'business.expenses.destroy' => 'expenses.delete',
            'business.reports' => 'reports.view', 'business.reports.pdf' => 'reports.export',
            'business.audit-logs.index', 'business.audit-logs.live' => 'audit_logs.view',
            'business.audit-logs.export.csv', 'business.audit-logs.export.pdf' => 'audit_logs.export',
            'business.staff', 'business.staff.show' => 'staff.view', 'business.staff.store' => 'staff.create',
            'business.staff.edit', 'business.staff.update', 'business.staff.status', 'business.staff.archive', 'business.staff.restore', 'business.staff.reset-password', 'business.staff.destroy' => 'staff.edit',
            'business.settings' => 'settings.view',
            'business.settings.business' => 'settings.update',
            'business.settings.logo' => 'settings.update',
            default => null,
        };
    }

    /** @return array<int, string> */
    private function requiredPermissionsFor(?string $route): array
    {
        $deliveryActions = [
            'business.pos.delivery.assign',
            'business.deliveries.update',
            'business.deliveries.start',
            'business.deliveries.deliver',
            'business.deliveries.fail',
            'business.deliveries.reopen',
            'business.deliveries.cancel',
            'business.deliveries.proof',
            'business.deliveries.collection',
        ];

        $required = in_array($route, $deliveryActions, true) ? ['deliveries.view'] : [];

        if ($route === 'business.deliveries.deliver') {
            $required[] = 'deliveries.upload_proof';
        }

        return $required;
    }
}
