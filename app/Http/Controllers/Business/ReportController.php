<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\FinanceCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(private FinanceCalculator $finance) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'status' => ['nullable', Rule::in(['New', 'Accepted', 'Packing', 'Ready', 'Delivered', 'Completed', 'Cancelled'])],
            'customer_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
        ]);
        if (!empty($filters['date_from']) && !empty($filters['date_to']) && $filters['date_to'] < $filters['date_from']) {
            return back()->withErrors(['date_to' => 'Date To must be after or equal to Date From.'])->withInput();
        }
        $businessId = auth()->user()->business_id;
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;
        $month = $filters['month'] ?? null;
        $year = $filters['year'] ?? null;
        $status = $filters['status'] ?? null;
        $customerId = $filters['customer_id'] ?? null;
        $productId = $filters['product_id'] ?? null;
        $ordersQuery = Order::where('business_id', $businessId)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($month, fn ($q) => $q->whereMonth('created_at', $month))
            ->when($year, fn ($q) => $q->whereYear('created_at', $year))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($productId, fn ($q) => $q->whereHas('items', fn ($items) => $items->where('product_id', $productId)));
        $expenseQuery = Expense::where('business_id', $businessId)
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
            ->when($month, fn ($q) => $q->whereMonth('expense_date', $month))
            ->when($year, fn ($q) => $q->whereYear('expense_date', $year));
        $productQuery = Product::where('business_id', $businessId)
            ->when($productId, fn ($q) => $q->where('id', $productId));
        $customerQuery = Customer::where('business_id', $businessId)
            ->when($customerId, fn ($q) => $q->where('id', $customerId));
        $validOrdersQuery = (clone $ordersQuery)->whereNotIn('status', ['Cancelled']);
        $sales = (clone $validOrdersQuery)->sum('grand_total');
        $subtotal = (clone $validOrdersQuery)->sum('subtotal');
        $discountAmount = (clone $validOrdersQuery)->sum('discount_amount');
        $revenue = Payment::where('business_id', $businessId)
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
            ->when($month, fn ($q) => $q->whereMonth('payment_date', $month))
            ->when($year, fn ($q) => $q->whereYear('payment_date', $year))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->sum('amount');
        $balance = (clone $validOrdersQuery)->sum('balance');
        $expenses = (clone $expenseQuery)->sum('amount');
        $filteredOrdersCount = (clone $ordersQuery)->count();
        $filteredExpensesCount = (clone $expenseQuery)->count();
        $filtersApplied = filled($from) || filled($to) || filled($month) || filled($year) || filled($status) || filled($customerId) || filled($productId);

        return view('business.reports.index', [
            'sales' => $sales,
            'subtotal' => $subtotal,
            'discountAmount' => $discountAmount,
            'revenue' => $revenue,
            'balance' => $balance,
            'todaySales' => Order::where('business_id', $businessId)->whereDate('created_at', today())->whereNotIn('status', ['Cancelled'])->sum('grand_total'),
            'monthlySales' => Order::where('business_id', $businessId)->whereMonth('created_at', $month ?: now()->month)->whereYear('created_at', $year ?: now()->year)->whereNotIn('status', ['Cancelled'])->sum('grand_total'),
            'orders' => $filteredOrdersCount,
            'completedOrders' => (clone $ordersQuery)->whereIn('status', ['Delivered', 'Completed'])->count(),
            'pendingOrders' => (clone $ordersQuery)->whereIn('status', ['New', 'Pending', 'Accepted', 'Packing', 'Ready'])->count(),
            'inventory' => (clone $productQuery)->sum('stock_quantity'),
            'stockValue' => (clone $productQuery)->get()->sum(fn ($p) => $p->stock_quantity * ($p->purchase_cost ?: $p->wholesale_price)),
            'lowStockProducts' => (clone $productQuery)->whereColumn('stock_quantity', '<=', 'low_stock_alert_qty')->get(),
            'credit' => $balance,
            'pendingPayments' => $balance,
            'topCustomers' => (clone $customerQuery)->orderByDesc('current_balance')->take(5)->get(),
            'blockedCustomers' => (clone $customerQuery)->where('status', 'Blocked')->count(),
            'expenses' => $expenses, 'profit' => $this->finance->calculateProfit($revenue, $expenses),
            'filtersApplied' => $filtersApplied,
            'filteredOrdersCount' => $filteredOrdersCount,
            'filteredExpensesCount' => $filteredExpensesCount,
            'customers' => Customer::where('business_id', $businessId)->orderBy('name')->get(),
            'products' => Product::where('business_id', $businessId)->orderBy('name')->get(),
        ]);
    }

    public function pdf(Request $request, string $type)
    {
        $businessId = auth()->user()->business_id;
        $from = $request->date_from;
        $to = $request->date_to;
        $orders = Order::with('customer')->where('business_id', $businessId)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($request->month, fn ($q) => $q->whereMonth('created_at', $request->month))
            ->when($request->year, fn ($q) => $q->whereYear('created_at', $request->year))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->product_id, fn ($q) => $q->whereHas('items', fn ($items) => $items->where('product_id', $request->product_id)));
        $validOrders = (clone $orders)->whereNotIn('status', ['Cancelled']);
        $expenses = Expense::where('business_id', $businessId)
            ->when($from, fn ($q) => $q->whereDate('expense_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('expense_date', '<=', $to))
            ->when($request->month, fn ($q) => $q->whereMonth('expense_date', $request->month))
            ->when($request->year, fn ($q) => $q->whereYear('expense_date', $request->year));

        $data = [
            'type' => $type,
            'business' => auth()->user()->business,
            'orders' => (clone $orders)->latest()->get(),
            'summary' => [
                'subtotal' => (clone $validOrders)->sum('subtotal'),
                'discount_amount' => (clone $validOrders)->sum('discount_amount'),
                'grand_total' => (clone $validOrders)->sum('grand_total'),
                'paid_amount' => Payment::where('business_id', $businessId)
                    ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
                    ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
                    ->when($request->month, fn ($q) => $q->whereMonth('payment_date', $request->month))
                    ->when($request->year, fn ($q) => $q->whereYear('payment_date', $request->year))
                    ->sum('amount'),
                'balance' => (clone $validOrders)->sum('balance'),
            ],
            'products' => Product::where('business_id', $businessId)->get(),
            'customers' => Customer::where('business_id', $businessId)->get(),
            'expenses' => (clone $expenses)->latest()->get(),
        ];

        return Pdf::loadView('business.reports.pdf', $data)->stream('tradeflow-'.$type.'-report.pdf');
    }
}
