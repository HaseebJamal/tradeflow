<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only inventory presentation totals.
 *
 * Product stock remains the transactional source of truth. This service only
 * groups the auditable documents and movements required by the inventory
 * directory, avoiding the stale denormalized counters left by older flows.
 */
class InventorySummaryService
{
    /**
     * @param Collection<int, Product> $products
     * @return Collection<int, array{available: float, sold: float, damaged: float, sales_returned: float, purchase_returned: float, expired: float, alert_qty: float}>
     */
    public function summaries(int $businessId, Collection $products): Collection
    {
        $products = $products->filter(fn ($product) => $product instanceof Product)->keyBy('id');
        $productIds = $products->keys()->map(fn ($id) => (int) $id)->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $sold = $this->totals(
            DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.business_id', $businessId)
                ->whereIn('order_items.product_id', $productIds)
                ->whereIn('orders.status', ['Delivered', 'Completed', 'Partially Returned'])
                ->selectRaw('order_items.product_id, SUM(order_items.quantity) as quantity')
                ->groupBy('order_items.product_id')
        );

        $salesReturned = $this->totals(
            DB::table('sales_return_items')
                ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_items.sales_return_id')
                ->join('order_items', 'order_items.id', '=', 'sales_return_items.order_item_id')
                ->where('sales_returns.business_id', $businessId)
                ->whereIn('order_items.product_id', $productIds)
                ->selectRaw('order_items.product_id, SUM(sales_return_items.quantity) as quantity')
                ->groupBy('order_items.product_id')
        );

        $purchaseReturned = $this->totals(
            DB::table('purchase_return_items')
                ->join('purchase_returns', 'purchase_returns.id', '=', 'purchase_return_items.purchase_return_id')
                ->where('purchase_returns.business_id', $businessId)
                ->whereIn('purchase_return_items.product_id', $productIds)
                ->selectRaw('purchase_return_items.product_id, SUM(purchase_return_items.quantity) as quantity')
                ->groupBy('purchase_return_items.product_id')
        );

        // GRN damage is not included: the receiving service only adds
        // accepted quantity to stock, so damaged/rejected GRN units never
        // become retained inventory. Manual and stock-count damaged write-offs
        // do leave physical custody and are represented by StockMovement.
        $damaged = $this->totals(
            DB::table('stock_movements')
                ->where('business_id', $businessId)
                ->whereIn('product_id', $productIds)
                ->where(function ($query): void {
                    $query->where('type', 'damaged')
                        ->orWhere(function ($stockCount): void {
                            $stockCount->where('type', 'stock_count_adjustment')
                                ->where('reason', 'Damaged')
                                ->where('quantity', '<', 0);
                        });
                })
                ->selectRaw("product_id, SUM(CASE WHEN type = 'damaged' THEN ABS(quantity) WHEN type = 'stock_count_adjustment' AND reason = 'Damaged' AND quantity < 0 THEN ABS(quantity) ELSE 0 END) as quantity")
                ->groupBy('product_id')
        );

        $today = now(config('app.timezone'))->toDateString();
        $batchStates = DB::table('product_batches')
            ->where('business_id', $businessId)
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, SUM(CASE WHEN remaining_quantity > 0 AND expiry_date >= ? THEN remaining_quantity ELSE 0 END) as sellable_quantity, SUM(CASE WHEN remaining_quantity > 0 AND expiry_date < ? THEN remaining_quantity ELSE 0 END) as expired_quantity', [$today, $today])
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        return $products->mapWithKeys(function (Product $product, int $productId) use ($sold, $salesReturned, $purchaseReturned, $damaged, $batchStates): array {
            $batchState = $batchStates->get($productId);

            return [$productId => [
                'available' => $product->has_batch_tracking
                    ? max(0, (float) ($batchState->sellable_quantity ?? 0))
                    : max(0, (float) $product->stock_quantity),
                'sold' => (float) ($sold[$productId] ?? 0),
                'damaged' => (float) ($damaged[$productId] ?? 0),
                'sales_returned' => (float) ($salesReturned[$productId] ?? 0),
                'purchase_returned' => (float) ($purchaseReturned[$productId] ?? 0),
                'expired' => (float) ($batchState->expired_quantity ?? 0),
                'alert_qty' => (float) $product->low_stock_alert_qty,
            ]];
        });
    }

    /** @return Collection<int, float> */
    private function totals($query): Collection
    {
        return $query->get()->mapWithKeys(fn ($row) => [(int) $row->product_id => (float) $row->quantity]);
    }
}
