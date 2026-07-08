<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Delivery;

class StaffDashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $permissions = collect($user->permissions ?? [])->map(fn ($value) => strtolower($value))->all();

        $modules = collect([
            ['products', 'Products', 'bi-box', route('business.products.index'), 'Manage assigned product catalog work.'],
            ['inventory', 'Inventory', 'bi-clipboard-data', route('business.inventory'), 'Track stock and inventory movements.'],
            ['customers', 'Customers', 'bi-person-lines-fill', route('business.customers.index'), 'View and manage customer records.'],
            ['orders', 'Orders', 'bi-bag-check', route('business.orders.index'), 'Create and process customer orders.'],
            ['payments', 'Payments', 'bi-cash-stack', route('business.payments'), 'Record manual customer payments.'],
            ['khata', 'Khata', 'bi-journal-text', route('business.khata'), 'Review customer credit ledger.'],
            ['deliveries', 'Deliveries', 'bi-truck', route('business.deliveries'), 'View and update deliveries.'],
            ['invoices', 'Invoices', 'bi-file-earmark-text', route('business.invoices.index'), 'View and print invoices.'],
            ['expenses', 'Expenses', 'bi-receipt-cutoff', route('business.expenses.index'), 'Record business expenses.'],
            ['reports', 'Reports', 'bi-graph-up', route('business.reports'), 'View assigned business reports.'],
        ])->filter(fn ($module) => (
            in_array($module[0], $permissions, true)
            || collect($permissions)->contains(fn ($value) => str_starts_with($value, $module[0].'.'))
        ))->values();

        $deliveryQuery = Delivery::with('order.customer')->where('delivery_staff_id', $user->id);

        return view('business.staff-dashboard', [
            'user' => $user,
            'modules' => $modules,
            'deliveryStats' => $user->role === 'delivery_staff' ? [
                'today' => (clone $deliveryQuery)->whereDate('created_at', today())->count(),
                'pending' => (clone $deliveryQuery)->where('status', 'Pending')->count(),
                'out' => (clone $deliveryQuery)->where('status', 'Out For Delivery')->count(),
                'delivered' => (clone $deliveryQuery)->where('status', 'Delivered')->count(),
                'failed' => (clone $deliveryQuery)->where('status', 'Failed')->count(),
                'cash_to_collect' => (clone $deliveryQuery)->whereIn('status', ['Pending', 'Out For Delivery'])->sum('amount'),
            ] : null,
            'assignedDeliveries' => $user->role === 'delivery_staff' ? $deliveryQuery->latest()->take(8)->get() : collect(),
        ]);
    }
}
