<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseItem;
use Illuminate\Support\Collection;

class ReorderSuggestionService
{
    /**
     * Build decision-support rows only. This service deliberately never
     * creates, confirms, or changes a purchase or inventory record.
     */
    public function suggestions(int $businessId, array $filters = []): Collection
    {
        $today = now(config('app.timezone'))->toDateString();
        $batchSellable = ProductBatch::query()
            ->selectRaw('product_id, SUM(remaining_quantity) AS quantity')
            ->where('business_id', $businessId)
            ->where('remaining_quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today)
            ->groupBy('product_id')
            ->pluck('quantity', 'product_id');

        // Only unprocessed quantities on a still-open purchase can be counted
        // as incoming. Paid and free quantities are both physical incoming
        // stock, while damaged/rejected/received quantities are never counted
        // again.
        $incoming = PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.business_id', $businessId)
            ->whereIn('purchases.status', ['Confirmed', 'Ordered', 'Received'])
            ->where(fn ($query) => $query->whereNull('purchases.receiving_status')->orWhere('purchases.receiving_status', '!=', 'Fully Received'))
            ->selectRaw('purchase_items.product_id, SUM(GREATEST(0, COALESCE(purchase_items.quantity, 0) + COALESCE(purchase_items.free_quantity, 0) - COALESCE(purchase_items.received_quantity, 0) - COALESCE(purchase_items.damaged_quantity, 0) - COALESCE(purchase_items.rejected_quantity, 0))) AS quantity')
            ->groupBy('purchase_items.product_id')
            ->pluck('quantity', 'purchase_items.product_id');

        // This is informational only: it is the most recent purchase supplier
        // and cost, not a permanent preferred-supplier relationship.
        $latestPurchaseContext = PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->where('purchases.business_id', $businessId)
            ->whereNotIn('purchases.status', ['Cancelled', 'Draft'])
            ->orderByDesc('purchases.purchase_date')
            ->orderByDesc('purchases.id')
            ->get([
                'purchase_items.product_id',
                'purchase_items.unit_cost',
                'purchases.supplier_id',
                'suppliers.supplier_name',
                'suppliers.company_name',
            ])->unique('product_id')->keyBy('product_id');

        $products = Product::query()
            ->with(['category:id,name', 'unitRecord:id,unit_name,short_code'])
            ->where('business_id', $businessId)
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $rows = $products->map(function (Product $product) use ($batchSellable, $incoming, $latestPurchaseContext) {
            $current = $product->has_batch_tracking
                ? max(0, (float) ($batchSellable[$product->id] ?? 0))
                : max(0, (float) $product->stock_quantity);
            $openIncoming = max(0, (float) ($incoming[$product->id] ?? 0));
            $reorder = max(0, (float) $product->low_stock_alert_qty);
            $target = max(0, (float) $product->target_stock_level);
            $projected = round($current + $openIncoming, 3);
            $needsReorder = $reorder > 0 && $current < $reorder;
            $suggested = $needsReorder ? max(0, round($target - $projected, 3)) : 0;
            $context = $latestPurchaseContext->get($product->id);
            $cost = $product->currentPurchasePrice();
            if ($cost <= 0 && $context) $cost = (float) $context->unit_cost;

            return (object) [
                'product' => $product,
                'product_id' => $product->id,
                'name' => $product->name,
                'category' => $product->category?->name ?? '—',
                'category_id' => $product->category_id,
                'unit' => $product->unitRecord?->short_code ?: ($product->unitRecord?->unit_name ?: ($product->unit ?: '—')),
                'unit_id' => $product->unit_id,
                'current_stock' => $current,
                'open_incoming' => $openIncoming,
                'projected_stock' => $projected,
                'reorder_level' => $reorder,
                'target_stock' => $target,
                'suggested_quantity' => $suggested,
                'latest_cost' => $cost > 0 ? $cost : null,
                'estimated_cost' => $cost > 0 ? round($suggested * $cost, 2) : null,
                'supplier_id' => $context?->supplier_id,
                'supplier' => $context ? trim(($context->supplier_name ?? '').(($context->company_name ?? '') ? ' · '.$context->company_name : '')) : '—',
                'status' => $current <= 0 ? 'Out of Stock' : ($needsReorder ? 'Below Reorder' : 'Healthy'),
                'needs_reorder' => $needsReorder,
                'is_batch_tracked' => (bool) $product->has_batch_tracking,
            ];
        })->filter(function (object $row) use ($filters): bool {
            $search = strtolower(trim((string) ($filters['search'] ?? '')));
            if ($search !== '' && ! str_contains(strtolower($row->name), $search) && ! str_contains(strtolower($row->product->barcode ?? ''), $search)) return false;
            if (! empty($filters['category_id']) && (int) $row->category_id !== (int) $filters['category_id']) return false;
            if (! empty($filters['unit_id']) && (int) $row->unit_id !== (int) $filters['unit_id']) return false;
            if (! empty($filters['supplier_id']) && (int) $row->supplier_id !== (int) $filters['supplier_id']) return false;

            return match ($filters['stock_status'] ?? 'all') {
                'out_of_stock' => $row->status === 'Out of Stock',
                'below_reorder' => $row->status === 'Below Reorder',
                'all' => $row->needs_reorder,
                default => $row->needs_reorder,
            };
        })->values();

        return $rows;
    }

    /** @return array{current_stock:float,projected_stock:float,suggested_quantity:float,status:string} */
    public static function calculate(float $currentStock, float $openIncoming, float $reorderLevel, float $targetStock): array
    {
        $currentStock = max(0, $currentStock);
        $openIncoming = max(0, $openIncoming);
        $reorderLevel = max(0, $reorderLevel);
        $targetStock = max(0, $targetStock);
        $needsReorder = $reorderLevel > 0 && $currentStock < $reorderLevel;

        return [
            'current_stock' => $currentStock,
            'projected_stock' => round($currentStock + $openIncoming, 3),
            'suggested_quantity' => $needsReorder ? max(0, round($targetStock - $currentStock - $openIncoming, 3)) : 0.0,
            'status' => $currentStock <= 0 ? 'Out of Stock' : ($needsReorder ? 'Below Reorder' : 'Healthy'),
        ];
    }
}
