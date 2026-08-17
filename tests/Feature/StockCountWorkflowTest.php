<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\StockCountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockCountWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Stock-count integration coverage requires the dedicated MySQL testing database.');
        }

        parent::setUp();
    }

    public function test_finalization_creates_signed_stock_count_history_without_overwriting_stock_silently(): void
    {
        [$business, $user, $product] = $this->context(100);
        $service = app(StockCountService::class);
        $count = $service->create($business->id, $user);
        $item = $service->addProduct($count, $product->id);
        $service->save($count, [[
            'id' => $item->id, 'physical_quantity' => 96, 'reason' => 'Counting Error', 'notes' => null,
        ]], ['counted_at' => now(), 'notes' => null]);

        $result = $service->finalize($count, $user);

        $this->assertSame(1, $result['adjusted']);
        $this->assertEquals(96, $product->fresh()->stock_quantity);
        $movement = InventoryMovement::where('stock_count_id', $count->id)->firstOrFail();
        $this->assertSame('STOCK_COUNT_ADJUSTMENT', $movement->type);
        $this->assertEquals(100, $movement->previous_stock);
        $this->assertEquals(96, $movement->new_stock);
    }

    public function test_duplicate_products_are_rejected_and_cancelled_counts_do_not_change_stock(): void
    {
        [$business, $user, $product] = $this->context(100);
        $service = app(StockCountService::class);
        $count = $service->create($business->id, $user);
        $service->addProduct($count, $product->id);

        try {
            $service->addProduct($count, $product->id);
            $this->fail('The same product cannot be counted twice in one session.');
        } catch (ValidationException) {
            $this->assertSame(100, (float) $product->fresh()->stock_quantity);
        }

        $service->cancel($count, $user);
        $this->assertSame('Cancelled', $count->fresh()->status);
        $this->assertSame(100, (float) $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_live_stock_changes_require_explicit_conflict_confirmation(): void
    {
        [$business, $user, $product] = $this->context(100);
        $service = app(StockCountService::class);
        $count = $service->create($business->id, $user);
        $item = $service->addProduct($count, $product->id);
        $service->save($count, [[
            'id' => $item->id, 'physical_quantity' => 96, 'reason' => 'Counting Error', 'notes' => null,
        ]], ['counted_at' => now(), 'notes' => null]);
        $product->update(['stock_quantity' => 98, 'current_stock' => 98]);

        $result = $service->finalize($count, $user);

        $this->assertNotEmpty($result['conflicts']);
        $this->assertSame('Draft', $count->fresh()->status);
        $this->assertTrue($item->fresh()->review_required);
        $this->assertEquals(98, $product->fresh()->stock_quantity);
    }

    private function context(float $stock): array
    {
        $business = Business::query()->create(['business_name' => 'Stock Count Test', 'business_type' => 'General']);
        $user = \App\Models\User::factory()->create(['business_id' => $business->id, 'role' => 'business_owner']);
        $product = Product::query()->create([
            'business_id' => $business->id,
            'name' => 'Counted Product',
            'unit' => 'Piece',
            'stock_quantity' => $stock,
            'current_stock' => $stock,
            'retail_price' => 10,
            'wholesale_price' => 8,
            'status' => 'Active',
            'created_by' => $user->id,
        ]);

        return [$business, $user, $product];
    }
}
