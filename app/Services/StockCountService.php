<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockCountService
{
    public const REASONS = [
        'Counting Error', 'Damaged', 'Expired', 'Lost / Theft', 'Internal Use',
        'Unrecorded Receipt', 'Unrecorded Return', 'Other',
    ];

    public function __construct(private DocumentNumberService $numbers) {}

    public function create(int $businessId, User $user, ?string $countedAt = null, ?string $notes = null): StockCount
    {
        return DB::transaction(function () use ($businessId, $user, $countedAt, $notes): StockCount {
            return StockCount::create([
                'business_id' => $businessId,
                'reference' => $this->numbers->next($businessId, 'stock_count'),
                'counted_at' => $countedAt ?: now(),
                'status' => 'Draft',
                'notes' => $notes,
                'created_by' => $user->id,
            ]);
        });
    }

    public function addProduct(StockCount $count, int $productId): StockCountItem
    {
        $this->ensureEditable($count);

        return DB::transaction(function () use ($count, $productId): StockCountItem {
            $product = Product::query()->where('business_id', $count->business_id)->lockForUpdate()->findOrFail($productId);

            if (StockCountItem::where('stock_count_id', $count->id)->where('product_id', $product->id)->exists()) {
                throw ValidationException::withMessages(['product_id' => $product->name.' is already in this stock count.']);
            }

            return StockCountItem::create([
                'stock_count_id' => $count->id,
                'business_id' => $count->business_id,
                'product_id' => $product->id,
                'system_quantity' => $product->stock_quantity,
            ]);
        });
    }

    /** @param array<int, array<string, mixed>> $rows */
    public function save(StockCount $count, array $rows, array $sessionData): void
    {
        $this->ensureEditable($count);
        DB::transaction(function () use ($count, $rows, $sessionData): void {
            $items = StockCountItem::where('stock_count_id', $count->id)->lockForUpdate()->get()->keyBy('id');
            foreach ($rows as $row) {
                $item = $items->get((int) $row['id']);
                if (!$item) abort(403);

                $physical = array_key_exists('physical_quantity', $row) && $row['physical_quantity'] !== ''
                    ? round((float) $row['physical_quantity'], 3)
                    : null;
                $variance = $physical === null ? null : self::variance((float) $item->system_quantity, $physical);
                $reason = $variance === null || abs($variance) < 0.0005 ? null : ($row['reason'] ?? null);
                $notes = $row['notes'] ?? null;

                if ($variance !== null && abs($variance) >= 0.0005 && blank($reason)) {
                    throw ValidationException::withMessages(['items.'.$item->id.'.reason' => 'A reason is required for '.$item->product?->name.'.']);
                }
                if ($reason === 'Other' && blank($notes)) {
                    throw ValidationException::withMessages(['items.'.$item->id.'.notes' => 'Enter a note when the reason is Other.']);
                }

                $item->update([
                    'physical_quantity' => $physical,
                    'variance' => $variance,
                    'reason' => $reason,
                    'notes' => $notes,
                    'review_required' => false,
                    'current_system_quantity' => null,
                    'applied_variance' => null,
                ]);
            }

            $count->update([
                'counted_at' => $sessionData['counted_at'],
                'notes' => $sessionData['notes'] ?? null,
            ]);
        });
    }

    /** @return array{conflicts: array<int, array<string, mixed>>, adjusted: int} */
    public function finalize(StockCount $count, User $user, bool $confirmConflicts = false): array
    {
        $this->ensureEditable($count);

        return DB::transaction(function () use ($count, $user, $confirmConflicts): array {
            $count = StockCount::whereKey($count->id)->lockForUpdate()->firstOrFail();
            $this->ensureEditable($count);
            $items = StockCountItem::where('stock_count_id', $count->id)->with('product')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Add at least one product before finalizing this stock count.']);
            }
            if ($items->contains(fn (StockCountItem $item) => $item->physical_quantity === null)) {
                throw ValidationException::withMessages(['items' => 'Enter a physical quantity for every counted product.']);
            }

            $products = Product::where('business_id', $count->business_id)
                ->whereIn('id', $items->pluck('product_id')->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            if ($products->count() !== $items->count()) abort(403);

            $conflicts = [];
            foreach ($items as $item) {
                $current = (float) $products[$item->product_id]->stock_quantity;
                if (abs($current - (float) $item->system_quantity) >= 0.0005) {
                    $conflicts[] = [
                        'product' => $item->product?->name ?? 'Product',
                        'snapshot' => (float) $item->system_quantity,
                        'current' => $current,
                        'physical' => (float) $item->physical_quantity,
                    ];
                    $item->update(['review_required' => true, 'current_system_quantity' => $current]);
                }
            }

            if ($conflicts !== [] && !$confirmConflicts) {
                return ['conflicts' => $conflicts, 'adjusted' => 0];
            }

            $adjusted = 0;
            foreach ($items as $item) {
                $product = $products[$item->product_id];
                $before = (float) $product->stock_quantity;
                $after = round((float) $item->physical_quantity, 3);
                $appliedVariance = self::variance($before, $after);

                if (abs($appliedVariance) >= 0.0005 && blank($item->reason)) {
                    throw ValidationException::withMessages(['items' => 'A reason is required before reconciling '.$product->name.'.']);
                }
                if ($item->reason === 'Other' && blank($item->notes)) {
                    throw ValidationException::withMessages(['items' => 'Enter a note for Other on '.$product->name.'.']);
                }

                $item->update(['current_system_quantity' => $before, 'applied_variance' => $appliedVariance, 'review_required' => false]);
                if (abs($appliedVariance) < 0.0005) continue;

                $product->update(['stock_quantity' => $after, 'current_stock' => $after]);
                $inventory = Inventory::firstOrCreate(
                    ['business_id' => $count->business_id, 'product_id' => $product->id],
                    ['available_stock' => $before, 'low_stock_alert' => $product->low_stock_alert_qty ?? 10]
                );
                $inventory->update(['available_stock' => $after, 'low_stock_alert' => $product->low_stock_alert_qty ?? 10]);

                $note = 'Stock count '.$count->reference.' · '.$item->reason.($item->notes ? ' · '.$item->notes : '');
                InventoryMovement::create([
                    'business_id' => $count->business_id,
                    'product_id' => $product->id,
                    'stock_count_id' => $count->id,
                    'type' => 'STOCK_COUNT_ADJUSTMENT',
                    'quantity' => abs($appliedVariance),
                    'previous_stock' => $before,
                    'new_stock' => $after,
                    'note' => $note,
                    'created_by' => $user->id,
                    'movement_date' => now(),
                ]);
                StockMovement::create([
                    'business_id' => $count->business_id,
                    'product_id' => $product->id,
                    'stock_count_id' => $count->id,
                    'type' => 'stock_count_adjustment',
                    'quantity' => $appliedVariance,
                    'reason' => $item->reason,
                    'note' => 'Stock count '.$count->reference.($item->notes ? ' · '.$item->notes : ''),
                    'user_id' => $user->id,
                    'created_by' => $user->id,
                ]);
                $adjusted++;
            }

            $count->update(['status' => 'Completed', 'completed_by' => $user->id, 'completed_at' => now()]);

            return ['conflicts' => [], 'adjusted' => $adjusted];
        });
    }

    public function cancel(StockCount $count, User $user): void
    {
        $this->ensureEditable($count);
        $count->update(['status' => 'Cancelled', 'cancelled_by' => $user->id, 'cancelled_at' => now()]);
    }

    public static function variance(float $systemQuantity, float $physicalQuantity): float
    {
        return round($physicalQuantity - $systemQuantity, 3);
    }

    private function ensureEditable(StockCount $count): void
    {
        if (!in_array($count->status, ['Draft', 'In Progress'], true)) {
            abort(403, 'Completed and cancelled stock counts are read-only.');
        }
    }
}
