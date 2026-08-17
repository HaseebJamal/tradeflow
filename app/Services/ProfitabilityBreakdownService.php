<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProfitabilityBreakdownService
{
    /**
     * The canonical, period-aware profitability calculation shared by Reports,
     * the drill-down, and the profitability PDF. It mirrors the previous
     * Reports queries; it never writes a profit balance or ledger entry.
     */
    public function forPeriod(int $businessId, array $filters): array
    {
        $orders = $this->orders($businessId, $filters)->whereNotIn('status', ['Cancelled', 'Void']);
        $returns = $this->returns($businessId, $filters);
        $expenses = $this->expenses($businessId, $filters);
        $cogsLines = $this->cogsLines($businessId, $filters);
        $returnedCogsLines = $this->returnedCogsLines($businessId, $filters);

        $grossSales = round((float) (clone $orders)->sum('subtotal'), 2);
        $invoiceDiscounts = round((float) (clone $orders)->sum('discount_amount'), 2);
        $salesReturns = round((float) (clone $returns)->sum('refund_amount'), 2);
        $lineDiscounts = round((float) (clone $cogsLines)->sum('order_items.discount_amount'), 2);
        $soldCogs = round((float) (clone $cogsLines)->sum(DB::raw('order_items.quantity * COALESCE(order_items.purchase_cost_snapshot, 0)')), 2);
        $returnedCogs = round((float) (clone $returnedCogsLines)->sum(DB::raw('sales_return_items.quantity * COALESCE(order_items.purchase_cost_snapshot, 0)')), 2);

        return self::calculate(
            $grossSales,
            $invoiceDiscounts,
            $salesReturns,
            $soldCogs,
            $returnedCogs,
            round((float) (clone $expenses)->sum('amount'), 2),
        ) + [
            // Saved line totals already include these discounts, so this is an
            // explanatory value only and is not deducted a second time.
            'line_discounts_included' => $lineDiscounts,
            'expense_categories' => (clone $expenses)
                ->selectRaw("COALESCE(NULLIF(TRIM(category), ''), 'Uncategorised') AS category, SUM(amount) AS amount")
                ->groupBy('category')->orderByDesc('amount')->get(),
            'expense_details' => (clone $expenses)->orderByDesc('expense_date')->orderByDesc('id')->limit(25)->get(),
            'top_cogs_products' => $this->topCogsProducts($businessId, $cogsLines, $returnedCogsLines),
            'sales_transactions' => (clone $orders)->with('customer:id,name,business_name')->latest('created_at')->limit(10)->get(),
            'return_transactions' => (clone $returns)->with('customer:id,name,business_name')->latest('returned_at')->limit(10)->get(),
        ];
    }

    public static function calculate(float $grossSales, float $invoiceDiscounts, float $salesReturns, float $soldCogs, float $returnedCogs, float $expenses): array
    {
        $netSales = round($grossSales - $invoiceDiscounts - $salesReturns, 2);
        $cogs = round(max(0, $soldCogs - $returnedCogs), 2);
        $grossProfit = round($netSales - $cogs, 2);

        return [
            'gross_sales' => round($grossSales, 2),
            'invoice_discounts' => round($invoiceDiscounts, 2),
            'sales_returns' => round($salesReturns, 2),
            'net_sales' => $netSales,
            'sold_cogs' => round($soldCogs, 2),
            'returned_cogs' => round($returnedCogs, 2),
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : null,
            'expenses' => round($expenses, 2),
            'net_profit' => round($grossProfit - $expenses, 2),
        ];
    }

    private function orders(int $businessId, array $filters): Builder
    {
        return Order::query()->where('business_id', $businessId)
            ->whereBetween('created_at', [$filters['from']->copy()->startOfDay(), $filters['to']->copy()->endOfDay()])
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['product_id'] ?? null, fn (Builder $query, $productId) => $query->whereHas('items', fn (Builder $items) => $items->where('product_id', $productId)));
    }

    private function returns(int $businessId, array $filters): Builder
    {
        return SalesReturn::query()->where('business_id', $businessId)
            ->whereBetween('returned_at', [$filters['from']->copy()->startOfDay(), $filters['to']->copy()->endOfDay()])
            ->when($filters['customer_id'] ?? null, fn (Builder $query, $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['product_id'] ?? null, fn (Builder $query, $productId) => $query->whereHas('items.orderItem', fn (Builder $items) => $items->where('product_id', $productId)))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->whereHas('order', fn (Builder $order) => $order->where('status', $status)));
    }

    private function expenses(int $businessId, array $filters): Builder
    {
        return Expense::query()->where('business_id', $businessId)
            ->whereBetween('expense_date', [$filters['from']->toDateString(), $filters['to']->toDateString()]);
    }

    private function cogsLines(int $businessId, array $filters): Builder
    {
        return OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $businessId)
            ->whereBetween('orders.created_at', [$filters['from']->copy()->startOfDay(), $filters['to']->copy()->endOfDay()])
            ->whereNotIn('orders.status', ['Cancelled', 'Void'])
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('orders.status', $status))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, $customerId) => $query->where('orders.customer_id', $customerId))
            ->when($filters['product_id'] ?? null, fn (Builder $query, $productId) => $query->where('order_items.product_id', $productId));
    }

    private function returnedCogsLines(int $businessId, array $filters): Builder
    {
        return SalesReturnItem::query()->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->join('order_items', 'order_items.id', '=', 'sales_return_items.order_item_id')
            ->join('orders', 'orders.id', '=', 'sales_returns.order_id')
            ->where('sales_returns.business_id', $businessId)
            ->whereBetween('sales_returns.returned_at', [$filters['from']->copy()->startOfDay(), $filters['to']->copy()->endOfDay()])
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('orders.status', $status))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, $customerId) => $query->where('sales_returns.customer_id', $customerId))
            ->when($filters['product_id'] ?? null, fn (Builder $query, $productId) => $query->where('order_items.product_id', $productId));
    }

    private function topCogsProducts(int $businessId, Builder $sold, Builder $returned): \Illuminate\Support\Collection
    {
        $soldByProduct = (clone $sold)->selectRaw('order_items.product_id, SUM(order_items.quantity) AS quantity, SUM(order_items.quantity * COALESCE(order_items.purchase_cost_snapshot, 0)) AS sold_cogs')->groupBy('order_items.product_id')->get()->keyBy('product_id');
        $returnedByProduct = (clone $returned)->selectRaw('order_items.product_id, SUM(sales_return_items.quantity * COALESCE(order_items.purchase_cost_snapshot, 0)) AS returned_cogs')->groupBy('order_items.product_id')->get()->keyBy('product_id');
        $products = Product::where('business_id', $businessId)->whereIn('id', $soldByProduct->keys()->merge($returnedByProduct->keys())->unique())->get(['id', 'name'])->keyBy('id');
        $totalCogs = max(0.01, (float) $soldByProduct->sum('sold_cogs') - (float) $returnedByProduct->sum('returned_cogs'));

        return $soldByProduct->keys()->merge($returnedByProduct->keys())->unique()->map(function ($productId) use ($soldByProduct, $returnedByProduct, $products, $totalCogs): object {
            $cogs = round((float) ($soldByProduct->get($productId)?->sold_cogs ?? 0) - (float) ($returnedByProduct->get($productId)?->returned_cogs ?? 0), 2);
            return (object) ['product_id' => $productId, 'name' => $products->get($productId)?->name ?? 'Removed product', 'quantity' => (float) ($soldByProduct->get($productId)?->quantity ?? 0), 'cogs' => $cogs, 'share' => round(($cogs / $totalCogs) * 100, 2)];
        })->sortByDesc('cogs')->take(10)->values();
    }
}
