<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductBatchAllocation;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ProductBatchService
{
    public function validateReceiptBatches(Product $product, array $batches, float $accepted, int $lineIndex): array
    {
        if (! $product->has_batch_tracking || $accepted <= 0) return [];
        if ($batches === []) {
            throw ValidationException::withMessages(["items.$lineIndex.batches" => 'Add batch number and expiry details for every accepted quantity of '.$product->name.'.']);
        }

        $normalised = collect($batches)->values()->map(function (array $batch, int $batchIndex) use ($product, $lineIndex): array {
            $number = trim((string) ($batch['batch_number'] ?? ''));
            $quantity = round((float) ($batch['quantity'] ?? 0), 3);
            $expiry = $batch['expiry_date'] ?? null;
            if ($number === '' || $quantity <= 0 || ! $expiry) {
                throw ValidationException::withMessages(["items.$lineIndex.batches.$batchIndex" => 'Each batch needs a number, quantity, and expiry date.']);
            }
            $expiryDate = Carbon::parse($expiry, config('app.timezone'))->startOfDay();
            if ($expiryDate->lt(now(config('app.timezone'))->startOfDay())) {
                throw ValidationException::withMessages(["items.$lineIndex.batches.$batchIndex.expiry_date" => 'Expired batches cannot be received into sellable inventory.']);
            }
            $manufacturing = ! empty($batch['manufacturing_date']) ? Carbon::parse($batch['manufacturing_date'], config('app.timezone'))->startOfDay() : null;
            if ($manufacturing && $manufacturing->gt($expiryDate)) {
                throw ValidationException::withMessages(["items.$lineIndex.batches.$batchIndex.manufacturing_date" => 'Manufacturing date cannot be after expiry date.']);
            }
            return ['batch_number' => $number, 'quantity' => $quantity, 'manufacturing_date' => $manufacturing?->toDateString(), 'expiry_date' => $expiryDate->toDateString()];
        })->all();
        if (abs(round(collect($normalised)->sum('quantity'), 3) - $accepted) > 0.0001) {
            throw ValidationException::withMessages(["items.$lineIndex.batches" => 'Batch quantities must total the accepted quantity ('.rtrim(rtrim(number_format($accepted, 3, '.', ''), '0'), '.').').']);
        }
        return $normalised;
    }

    public function receive(Product $product, Purchase $purchase, GoodsReceipt $receipt, GoodsReceiptItem $receiptItem, array $batches): Collection
    {
        if (! $product->has_batch_tracking) return collect();
        return collect($batches)->map(function (array $line) use ($product, $purchase, $receipt, $receiptItem) {
            $batch = ProductBatch::query()->where('business_id', $product->business_id)->where('product_id', $product->id)
                ->where('batch_number', $line['batch_number'])->whereDate('expiry_date', $line['expiry_date'])->lockForUpdate()->first();
            if ($batch) {
                $batch->update([
                    'received_quantity' => round((float) $batch->received_quantity + $line['quantity'], 3),
                    'remaining_quantity' => round((float) $batch->remaining_quantity + $line['quantity'], 3),
                    'unit_cost' => $receiptItem->unit_cost,
                    'goods_receipt_id' => $receipt->id,
                    'purchase_id' => $purchase->id,
                ]);
            } else {
                $batch = ProductBatch::create($line + [
                    'business_id' => $product->business_id, 'product_id' => $product->id, 'purchase_id' => $purchase->id,
                    'goods_receipt_id' => $receipt->id, 'received_quantity' => $line['quantity'], 'remaining_quantity' => $line['quantity'],
                    'unit_cost' => $receiptItem->unit_cost, 'source' => 'GRN',
                ]);
            }
            return $batch;
        });
    }

    public function assertSellable(Product $product, float $quantity): void
    {
        if (! $product->has_batch_tracking) return;
        $batches = ProductBatch::query()->where('business_id', $product->business_id)->where('product_id', $product->id)->sellable()->lockForUpdate()->get();
        $available = round((float) $batches->sum('remaining_quantity'), 3);
        if ($available + 0.0001 < $quantity) {
            throw ValidationException::withMessages(['items' => 'Insufficient valid batch stock for '.$product->name.'. Allocate existing stock to batches or receive a valid batch before selling.']);
        }
    }

    public function allocateSale(Product $product, Order $order, OrderItem $item, float $quantity, int $userId): void
    {
        if (! $product->has_batch_tracking || $quantity <= 0) return;
        $remaining = round($quantity, 3);
        $batches = ProductBatch::query()->where('business_id', $product->business_id)->where('product_id', $product->id)
            ->sellable()->orderBy('expiry_date')->orderBy('id')->lockForUpdate()->get();
        foreach ($batches as $batch) {
            if ($remaining <= 0.0001) break;
            $taken = min($remaining, (float) $batch->remaining_quantity);
            if ($taken <= 0) continue;
            $batch->update(['remaining_quantity' => round((float) $batch->remaining_quantity - $taken, 3)]);
            ProductBatchAllocation::create(['business_id' => $product->business_id, 'product_batch_id' => $batch->id, 'order_id' => $order->id, 'order_item_id' => $item->id, 'quantity' => $taken, 'type' => 'Sale', 'created_by' => $userId]);
            $remaining = round($remaining - $taken, 3);
        }
        if ($remaining > 0.0001) throw ValidationException::withMessages(['items' => 'The valid batch stock changed while completing this sale. Please review the cart.']);
    }

    public function restoreSaleReturn(OrderItem $item, float $quantity): void
    {
        $remaining = round($quantity, 3);
        $allocations = ProductBatchAllocation::query()->where('order_item_id', $item->id)->where('type', 'Sale')->orderBy('id')->lockForUpdate()->get();
        foreach ($allocations as $allocation) {
            if ($remaining <= 0.0001) break;
            $returned = ProductBatchAllocation::query()->where('order_item_id', $item->id)->where('product_batch_id', $allocation->product_batch_id)->where('type', 'Sale Return')->sum('quantity');
            $available = max(0, (float) $allocation->quantity - (float) $returned);
            $restore = min($remaining, $available);
            if ($restore <= 0) continue;
            $batch = ProductBatch::lockForUpdate()->find($allocation->product_batch_id);
            if (! $batch) continue;
            $batch->increment('remaining_quantity', $restore);
            ProductBatchAllocation::create(['business_id' => $allocation->business_id, 'product_batch_id' => $batch->id, 'order_id' => $item->order_id, 'order_item_id' => $item->id, 'quantity' => $restore, 'type' => 'Sale Return', 'created_by' => auth()->id()]);
            $remaining = round($remaining - $restore, 3);
        }
    }

    public function allocatePurchaseReturn(Product $product, Purchase $purchase, float $quantity): void
    {
        if (! $product->has_batch_tracking || $quantity <= 0) return;
        $remaining = round($quantity, 3);
        $batches = ProductBatch::query()->where('business_id', $product->business_id)->where('product_id', $product->id)
            ->where('purchase_id', $purchase->id)->where('remaining_quantity', '>', 0)->orderBy('expiry_date')->orderBy('id')->lockForUpdate()->get();
        foreach ($batches as $batch) {
            if ($remaining <= 0.0001) break;
            $taken = min($remaining, (float) $batch->remaining_quantity);
            $batch->decrement('remaining_quantity', $taken);
            $remaining = round($remaining - $taken, 3);
        }
        if ($remaining > 0.0001) {
            throw ValidationException::withMessages(['items' => 'This return exceeds batch stock originally received for '.$product->name.'.']);
        }
    }
}
