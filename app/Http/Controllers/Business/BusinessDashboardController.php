<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\FinanceCalculator;

class BusinessDashboardController extends Controller
{
    public function __construct(private FinanceCalculator $finance) {}

    public function __invoke()
    {
        $businessId = auth()->user()->business_id;
        $revenue = Payment::where('business_id', $businessId)->sum('amount');
        $expenses = Expense::where('business_id', $businessId)->whereMonth('expense_date', now()->month)->whereYear('expense_date', now()->year)->sum('amount');

        return view('business.dashboard', [
            'revenue' => $revenue,
            'todayRevenue' => Payment::where('business_id', $businessId)->whereDate('payment_date', today())->sum('amount'),
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
