<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\FinanceCalculator;
use App\Services\CompanyPermissionService;

class BusinessDashboardController extends Controller
{
    public function __construct(private FinanceCalculator $finance) {}

    public function __invoke(CompanyPermissionService $companyPermissions)
    {
        $businessId = auth()->user()->business_id;
        $hasOperationalAccess = collect(['products.view', 'inventory.view', 'customers.view', 'suppliers.view', 'orders.view', 'pos.view', 'payments.view', 'accounting.view', 'deliveries.view', 'invoices.view', 'expenses.view', 'reports.view', 'staff.view', 'settings.view'])
            ->contains(fn ($permission) => $companyPermissions->allowsUser(auth()->user(), $permission));

        if (!$hasOperationalAccess) {
            return view('business.dashboard', ['hasOperationalAccess' => false]);
        }
        $revenue = Payment::where('business_id', $businessId)->sum('amount');
        $expenses = Expense::where('business_id', $businessId)->whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount');
        $inventoryValue = Product::where('business_id', $businessId)->get()->sum(fn (Product $product) => (float) $product->stock_quantity * (float) ($product->purchase_cost ?: $product->wholesale_price));

        return view('business.dashboard', [
            'hasOperationalAccess' => true,
            'totalSales' => Order::where('business_id', $businessId)->whereNotIn('status', ['Cancelled'])->sum('grand_total'),
            'todaySales' => Order::where('business_id', $businessId)->whereDate('order_date', today())->whereNotIn('status', ['Cancelled'])->sum('grand_total'),
            'todayPosSales' => Order::where('business_id', $businessId)->where('sale_channel', 'pos')->whereDate('order_date', today())->whereNotIn('status', ['Cancelled', 'Void', 'Returned'])->sum('grand_total'),
            'revenue' => $revenue,
            'todayRevenue' => Payment::where('business_id', $businessId)->whereDate('payment_date', today())->sum('amount'),
            'receivables' => Order::where('business_id', $businessId)->whereNotIn('status', ['Cancelled'])->sum('balance'),
            'payables' => Supplier::where('business_id', $businessId)->sum('opening_balance'),
            'inventoryValue' => $inventoryValue,
            'suppliersCount' => Supplier::where('business_id', $businessId)->count(),
            'ordersCount' => Order::where('business_id', $businessId)->count(),
            'pendingOrders' => Order::where('business_id', $businessId)->whereIn('status', ['New', 'Pending', 'Accepted', 'Packing', 'Ready'])->count(),
            'completedOrders' => Order::where('business_id', $businessId)->whereIn('status', ['Completed', 'Delivered'])->count(),
            'customersCount' => Customer::where('business_id', $businessId)->count(),
            'productsCount' => Product::where('business_id', $businessId)->count(),
            'pendingPayments' => Order::where('business_id', $businessId)->whereNotIn('status', ['Cancelled'])->sum('balance'),
            'lowStock' => Product::where('business_id', $businessId)->whereColumn('stock_quantity', '<=', 'low_stock_alert_qty')->count(),
            'expenses' => $expenses,
            'profit' => $this->finance->calculateProfit($revenue, $expenses),
            'recentOrders' => Order::with('customer')->where('business_id', $businessId)->latest()->take(5)->get(),
            'lowStockProducts' => Product::where('business_id', $businessId)->whereColumn('stock_quantity', '<=', 'low_stock_alert_qty')->take(5)->get(),
        ]);
    }
}
