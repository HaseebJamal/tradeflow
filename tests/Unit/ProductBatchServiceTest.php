<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\ProductBatchService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductBatchServiceTest extends TestCase
{
    public function test_tracked_receipts_require_batch_quantities_to_match_accepted_stock(): void
    {
        $product = new Product(['name' => 'Tracked Item', 'has_batch_tracking' => true]);
        $service = app(ProductBatchService::class);

        $this->expectException(ValidationException::class);
        $service->validateReceiptBatches($product, [[
            'batch_number' => 'B-100', 'quantity' => 4, 'expiry_date' => now()->addMonth()->toDateString(),
        ]], 5, 0);
    }

    public function test_expiry_status_never_marks_expired_stock_as_valid(): void
    {
        $product = new Product(['expiry_alert_days' => 10]);
        $batch = new ProductBatch(['remaining_quantity' => 2, 'expiry_date' => now()->subDay()]);
        $batch->setRelation('product', $product);

        $this->assertSame('Expired', $batch->expiry_status);
    }
}
