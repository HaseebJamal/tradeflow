<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseItem;

class ProductPurchaseCostService
{
    public function __construct(private readonly BusinessActivityService $activity) {}

    /**
     * Refresh the product's purchase-derived costs without altering any stock
     * or supplier/accounting records. Returned quantities are excluded.
     */
    public function refresh(Product $product): void
    {
        $wasPricingAttention = $product->hasPricingAttention();
        $items = PurchaseItem::query()
            ->with([
                'purchase:id,business_id,status,purchase_date,received_at,created_at',
                'returnItems:id,purchase_item_id,quantity',
            ])
            ->where('product_id', $product->id)
            ->whereHas('purchase', fn ($query) => $query
                ->where('business_id', $product->business_id)
                ->whereHas('goodsReceipts'))
            ->get()
            ->map(function (PurchaseItem $item) {
                $item->net_received_quantity = max(0, (float) $item->received_quantity - (float) $item->returnItems->sum('quantity'));

                return $item;
            })
            ->filter(fn (PurchaseItem $item) => $item->net_received_quantity > 0)
            ->values();

        if ($items->isEmpty()) {
            $product->update([
                'latest_purchase_price' => null,
                'average_purchase_price' => null,
            ]);

            return;
        }

        $totalQuantity = (float) $items->sum('net_received_quantity');
        $totalCost = $items->sum(fn (PurchaseItem $item) => $item->net_received_quantity * (float) $item->unit_cost);
        $latestItem = $items->sortByDesc(fn (PurchaseItem $item) => $item->purchase->received_at ?? $item->purchase->purchase_date ?? $item->purchase->created_at)->first();
        $average = round($totalCost / max(0.001, $totalQuantity), 2);

        $product->update([
            'latest_purchase_price' => round((float) $latestItem->unit_cost, 2),
            'average_purchase_price' => $average,
            // Preserve existing inventory/COGS consumers by keeping the legacy
            // cost field aligned with the calculated weighted average.
            'purchase_cost' => $average,
        ]);

        $product->refresh();

        // Never overwrite prices when accepted purchase costs increase. Flag
        // the first newly-invalid state and notify users who are allowed to
        // see business notifications; the product edit form shows the same
        // warning until both prices are corrected.
        if (! $wasPricingAttention && $product->hasPricingAttention()) {
            $this->activity->record(
                $product->business_id,
                'Products',
                'Product pricing needs attention after a purchase cost update.',
                $product->id,
                null,
                [
                    'product' => $product->name,
                    'purchase_price' => $product->currentPurchasePrice(),
                    'retail_price' => $product->retail_price,
                    'wholesale_price' => $product->wholesale_price,
                ],
            );
        }
    }
}
