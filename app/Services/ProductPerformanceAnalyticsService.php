<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesReturnItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProductPerformanceAnalyticsService
{
    public const VALID_SALE_STATUSES = ['Delivered', 'Completed', 'Partially Returned'];

    public function __construct(private StockMovementAnalyticsService $stockMovement) {}

    /** @return Collection<int, object> */
    public function report(int $businessId, Carbon $from, Carbon $to): Collection
    {
        $sales = $this->salesByProduct($businessId, $from, $to);
        $returns = $this->returnsByProduct($businessId, $from, $to);
        $productIds = $sales->keys()->merge($returns->keys())->unique()->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->where('business_id', $businessId)
            ->whereIn('id', $productIds)
            ->with(['category:id,name', 'unitRecord:id,unit_name,short_code'])
            ->get()
            ->keyBy('id');
        $movement = $this->stockMovement
            ->report($businessId, $from, $to, 90)
            ->keyBy('product_id');

        return $productIds->map(function ($productId) use ($products, $sales, $returns, $movement): ?object {
            /** Historical lines without a surviving tenant product cannot be attributed safely. */
            $product = $products->get($productId);
            if (! $product) {
                return null;
            }

            $sale = $sales->get($productId, (object) ['quantity' => 0, 'revenue' => 0, 'invoice_discount' => 0, 'cogs' => 0]);
            $return = $returns->get($productId, (object) ['quantity' => 0, 'value' => 0, 'cogs' => 0, 'reasons' => collect()]);
            $metrics = self::calculateMetrics(
                (float) $sale->quantity,
                (float) $sale->revenue,
                (float) $sale->invoice_discount,
                (float) $sale->cogs,
                (float) $return->quantity,
                (float) $return->value,
                (float) $return->cogs,
            );
            $margin = $metrics['gross_margin'];
            $status = $metrics['gross_profit'] < 0
                ? 'Loss-Making'
                : ($margin !== null && $margin <= 10 ? 'Low Margin' : 'Healthy');
            $stock = $movement->get($productId);

            return (object) array_merge($metrics, [
                'product' => $product,
                'product_id' => $product->id,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'category' => $product->category?->name ?? '—',
                'category_id' => $product->category_id,
                'unit' => $product->unitRecord?->short_code ?: ($product->unitRecord?->unit_name ?: ($product->unit ?: '—')),
                'unit_id' => $product->unit_id,
                'retail_price' => (float) $product->retail_price,
                'wholesale_price' => (float) $product->wholesale_price,
                'return_reasons' => $return->reasons,
                'top_return_reason' => $return->reasons->first(),
                'status' => $status,
                'movement_status' => $stock?->movement_status,
                'suggested_quantity' => (float) ($stock?->suggested_quantity ?? 0),
            ]);
        })->filter()->values();
    }

    /**
     * Calculates product values from the same historical inputs used by the
     * financial reports. Line totals already reflect line discounts and price
     * overrides; only the stored order discount is allocated proportionally.
     */
    public static function calculateMetrics(
        float $soldQuantity,
        float $lineRevenue,
        float $allocatedOrderDiscount,
        float $soldCogs,
        float $returnedQuantity,
        float $returnValue,
        float $returnedCogs,
    ): array {
        $netSales = round($lineRevenue - $allocatedOrderDiscount - $returnValue, 2);
        $cogs = round($soldCogs - $returnedCogs, 2);
        $grossProfit = round($netSales - $cogs, 2);

        return [
            'qty_sold' => round($soldQuantity, 3),
            'qty_returned' => round($returnedQuantity, 3),
            'net_qty_sold' => round($soldQuantity - $returnedQuantity, 3),
            'gross_revenue' => round($lineRevenue, 2),
            'invoice_discount' => round($allocatedOrderDiscount, 2),
            'net_sales' => $netSales,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : null,
            'return_value' => round($returnValue, 2),
            // Gross sold quantity is deliberately the denominator. A return
            // rate must not become inflated by subtracting returns first.
            'return_rate' => $soldQuantity > 0 ? round(($returnedQuantity / $soldQuantity) * 100, 2) : null,
        ];
    }

    /** @return Collection<int, object> */
    private function salesByProduct(int $businessId, Carbon $from, Carbon $to): Collection
    {
        $lines = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $businessId)
            ->whereIn('orders.status', self::VALID_SALE_STATUSES)
            ->whereBetween('orders.order_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('orders.id AS order_id, orders.subtotal AS order_subtotal, orders.discount_amount AS order_discount, order_items.product_id, SUM(order_items.quantity) AS quantity, SUM(COALESCE(order_items.line_total, order_items.total, 0)) AS line_revenue, SUM(order_items.quantity * COALESCE(order_items.purchase_cost_snapshot, 0)) AS cogs')
            ->groupBy('orders.id', 'orders.subtotal', 'orders.discount_amount', 'order_items.product_id')
            ->get();

        return $lines->groupBy('order_id')->flatMap(function (Collection $orderLines): Collection {
            $subtotal = max(0, (float) $orderLines->first()->order_subtotal);
            $discount = max(0, (float) $orderLines->first()->order_discount);
            $allocated = 0.0;

            return $orderLines->sortBy('product_id')->values()->map(function (object $line, int $index) use ($subtotal, $discount, &$allocated, $orderLines): object {
                $isLast = $index === $orderLines->count() - 1;
                $lineDiscount = $isLast
                    ? round(max(0, $discount - $allocated), 2)
                    : round($subtotal > 0 ? $discount * ((float) $line->line_revenue / $subtotal) : 0, 2);
                $allocated += $lineDiscount;
                $line->invoice_discount = $lineDiscount;

                return $line;
            });
        })->groupBy('product_id')->map(function (Collection $productLines): object {
            return (object) [
                'quantity' => (float) $productLines->sum('quantity'),
                'revenue' => round((float) $productLines->sum('line_revenue'), 2),
                'invoice_discount' => round((float) $productLines->sum('invoice_discount'), 2),
                'cogs' => round((float) $productLines->sum('cogs'), 2),
            ];
        });
    }

    /** @return Collection<int, object> */
    private function returnsByProduct(int $businessId, Carbon $from, Carbon $to): Collection
    {
        $lines = SalesReturnItem::query()
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->join('order_items', 'order_items.id', '=', 'sales_return_items.order_item_id')
            ->where('sales_returns.business_id', $businessId)
            ->whereBetween('sales_returns.returned_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw("order_items.product_id, COALESCE(NULLIF(TRIM(sales_returns.reason), ''), 'Not specified') AS reason, SUM(sales_return_items.quantity) AS quantity, SUM(COALESCE(sales_return_items.refund_total, 0)) AS value, SUM(sales_return_items.quantity * COALESCE(order_items.purchase_cost_snapshot, 0)) AS cogs")
            ->groupBy('order_items.product_id', 'sales_returns.reason')
            ->get();

        return $lines->groupBy('product_id')->map(function (Collection $productReturns): object {
            $reasons = $productReturns->sortByDesc('quantity')->values()->map(fn (object $line) => (object) [
                'reason' => $line->reason,
                'quantity' => (float) $line->quantity,
            ]);

            return (object) [
                'quantity' => (float) $productReturns->sum('quantity'),
                'value' => round((float) $productReturns->sum('value'), 2),
                'cogs' => round((float) $productReturns->sum('cogs'), 2),
                'reasons' => $reasons,
            ];
        });
    }
}
