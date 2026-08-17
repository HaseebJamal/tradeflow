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
                'returnItems:id,purchase_item_id,quantity,paid_quantity,free_quantity',
            ])
            ->where('product_id', $product->id)
            ->whereHas('purchase', fn ($query) => $query
                ->where('business_id', $product->business_id)
                ->whereHas('goodsReceipts'))
            ->get()
            ->map(function (PurchaseItem $item) {
                $item->net_received_quantity = max(0, (float) $item->received_quantity - (float) $item->returnItems->sum('quantity'));
                // New bonus-aware GRNs persist paid accepted quantities. For
                // historical purchases the column remains NULL, so preserve
                // their original quantity × unit-cost calculation exactly.
                $receiptItems = $item->goodsReceiptItems()->get();
                $legacyAccepted = $receiptItems
                    ->filter(fn ($receiptItem) => $receiptItem->paid_accepted_quantity === null)
                    ->sum('accepted_quantity');
                $paidReceived = (float) $legacyAccepted + (float) $receiptItems
                    ->filter(fn ($receiptItem) => $receiptItem->paid_accepted_quantity !== null)
                    ->sum('paid_accepted_quantity');
                $paidReturned = $item->returnItems->sum(fn ($return) => $return->paid_quantity === null ? (float) $return->quantity : (float) $return->paid_quantity);
                $item->net_paid_received_quantity = max(0, $paidReceived - (float) $paidReturned);

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
        $totalCost = $items->sum(fn (PurchaseItem $item) => $item->net_paid_received_quantity * (float) $item->unit_cost);
        $orderedItems = $items->sortByDesc(fn (PurchaseItem $item) => $item->purchase->received_at ?? $item->purchase->purchase_date ?? $item->purchase->created_at);
        $latestCostItem = $orderedItems->first(fn (PurchaseItem $item) => $item->net_paid_received_quantity > 0);
        $existingCost = max(
            (float) ($product->latest_purchase_price ?? 0),
            (float) ($product->average_purchase_price ?? 0),
            (float) ($product->purchase_cost ?? 0),
        );
        $average = $totalCost > 0
            ? round($totalCost / max(0.001, $totalQuantity), 2)
            : $existingCost;
        $latest = $latestCostItem
            ? round($latestCostItem->net_paid_received_quantity * (float) $latestCostItem->unit_cost / max(0.001, $latestCostItem->net_received_quantity), 2)
            : $existingCost;

        $product->update([
            'latest_purchase_price' => $latest,
            'average_purchase_price' => $average,
            // Preserve existing inventory/COGS consumers by keeping the legacy
            // cost field aligned with the calculated weighted average.
            'purchase_cost' => $average,
        ]);

        $product->refresh();
    }
}
