<?php

namespace Tests\Unit;

use App\Models\GoodsReceiptItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturnItem;
use App\Services\PurchaseReceivingService;
use ReflectionMethod;
use Tests\TestCase;

class PurchaseBonusQuantityTest extends TestCase
{
    public function test_bonus_quantity_fields_are_explicitly_auditable(): void
    {
        $this->assertContains('free_quantity', (new PurchaseItem())->getFillable());
        $this->assertContains('free_accepted_quantity', (new GoodsReceiptItem())->getFillable());
        $this->assertContains('paid_quantity', (new PurchaseReturnItem())->getFillable());
        $this->assertContains('free_quantity', (new PurchaseReturnItem())->getFillable());
    }

    public function test_paid_cost_is_allocated_before_bonus_stock(): void
    {
        $service = app(PurchaseReceivingService::class);
        $allocation = new ReflectionMethod($service, 'allocationFor');

        $tenPaidOneFree = $allocation->invoke($service, 10.0, 11.0, 0.0, 0.0);
        $this->assertSame(10.0, $tenPaidOneFree['paid_accepted']);
        $this->assertSame(1.0, $tenPaidOneFree['free_accepted']);

        $tenPaidFiveFree = $allocation->invoke($service, 10.0, 15.0, 0.0, 0.0);
        $this->assertSame(10.0, $tenPaidFiveFree['paid_accepted']);
        $this->assertSame(5.0, $tenPaidFiveFree['free_accepted']);
    }

    public function test_rejected_bonus_units_have_no_paid_value_allocation(): void
    {
        $service = app(PurchaseReceivingService::class);
        $allocation = new ReflectionMethod($service, 'allocationFor');

        $result = $allocation->invoke($service, 0.0, 0.0, 0.0, 1.0);

        $this->assertSame(0.0, $result['paid_rejected']);
        $this->assertSame(1.0, $result['free_rejected']);
    }

    public function test_receipt_completion_includes_bonus_quantity(): void
    {
        $service = app(PurchaseReceivingService::class);
        $purchase = new Purchase(['status' => 'Confirmed']);
        $item = new PurchaseItem([
            'quantity' => 10,
            'free_quantity' => 1,
            'received_quantity' => 10,
            'damaged_quantity' => 0,
            'rejected_quantity' => 0,
        ]);
        $purchase->setRelation('items', collect([$item]));

        $partial = $service->state($purchase);
        $this->assertSame(1.0, $partial['pending_qty']);
        $this->assertSame('Partially Received', $partial['receipt_status']);

        $item->received_quantity = 11;
        $complete = $service->state($purchase);
        $this->assertSame(0.0, $complete['pending_qty']);
        $this->assertSame('Fully Received', $complete['receipt_status']);
    }
}
