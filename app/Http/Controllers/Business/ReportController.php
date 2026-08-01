<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $filters = $this->resolveFilters($request);
        $validOrders = $this->orderQuery($businessId, $filters)->whereNotIn('status', ['Cancelled', 'Void']);
        $returns = $this->returnQuery($businessId, $filters);
        $expenses = $this->expenseQuery($businessId, $filters);
        $products = Product::query()
            ->where('business_id', $businessId)
            ->when($filters['product_id'], fn ($query) => $query->whereKey($filters['product_id']));
        $payables = Purchase::query()
            ->where('business_id', $businessId)
            ->where('balance', '>', 0)
            ->whereNotIn('status', ['Draft', 'Cancelled'])
            ->whereBetween('purchase_date', [$filters['from']->copy()->startOfDay(), $filters['to']->copy()->endOfDay()]);

        $grossSales = round((float) (clone $validOrders)->sum('subtotal'), 2);
        $salesDiscounts = round((float) (clone $validOrders)->sum('discount_amount'), 2);
        $salesReturns = round((float) (clone $returns)->sum('refund_amount'), 2);
        $netSales = round($grossSales - $salesDiscounts - $salesReturns, 2);
        $soldCost = round((float) $this->cogsQuery($businessId, $filters)->sum(DB::raw('order_items.quantity * COALESCE(order_items.purchase_cost_snapshot, 0)')), 2);
        $returnedCost = round((float) $this->returnedCogsQuery($businessId, $filters)->sum(DB::raw('sales_return_items.quantity * COALESCE(order_items.purchase_cost_snapshot, 0)')), 2);
        $cogs = round(max(0, $soldCost - $returnedCost), 2);
        $grossProfit = round($netSales - $cogs, 2);
        $operatingExpenses = round((float) (clone $expenses)->sum('amount'), 2);
        $netProfit = round($grossProfit - $operatingExpenses, 2);
        $revenueReceived = round((float) $this->paymentQuery($businessId, $filters)->sum('amount'), 2);
        $outstandingReceivables = round((float) (clone $validOrders)->sum('balance'), 2);
        $today = now(config('app.timezone'))->startOfDay();

        $lowStockProducts = (clone $products)->whereColumn('stock_quantity', '<=', 'low_stock_alert_qty')->orderBy('stock_quantity')->take(5)->get();
        $topCustomers = Customer::query()
            ->where('business_id', $businessId)
            ->where('current_balance', '>', 0)
            ->when($filters['customer_id'], fn ($query) => $query->whereKey($filters['customer_id']))
            ->orderByDesc('current_balance')
            ->take(5)
            ->get();
        $supplierBalances = Supplier::query()
            ->where('business_id', $businessId)
            ->withSum(['purchases as open_payable' => fn ($query) => $query->where('balance', '>', 0)->whereNotIn('status', ['Draft', 'Cancelled'])], 'balance')
            ->orderByDesc('open_payable')
            ->take(5)
            ->get()
            ->filter(fn ($supplier) => (float) $supplier->open_payable > 0);

        return view('business.reports.index', [
            'filters' => $filters,
            'exportFilters' => $this->exportFilters($filters),
            'grossSales' => $grossSales,
            'salesDiscounts' => $salesDiscounts,
            'salesReturns' => $salesReturns,
            'netSales' => $netSales,
            'revenueReceived' => $revenueReceived,
            'outstandingReceivables' => $outstandingReceivables,
            'completedOrders' => (clone $validOrders)->whereIn('status', ['Delivered', 'Completed'])->count(),
            'pendingOrders' => (clone $validOrders)->whereIn('status', ['New', 'Pending', 'Accepted', 'Packing', 'Ready'])->count(),
            'stockValue' => round((float) (clone $products)->get()->sum(fn (Product $product) => (float) $product->stock_quantity * (float) ($product->purchase_cost ?: $product->wholesale_price)), 2),
            'lowStockCount' => (clone $products)->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'low_stock_alert_qty')->count(),
            'outOfStockCount' => (clone $products)->where('stock_quantity', '<=', 0)->count(),
            'cogs' => $cogs,
            'grossProfit' => $grossProfit,
            'expenses' => $operatingExpenses,
            'netProfit' => $netProfit,
            'totalPayables' => round((float) (clone $payables)->sum('balance'), 2),
            'dueTodayPayables' => round((float) (clone $payables)->whereDate('due_date', $today)->sum('balance'), 2),
            'dueSoonPayables' => round((float) (clone $payables)->whereBetween('due_date', [$today->copy()->addDay(), $today->copy()->addDays(7)])->sum('balance'), 2),
            'overduePayables' => round((float) (clone $payables)->whereNotNull('due_date')->whereDate('due_date', '<', $today)->sum('balance'), 2),
            'lowStockProducts' => $lowStockProducts,
            'topCustomers' => $topCustomers,
            'highestSupplierBalances' => $supplierBalances,
            'oldestOutstandingPurchases' => (clone $payables)->with('supplier')->orderBy('due_date')->orderBy('purchase_date')->take(5)->get(),
            'chartSeries' => $this->chartSeries($businessId, $filters),
            'hasSalesData' => $grossSales > 0 || $salesReturns > 0,
            'customers' => Customer::where('business_id', $businessId)->orderBy('name')->get(),
            'products' => Product::where('business_id', $businessId)->orderBy('name')->get(),
        ]);
    }

    public function pdf(Request $request, string $type)
    {
        abort_unless(in_array($type, ['sales', 'inventory', 'expense', 'profit-loss'], true), 404);

        $businessId = (int) $request->user()->business_id;
        $filters = $this->resolveFilters($request);
        $orders = $this->orderQuery($businessId, $filters);
        $validOrders = (clone $orders)->whereNotIn('status', ['Cancelled', 'Void']);
        $expenses = $this->expenseQuery($businessId, $filters);

        $data = [
            'type' => $type,
            'business' => $request->user()->business?->load(['documentFooter', 'owner:id,email']),
            'orders' => (clone $orders)->with('customer')->latest()->get(),
            'summary' => [
                'subtotal' => (clone $validOrders)->sum('subtotal'),
                'discount_amount' => (clone $validOrders)->sum('discount_amount'),
                'grand_total' => (clone $validOrders)->sum('grand_total'),
                'paid_amount' => $this->paymentQuery($businessId, $filters)->sum('amount'),
                'balance' => (clone $validOrders)->sum('balance'),
            ],
            'products' => Product::where('business_id', $businessId)->when($filters['product_id'], fn ($query) => $query->whereKey($filters['product_id']))->get(),
            'customers' => Customer::where('business_id', $businessId)->when($filters['customer_id'], fn ($query) => $query->whereKey($filters['customer_id']))->get(),
            'expenses' => (clone $expenses)->latest()->get(),
        ];

        return Pdf::loadView('business.reports.pdf', $data)->stream('tradeflow-'.$type.'-report.pdf');
    }

    private function resolveFilters(Request $request): array
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(['today', 'this_week', 'this_month', 'this_year', 'custom'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['New', 'Pending', 'Accepted', 'Packing', 'Ready', 'Delivered', 'Completed', 'Partially Returned', 'Returned', 'Cancelled'])],
            'customer_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
        ]);

        $data = array_merge([
            'period' => 'this_month',
            'date_from' => null,
            'date_to' => null,
            'status' => null,
            'customer_id' => null,
            'product_id' => null,
        ], $data);

        $period = $data['period'];
        $now = now(config('app.timezone'));
        [$from, $to, $label] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Today'],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'This week'],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear(), 'This year'],
            'custom' => $this->customRange($data, $now),
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'This month'],
        };

        return $data + compact('period', 'from', 'to', 'label');
    }

    private function customRange(array $data, Carbon $now): array
    {
        if (empty($data['date_from']) || empty($data['date_to'])) {
            throw ValidationException::withMessages(['date_from' => 'Select both Date From and Date To for a custom range.']);
        }

        $from = Carbon::parse($data['date_from'], config('app.timezone'))->startOfDay();
        $to = Carbon::parse($data['date_to'], config('app.timezone'))->endOfDay();
        if ($to->lt($from)) {
            throw ValidationException::withMessages(['date_to' => 'Date To must be after or equal to Date From.']);
        }

        return [$from, $to, 'Custom range'];
    }

    private function orderQuery(int $businessId, array $filters)
    {
        return Order::query()
            ->where('business_id', $businessId)
            ->whereBetween('created_at', [$filters['from']->copy()->startOfDay(), $filters['to']->copy()->endOfDay()])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['product_id'] ?? null, fn ($query, $productId) => $query->whereHas('items', fn ($items) => $items->where('product_id', $productId)));
    }

    private function returnQuery(int $businessId, array $filters)
    {
        return SalesReturn::query()
            ->where('business_id', $businessId)
            ->whereBetween('returned_at', [$filters['from']->copy()->startOfDay(), $filters['to']->copy()->endOfDay()])
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['product_id'] ?? null, fn ($query, $productId) => $query->whereHas('items.orderItem', fn ($items) => $items->where('product_id', $productId)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->whereHas('order', fn ($order) => $order->where('status', $status)));
    }

    private function expenseQuery(int $businessId, array $filters)
    {
        return Expense::query()
            ->where('business_id', $businessId)
            ->whereBetween('expense_date', [$filters['from']->toDateString(), $filters['to']->toDateString()]);
    }

    private function paymentQuery(int $businessId, array $filters)
    {
        return Payment::query()
            ->where('business_id', $businessId)
            ->whereBetween('payment_date', [$filters['from']->toDateString(), $filters['to']->toDateString()])
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['product_id'] ?? null, fn ($query, $productId) => $query->whereHas('order.items', fn ($items) => $items->where('product_id', $productId)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->whereHas('order', fn ($order) => $order->where('status', $status)));
    }

    private function cogsQuery(int $businessId, array $filters)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $businessId)
            ->whereBetween('orders.created_at', [$filters['from']->copy()->startOfDay(), $filters['to']->copy()->endOfDay()])
            ->whereNotIn('orders.status', ['Cancelled', 'Void'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('orders.status', $status))
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('orders.customer_id', $customerId))
            ->when($filters['product_id'] ?? null, fn ($query, $productId) => $query->where('order_items.product_id', $productId));
    }

    private function returnedCogsQuery(int $businessId, array $filters)
    {
        return SalesReturnItem::query()
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->join('order_items', 'order_items.id', '=', 'sales_return_items.order_item_id')
            ->join('orders', 'orders.id', '=', 'sales_returns.order_id')
            ->where('sales_returns.business_id', $businessId)
            ->whereBetween('sales_returns.returned_at', [$filters['from']->copy()->startOfDay(), $filters['to']->copy()->endOfDay()])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('orders.status', $status))
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('sales_returns.customer_id', $customerId))
            ->when($filters['product_id'] ?? null, fn ($query, $productId) => $query->where('order_items.product_id', $productId));
    }

    private function chartSeries(int $businessId, array $filters): array
    {
        $byMonth = $filters['from']->diffInDays($filters['to']) > 45;
        $salesBucket = $byMonth ? "DATE_FORMAT(created_at, '%Y-%m')" : 'DATE(created_at)';
        $salesReturnBucket = $byMonth ? "DATE_FORMAT(returned_at, '%Y-%m')" : 'DATE(returned_at)';
        $orderBucket = $byMonth ? "DATE_FORMAT(orders.created_at, '%Y-%m')" : 'DATE(orders.created_at)';
        $returnBucket = $byMonth ? "DATE_FORMAT(sales_returns.returned_at, '%Y-%m')" : 'DATE(sales_returns.returned_at)';
        $expenseBucket = $byMonth ? "DATE_FORMAT(expense_date, '%Y-%m')" : 'DATE(expense_date)';

        $sales = $this->orderQuery($businessId, $filters)->whereNotIn('status', ['Cancelled', 'Void'])
            ->selectRaw("{$salesBucket} as bucket, SUM(subtotal) as gross, SUM(discount_amount) as discount")
            ->groupBy('bucket')->get();
        $returns = $this->returnQuery($businessId, $filters)->selectRaw("{$salesReturnBucket} as bucket, SUM(refund_amount) as amount")->groupBy('bucket')->get();
        $costs = $this->cogsQuery($businessId, $filters)->selectRaw("{$orderBucket} as bucket, SUM(order_items.quantity * COALESCE(order_items.purchase_cost_snapshot, 0)) as amount")->groupBy('bucket')->get();
        $returnedCosts = $this->returnedCogsQuery($businessId, $filters)->selectRaw("{$returnBucket} as bucket, SUM(sales_return_items.quantity * COALESCE(order_items.purchase_cost_snapshot, 0)) as amount")->groupBy('bucket')->get();
        $expenses = $this->expenseQuery($businessId, $filters)->selectRaw("{$expenseBucket} as bucket, SUM(amount) as amount")->groupBy('bucket')->get();

        $maps = [
            'sales' => $sales->pluck('gross', 'bucket')->all(),
            'discounts' => $sales->pluck('discount', 'bucket')->all(),
            'returns' => $returns->pluck('amount', 'bucket')->all(),
            'costs' => $costs->pluck('amount', 'bucket')->all(),
            'returnedCosts' => $returnedCosts->pluck('amount', 'bucket')->all(),
            'expenses' => $expenses->pluck('amount', 'bucket')->all(),
        ];
        $buckets = collect($maps)->flatMap(fn ($values) => array_keys($values))->unique()->sort()->values();

        return $buckets->map(function (string $bucket) use ($maps, $byMonth): array {
            $netSales = (float) ($maps['sales'][$bucket] ?? 0) - (float) ($maps['discounts'][$bucket] ?? 0) - (float) ($maps['returns'][$bucket] ?? 0);
            $cogs = (float) ($maps['costs'][$bucket] ?? 0) - (float) ($maps['returnedCosts'][$bucket] ?? 0);
            $grossProfit = $netSales - $cogs;

            return [
                'label' => $byMonth
                    ? Carbon::createFromFormat('Y-m', $bucket)->format('M Y')
                    : Carbon::parse($bucket, config('app.timezone'))->format('d M'),
                'net_sales' => round($netSales, 2),
                'expenses' => round((float) ($maps['expenses'][$bucket] ?? 0), 2),
                'gross_profit' => round($grossProfit, 2),
                'net_profit' => round($grossProfit - (float) ($maps['expenses'][$bucket] ?? 0), 2),
            ];
        })->all();
    }

    private function exportFilters(array $filters): array
    {
        return array_filter([
            'period' => $filters['period'],
            'date_from' => $filters['period'] === 'custom' ? $filters['from']->toDateString() : null,
            'date_to' => $filters['period'] === 'custom' ? $filters['to']->toDateString() : null,
            'status' => $filters['status'] ?? null,
            'customer_id' => $filters['customer_id'] ?? null,
            'product_id' => $filters['product_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
