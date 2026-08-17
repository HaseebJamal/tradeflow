<?php

namespace App\Services;

use App\Models\GoodsReceiptItem;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\SalesReturnItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StockMovementAnalyticsService
{
    public const COMPLETED_SALE_STATUSES = ['Delivered', 'Completed', 'Partially Returned'];

    public function __construct(private ReorderSuggestionService $reorderSuggestions) {}

    /** @return Collection<int, object> */
    public function report(int $businessId, Carbon $from, Carbon $to, int $deadThreshold): Collection
    {
        $today = now(config('app.timezone'))->startOfDay();
        $periodSales = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $businessId)
            ->whereIn('orders.status', self::COMPLETED_SALE_STATUSES)
            ->whereBetween('orders.order_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('order_items.product_id, SUM(order_items.quantity) AS quantity, COUNT(DISTINCT orders.id) AS sales_count, SUM(COALESCE(order_items.line_total, order_items.total, 0)) AS revenue')
            ->groupBy('order_items.product_id')->get()->keyBy('product_id');
        $periodReturns = SalesReturnItem::query()
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
            ->join('order_items', 'order_items.id', '=', 'sales_return_items.order_item_id')
            ->where('sales_returns.business_id', $businessId)
            ->whereBetween('sales_returns.returned_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('order_items.product_id, SUM(sales_return_items.quantity) AS quantity, SUM(COALESCE(sales_return_items.refund_total, 0)) AS refund_total')
            ->groupBy('order_items.product_id')->get()->keyBy('product_id');
        $lastSales = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.business_id', $businessId)
            ->whereIn('orders.status', self::COMPLETED_SALE_STATUSES)
            ->where('orders.order_date', '<=', $to->copy()->endOfDay())
            ->selectRaw('order_items.product_id, MAX(orders.order_date) AS last_sale_at')
            ->groupBy('order_items.product_id')->pluck('last_sale_at', 'order_items.product_id');
        $stockAges = $this->stockAgeMap($businessId);
        $sellableBatches = ProductBatch::query()
            ->selectRaw('product_id, SUM(remaining_quantity) AS quantity')
            ->where('business_id', $businessId)->where('remaining_quantity', '>', 0)
            ->whereNotNull('expiry_date')->whereDate('expiry_date', '>=', $today->toDateString())
            ->groupBy('product_id')->pluck('quantity', 'product_id');
        $expiredBatches = ProductBatch::query()
            ->selectRaw('product_id, SUM(remaining_quantity) AS quantity')
            ->where('business_id', $businessId)->where('remaining_quantity', '>', 0)
            ->whereNotNull('expiry_date')->whereDate('expiry_date', '<', $today->toDateString())
            ->groupBy('product_id')->pluck('quantity', 'product_id');
        $reorders = $this->reorderSuggestions->suggestions($businessId)->keyBy('product_id');
        $daysInPeriod = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);

        $rows = Product::query()->with(['category:id,name', 'unitRecord:id,unit_name,short_code'])
            ->where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get()
            ->map(function (Product $product) use ($periodSales, $periodReturns, $lastSales, $stockAges, $sellableBatches, $expiredBatches, $reorders, $daysInPeriod, $today): object {
                $sales = $periodSales->get($product->id);
                $returns = $periodReturns->get($product->id);
                $grossSold = (float) ($sales?->quantity ?? 0);
                $returned = (float) ($returns?->quantity ?? 0);
                $netSold = self::netQuantity($grossSold, $returned);
                $lastSale = isset($lastSales[$product->id]) ? Carbon::parse($lastSales[$product->id], config('app.timezone')) : null;
                $stockAge = isset($stockAges[$product->id]) ? Carbon::parse($stockAges[$product->id], config('app.timezone')) : null;
                $sellable = $product->has_batch_tracking ? max(0, (float) ($sellableBatches[$product->id] ?? 0)) : max(0, (float) $product->stock_quantity);
                $cost = max(0, (float) ($product->purchase_cost ?: $product->average_purchase_price ?: $product->latest_purchase_price ?: 0));

                return (object) [
                    'product' => $product, 'product_id' => $product->id, 'name' => $product->name,
                    'category' => $product->category?->name ?? '—', 'category_id' => $product->category_id,
                    'unit' => $product->unitRecord?->short_code ?: ($product->unitRecord?->unit_name ?: ($product->unit ?: '—')), 'unit_id' => $product->unit_id,
                    'current_stock' => $sellable, 'expired_stock' => (float) ($expiredBatches[$product->id] ?? 0),
                    // Net movement is completed sale quantity in the period less
                    // sales-return quantity processed in that same period.
                    'qty_sold' => $netSold, 'gross_qty_sold' => $grossSold, 'returned_quantity' => $returned,
                    'sales_count' => (int) ($sales?->sales_count ?? 0),
                    'revenue' => max(0, round((float) ($sales?->revenue ?? 0) - (float) ($returns?->refund_total ?? 0), 2)),
                    'average_daily_qty' => round($netSold / $daysInPeriod, 3),
                    'last_sale_at' => $lastSale, 'days_since_sale' => $lastSale ? $lastSale->copy()->startOfDay()->diffInDays($today) : null,
                    'stock_age_at' => $stockAge, 'days_since_stock' => $stockAge ? $stockAge->copy()->startOfDay()->diffInDays($today) : null,
                    'unit_cost' => $cost, 'inventory_value' => round($sellable * $cost, 2),
                    'suggested_quantity' => (float) ($reorders->get($product->id)?->suggested_quantity ?? 0),
                    'reorder_level' => (float) $product->low_stock_alert_qty,
                ];
            })->values();

        return $this->classify($rows, $deadThreshold);
    }

    /**
     * Returns reverse completed-sale movement. This keeps net velocity from
     * becoming negative when returns are greater than period sales.
     */
    public static function netQuantity(float $grossQuantity, float $returnedQuantity): float
    {
        return max(0, round($grossQuantity - $returnedQuantity, 3));
    }

    /** @param Collection<int, object> $rows @return Collection<int, object> */
    public function classify(Collection $rows, int $deadThreshold): Collection
    {
        $positive = $rows->filter(fn (object $row) => $row->qty_sold > 0)->sortByDesc('qty_sold')->values();
        $fastCount = $positive->isEmpty() ? 0 : max(1, (int) ceil($positive->count() * 0.20));
        $fastIds = $positive->take($fastCount)->pluck('product_id')->flip();
        $slowCutoff = $positive->isEmpty() ? null : (float) $positive->sortBy('qty_sold')->values()->get(max(0, (int) floor(($positive->count() - 1) * 0.25)))->qty_sold;
        $cutoff = now(config('app.timezone'))->startOfDay()->subDays($deadThreshold);

        return $rows->map(function (object $row) use ($fastIds, $slowCutoff, $cutoff): object {
            $dead = $row->current_stock > 0 && (
                ($row->last_sale_at && $row->last_sale_at->copy()->startOfDay()->lte($cutoff))
                || (! $row->last_sale_at && $row->stock_age_at && $row->stock_age_at->copy()->startOfDay()->lte($cutoff))
            );
            $row->movement_status = $dead ? 'Dead Stock' : (
                isset($fastIds[$row->product_id]) ? 'Fast Moving' : (
                    $row->current_stock > 0 && $row->qty_sold > 0 && $slowCutoff !== null && $row->qty_sold <= $slowCutoff ? 'Slow Moving' : (! $row->last_sale_at ? 'No Sales History' : 'Normal')
                )
            );
            $row->is_dead = $dead;

            return $row;
        });
    }

    /** @return Collection<int, string> */
    private function stockAgeMap(int $businessId): Collection
    {
        $receipts = GoodsReceiptItem::query()->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->where('goods_receipts.business_id', $businessId)->where('goods_receipt_items.accepted_quantity', '>', 0)
            ->selectRaw('goods_receipt_items.product_id, MIN(goods_receipts.received_at) AS stock_at')->groupBy('goods_receipt_items.product_id')->pluck('stock_at', 'goods_receipt_items.product_id');
        $increases = InventoryMovement::query()->where('business_id', $businessId)->whereColumn('new_stock', '>', 'previous_stock')
            ->selectRaw('product_id, MIN(movement_date) AS stock_at')->groupBy('product_id')->pluck('stock_at', 'product_id');

        return $receipts->union($increases)->mapWithKeys(function ($value, $productId) use ($receipts, $increases): array {
            $dates = collect([$receipts->get($productId), $increases->get($productId)])->filter();
            return [$productId => $dates->sort()->first()];
        });
    }
}
