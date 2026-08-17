<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventorySummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventorySummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Inventory-summary aggregation coverage requires the dedicated MySQL testing database.');
        }

        parent::setUp();
    }

    public function test_it_aggregates_canonical_stock_states_without_counting_grn_rejections(): void
    {
        [$business, $user] = $this->context('Inventory Summary');
        $standard = $this->product($business, $user, 'Standard stock', 20);
        $batched = $this->product($business, $user, 'Batch stock', 20, true);

        $order = Order::create(['order_number' => 'SALE-SUMMARY-1', 'business_id' => $business->id, 'created_by' => $user->id, 'status' => 'Completed']);
        $orderItem = OrderItem::create(['order_id' => $order->id, 'product_id' => $standard->id, 'quantity' => 10, 'price' => 1, 'total' => 10]);
        Order::create(['order_number' => 'SALE-SUMMARY-CANCELLED', 'business_id' => $business->id, 'created_by' => $user->id, 'status' => 'Cancelled'])
            ->items()->create(['product_id' => $standard->id, 'quantity' => 99, 'price' => 1, 'total' => 99]);
        Order::create(['order_number' => 'SALE-SUMMARY-HELD', 'business_id' => $business->id, 'created_by' => $user->id, 'status' => 'Held'])
            ->items()->create(['product_id' => $standard->id, 'quantity' => 99, 'price' => 1, 'total' => 99]);

        $salesReturn = SalesReturn::create(['business_id' => $business->id, 'return_number' => 'SR-SUMMARY-1', 'order_id' => $order->id, 'processed_by' => $user->id, 'returned_at' => now()]);
        SalesReturnItem::create(['sales_return_id' => $salesReturn->id, 'order_item_id' => $orderItem->id, 'quantity' => 2, 'refund_total' => 2]);

        $supplier = Supplier::create(['business_id' => $business->id, 'supplier_name' => 'Summary supplier', 'created_by' => $user->id]);
        $purchase = Purchase::create(['business_id' => $business->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'purchase_number' => 'PUR-SUMMARY-1', 'purchase_date' => now()]);
        $purchaseItem = PurchaseItem::create(['purchase_id' => $purchase->id, 'product_id' => $standard->id, 'product_name_snapshot' => $standard->name, 'quantity' => 20, 'unit_cost' => 1, 'line_total' => 20]);
        $receipt = GoodsReceipt::create(['business_id' => $business->id, 'purchase_id' => $purchase->id, 'supplier_id' => $supplier->id, 'grn_number' => 'GRN-SUMMARY-1', 'received_at' => now(), 'created_by' => $user->id]);
        GoodsReceiptItem::create(['goods_receipt_id' => $receipt->id, 'purchase_item_id' => $purchaseItem->id, 'product_id' => $standard->id, 'accepted_quantity' => 0, 'damaged_quantity' => 5, 'rejected_quantity' => 10, 'unit_cost' => 1, 'line_total' => 0]);
        $purchaseReturn = PurchaseReturn::create(['business_id' => $business->id, 'purchase_id' => $purchase->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'return_number' => 'PR-SUMMARY-1', 'return_date' => now()]);
        PurchaseReturnItem::create(['purchase_return_id' => $purchaseReturn->id, 'purchase_item_id' => $purchaseItem->id, 'product_id' => $standard->id, 'quantity' => 5, 'unit_cost' => 1, 'line_total' => 5]);

        DB::table('stock_movements')->insert(['business_id' => $business->id, 'product_id' => $standard->id, 'type' => 'damaged', 'quantity' => 3, 'reason' => 'Manual inventory movement', 'created_at' => now(), 'updated_at' => now()]);
        ProductBatch::create(['business_id' => $business->id, 'product_id' => $batched->id, 'batch_number' => 'VALID-1', 'expiry_date' => now()->addDays(5)->toDateString(), 'received_quantity' => 12, 'remaining_quantity' => 12, 'source' => 'GRN']);
        ProductBatch::create(['business_id' => $business->id, 'product_id' => $batched->id, 'batch_number' => 'EXPIRED-1', 'expiry_date' => now()->subDay()->toDateString(), 'received_quantity' => 8, 'remaining_quantity' => 8, 'source' => 'GRN']);

        $summary = app(InventorySummaryService::class)->summaries($business->id, collect([$standard, $batched]));

        $this->assertSame(20.0, $summary[$standard->id]['available']);
        $this->assertSame(10.0, $summary[$standard->id]['sold']);
        $this->assertSame(3.0, $summary[$standard->id]['damaged']);
        $this->assertSame(2.0, $summary[$standard->id]['sales_returned']);
        $this->assertSame(5.0, $summary[$standard->id]['purchase_returned']);
        $this->assertSame(0.0, $summary[$standard->id]['expired']);
        $this->assertSame(10.0, $summary[$standard->id]['alert_qty']);
        $this->assertSame(12.0, $summary[$batched->id]['available']);
        $this->assertSame(8.0, $summary[$batched->id]['expired']);
    }

    public function test_it_never_includes_another_business_products_or_documents(): void
    {
        [$business, $user] = $this->context('Primary business');
        [$otherBusiness, $otherUser] = $this->context('Other business');
        $product = $this->product($business, $user, 'Primary product', 7);
        $otherProduct = $this->product($otherBusiness, $otherUser, 'Other product', 99);
        $otherOrder = Order::create(['order_number' => 'SALE-OTHER-1', 'business_id' => $otherBusiness->id, 'created_by' => $otherUser->id, 'status' => 'Completed']);
        $otherOrder->items()->create(['product_id' => $otherProduct->id, 'quantity' => 99, 'price' => 1, 'total' => 99]);

        $summary = app(InventorySummaryService::class)->summaries($business->id, collect([$product]));

        $this->assertSame(7.0, $summary[$product->id]['available']);
        $this->assertSame(0.0, $summary[$product->id]['sold']);
        $this->assertArrayNotHasKey($otherProduct->id, $summary->all());
    }

    private function context(string $name): array
    {
        $business = Business::create(['business_name' => $name, 'business_type' => 'General']);
        $user = User::factory()->create(['business_id' => $business->id, 'role' => 'business_owner']);

        return [$business, $user];
    }

    private function product(Business $business, User $user, string $name, int $stock, bool $batchTracked = false): Product
    {
        return Product::create([
            'business_id' => $business->id,
            'name' => $name,
            'unit' => 'Piece',
            'stock_quantity' => $stock,
            'current_stock' => $stock,
            'has_batch_tracking' => $batchTracked,
            'retail_price' => 10,
            'wholesale_price' => 8,
            'status' => 'Active',
            'created_by' => $user->id,
        ]);
    }
}
