<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBatch;

/**
 * Idempotent condition alerts. It intentionally has no side effects on stock,
 * pricing, or lifecycle data; it only keeps actionable inbox records current.
 */
class OperationalAlertService
{
    public function __construct(private readonly BusinessNotificationPolicy $notifications) {}

    public function synchronizeAll(): void
    {
        Business::query()->select('id', 'business_name')->chunkById(100, function ($businesses): void {
            foreach ($businesses as $business) {
                $this->synchronizeBusiness($business);
            }
        });
    }

    public function synchronizeBusiness(Business $business): void
    {
        $this->synchronizeLowStock($business);
        $this->synchronizeBatchExpiry($business);
    }

    private function synchronizeLowStock(Business $business): void
    {
        Product::query()->where('business_id', $business->id)->get(['id', 'business_id', 'name', 'stock_quantity', 'low_stock_alert_qty'])
            ->each(function (Product $product) use ($business): void {
                $key = 'low-stock:'.$business->id.':'.$product->id;
                $stock = (float) $product->stock_quantity;
                $level = (float) $product->low_stock_alert_qty;
                if (! self::isLowStock($stock, $level)) {
                    $this->notifications->resolveForBusiness($business->id, $key);
                    return;
                }

                $this->notifications->publish(
                    $this->notifications->inventoryRecipients($business),
                    'Low stock: '.$product->name,
                    $product->name.' is below reorder level ('.self::quantity($stock).' / '.self::quantity($level).').',
                    $business->id,
                    'inventory',
                    'warning',
                    $key,
                    ['related_type' => Product::class, 'related_id' => $product->id]
                );
            });
    }

    private function synchronizeBatchExpiry(Business $business): void
    {
        $today = now(config('app.timezone'))->startOfDay();
        ProductBatch::query()
            ->where('business_id', $business->id)
            ->where('remaining_quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->with('product:id,name,expiry_alert_days')
            ->orderBy('id')
            ->chunkById(100, function ($batches) use ($business, $today): void {
                foreach ($batches as $batch) {
                    $expiringKey = 'batch-expiring:'.$business->id.':'.$batch->id;
                    $expiredKey = 'batch-expired:'.$business->id.':'.$batch->id;
                    $expiry = $batch->expiry_date?->copy()->startOfDay();
                    $warningDays = max(0, (int) ($batch->product?->expiry_alert_days ?? 30));
                    $status = self::batchExpiryStatus($expiry, $warningDays, $today);

                    if ($status === 'valid') {
                        $this->notifications->resolveForBusiness($business->id, $expiringKey);
                        $this->notifications->resolveForBusiness($business->id, $expiredKey);
                        continue;
                    }

                    $isExpired = $status === 'expired';
                    $this->notifications->resolveForBusiness($business->id, $isExpired ? $expiringKey : $expiredKey);
                    $this->notifications->publish(
                        $this->notifications->inventoryRecipients($business),
                        ($isExpired ? 'Expired batch: ' : 'Batch expiring soon: ').($batch->product?->name ?? 'Product'),
                        'Batch '.$batch->batch_number.' for '.($batch->product?->name ?? 'this product').' has '.($isExpired ? 'expired' : 'an upcoming expiry').' on '.$expiry?->format('n/j/Y').'. Remaining quantity: '.self::quantity((float) $batch->remaining_quantity).'.',
                        $business->id,
                        'inventory',
                        $isExpired ? 'critical' : 'warning',
                        $isExpired ? $expiredKey : $expiringKey,
                        ['related_type' => ProductBatch::class, 'related_id' => $batch->id, 'product_id' => $batch->product_id]
                    );
                }
            });
    }

    private static function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ','), '0'), '.');
    }

    public static function isLowStock(float $stock, float $level): bool
    {
        return $level > 0 && $stock <= $level;
    }

    public static function batchExpiryStatus(?\Carbon\CarbonInterface $expiry, int $warningDays, \Carbon\CarbonInterface $today): string
    {
        if (! $expiry) {
            return 'valid';
        }

        $expiry = $expiry->copy()->startOfDay();
        $today = $today->copy()->startOfDay();

        if ($expiry->lt($today)) {
            return 'expired';
        }

        return $expiry->lte($today->addDays(max(0, $warningDays))) ? 'expiring' : 'valid';
    }
}
