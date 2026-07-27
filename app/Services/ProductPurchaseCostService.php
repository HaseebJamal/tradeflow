<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseItem;

class ProductPurchaseCostService
{
    /**
     * Refresh the product's purchase-derived costs without altering any stock
     * or supplier/accounting records. Returned quantities are excluded.
     */
    public function refresh(Product $product): void
    {
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
    }
}
