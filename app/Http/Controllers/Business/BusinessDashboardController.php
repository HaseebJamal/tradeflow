<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\CompanyPermissionService;
use Illuminate\Support\Facades\DB;

class BusinessDashboardController extends Controller
{
    public function __invoke(CompanyPermissionService $companyPermissions)
    {
        $businessId = auth()->user()->business_id;
        // Dashboard access is a core workspace capability. Operational cards
        // are shown only when at least one operational module is enabled.
        $hasOperationalAccess = collect([
            'products', 'inventory', 'suppliers', 'purchases', 'purchase_returns', 'customers',
            'sales', 'sales_returns', 'pos', 'deliveries', 'accounting',
            'expenses', 'reports', 'staff', 'audit_logs', 'settings',
        ])->contains(fn (string $module) => $companyPermissions->allowsUser(auth()->user(), $module.'.view'));

        if (!$hasOperationalAccess) {
            return view('business.dashboard', ['hasOperationalAccess' => false]);
        }
        $saleBase = Order::where('business_id', $businessId)->whereNotIn('status', ['Cancelled', 'Void', 'Returned']);
        $monthlySales = (clone $saleBase)->whereMonth('order_date', now()->month)->whereYear('order_date', now()->year);
        $expenses = Expense::where('business_id', $businessId)->whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount');
        $totalExpenses = Expense::where('business_id', $businessId)->sum('amount');
        $costOfSales = OrderItem::whereHas('order', fn ($query) => $query->where('business_id', $businessId)->whereNotIn('status', ['Cancelled', 'Void', 'Returned']))->sum(DB::raw('quantity * purchase_cost_snapshot'));
        $monthlyCostOfSales = OrderItem::whereHas('order', fn ($query) => $query->where('business_id', $businessId)->whereNotIn('status', ['Cancelled', 'Void', 'Returned'])->whereMonth('order_date', now()->month)->whereYear('order_date', now()->year))->sum(DB::raw('quantity * purchase_cost_snapshot'));
        $totalSales = (clone $saleBase)->sum('grand_total');
        $monthlySalesTotal = (clone $monthlySales)->sum('grand_total');
        $inventoryValue = Product::where('business_id', $businessId)->get()->sum(fn (Product $product) => (float) $product->stock_quantity * (float) ($product->purchase_cost ?: $product->wholesale_price));

        return view('business.dashboard', [
            'hasOperationalAccess' => true,
            'totalSales' => $totalSales,
            'todaySales' => (clone $saleBase)->whereDate('order_date', today())->sum('grand_total'),
            // Receivables is the single authoritative outstanding-customer metric.
            'receivables' => (clone $saleBase)->where('balance', '>', 0)->sum('balance'),
            'payables' => Purchase::where('business_id', $businessId)->sum('balance') + Supplier::where('business_id', $businessId)->sum('opening_balance'),
            'inventoryValue' => $inventoryValue,
            'suppliersCount' => Supplier::where('business_id', $businessId)->count(),
            'pendingDeliveries' => Delivery::where('business_id', $businessId)->whereIn('status', ['Pending', 'Assigned', 'Out For Delivery'])->count(),
            'customersCount' => Customer::where('business_id', $businessId)->count(),
            'productsCount' => Product::where('business_id', $businessId)->count(),
            'todayPurchases' => Purchase::where('business_id', $businessId)->whereDate('purchase_date', today())->sum('grand_total'),
            'lowStock' => Product::where('business_id', $businessId)->whereColumn('stock_quantity', '<=', 'low_stock_alert_qty')->count(),
            'expenses' => $expenses,
            'profit' => $totalSales - $costOfSales - $totalExpenses,
            'monthlyProfit' => $monthlySalesTotal - $monthlyCostOfSales - $expenses,
            'recentOrders' => Order::with('customer')->where('business_id', $businessId)->latest()->take(5)->get(),
            'lowStockProducts' => Product::where('business_id', $businessId)->whereColumn('stock_quantity', '<=', 'low_stock_alert_qty')->take(5)->get(),
        ]);
    }
}
