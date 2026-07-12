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
            ['orders', 'Orders', 'bi-bag-check', route('business.orders.index'), 'Create and process customer orders.'],
            ['pos', 'Point of Sale', 'bi-upc-scan', route('business.pos.index'), 'Process counter sales and print receipts.'],
            ['payments', 'Payments', 'bi-cash-stack', route('business.payments'), 'Record manual customer payments.'],
            ['accounting', 'Accounting / Ledger', 'bi-journal-text', route('business.khata'), 'Review customer and business ledger work.'],
            ['deliveries', 'Deliveries', 'bi-truck', route('business.deliveries'), 'View and update deliveries.'],
            ['invoices', 'Invoices', 'bi-file-earmark-text', route('business.invoices.index'), 'View and print invoices.'],
            ['expenses', 'Expenses', 'bi-receipt-cutoff', route('business.expenses.index'), 'Record business expenses.'],
            ['reports', 'Reports', 'bi-graph-up', route('business.reports'), 'View assigned business reports.'],
        ])->filter(function ($module) use ($companyPermissions, $user) {
            $moduleKey = $module[0];
            return $companyPermissions->allowsUser($user, $moduleKey.'.view');
        })->values();

        $deliveryQuery = Delivery::with('order.customer')->where('business_id', $user->business_id)->where('delivery_staff_id', $user->id);

        return view('business.staff-dashboard', [
            'user' => $user,
            'modules' => $modules,
            'deliveryStats' => $user->role === 'delivery_staff' && $companyPermissions->allowsUser($user, 'deliveries.view') ? [
                'today' => (clone $deliveryQuery)->whereDate('created_at', today())->count(),
                'pending' => (clone $deliveryQuery)->where('status', 'Pending')->count(),
                'out' => (clone $deliveryQuery)->where('status', 'Out For Delivery')->count(),
                'delivered' => (clone $deliveryQuery)->where('status', 'Delivered')->count(),
                'failed' => (clone $deliveryQuery)->where('status', 'Failed')->count(),
                'cash_to_collect' => (clone $deliveryQuery)->whereIn('status', ['Pending', 'Out For Delivery'])->sum('amount'),
            ] : null,
            'assignedDeliveries' => $user->role === 'delivery_staff' && $companyPermissions->allowsUser($user, 'deliveries.view') ? $deliveryQuery->latest()->take(8)->get() : collect(),
        ]);
    }
}
