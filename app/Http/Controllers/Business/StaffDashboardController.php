<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Services\CompanyPermissionService;

class StaffDashboardController extends Controller
{
    public function __invoke(CompanyPermissionService $companyPermissions)
    {
        $user = auth()->user()->load('staffProfile');
        $modules = collect([
            ['products', 'Products', 'bi-box', route('business.products.index'), 'Manage assigned product catalog work.'],
            ['inventory', 'Inventory', 'bi-clipboard-data', route('business.inventory'), 'Track stock and inventory movements.'],
            ['customers', 'Customers', 'bi-person-lines-fill', route('business.customers.index'), 'View and manage customer records.'],
            ['suppliers', 'Suppliers', 'bi-building-add', route('business.suppliers.index'), 'Manage assigned supplier records.'],
            ['purchases', 'Purchases', 'bi-cart-plus', route('business.purchases.index'), 'Receive stock, supplier invoices, payments, and returns.'],
            ['purchase_returns', 'Purchase Returns', 'bi-arrow-return-left', route('business.purchase-returns.index'), 'Review and process permitted purchase returns.'],
            ['sales', 'Sales', 'bi-bag-check', route('business.sales.index'), 'Create sales, manage invoices, and record customer payments.'],
            ['sales_returns', 'Sales Returns', 'bi-arrow-return-right', route('business.sales.returns.index'), 'Review and process permitted sales returns.'],
            ['pos', 'Point of Sale', 'bi-upc-scan', route('business.pos.index'), 'Process counter sales and print receipts.'],
            ['accounting', 'Accounting / Ledger', 'bi-journal-text', route('business.khata'), 'Review customer and business ledger work.'],
            ['deliveries', 'Deliveries', 'bi-truck', route('business.deliveries'), 'View and update deliveries.'],
            ['expenses', 'Expenses', 'bi-receipt-cutoff', route('business.expenses.index'), 'Record business expenses.'],
            ['reports', 'Reports', 'bi-graph-up', route('business.reports'), 'View assigned business reports.'],
            ['staff', 'Roles & Users', 'bi-person-badge', route('business.staff'), 'Manage business users, roles, and assignments.'],
            ['audit_logs', 'Audit Logs', 'bi-activity', route('business.audit-logs.index'), 'Review activity across the business workspace.'],
            ['settings', 'Settings', 'bi-gear', route('business.settings'), 'Manage business configuration.'],
        ])->filter(function ($module) use ($companyPermissions, $user) {
            $moduleKey = $module[0];
            return $companyPermissions->allowsUser($user, $moduleKey.'.view');
        })->values();

        $deliveryQuery = Delivery::with('order.customer')->where('business_id', $user->business_id)->where('delivery_staff_id', $user->id);

        return view('business.staff-dashboard', [
            'user' => $user,
            'modules' => $modules,
            'deliveryStats' => $companyPermissions->allowsUser($user, 'deliveries.view') ? [
                'today' => (clone $deliveryQuery)->whereDate('created_at', today())->count(),
                'pending' => (clone $deliveryQuery)->where('status', 'Pending')->count(),
                'out' => (clone $deliveryQuery)->where('status', 'Out For Delivery')->count(),
                'delivered' => (clone $deliveryQuery)->where('status', 'Delivered')->count(),
                'failed' => (clone $deliveryQuery)->where('status', 'Failed')->count(),
                'cash_to_collect' => (clone $deliveryQuery)->whereIn('status', ['Pending', 'Out For Delivery'])->sum('amount'),
            ] : null,
            'assignedDeliveries' => $companyPermissions->allowsUser($user, 'deliveries.view') ? $deliveryQuery->latest()->take(8)->get() : collect(),
        ]);
    }
}
