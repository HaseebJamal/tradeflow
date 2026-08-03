<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\PurchaseController;
use App\Models\PurchaseItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use ReflectionMethod;
use Tests\TestCase;

class PurchasePartOneWorkflowTest extends TestCase
{
    public function test_purchase_items_do_not_accept_selling_prices(): void
    {
        $this->assertNotContains('selling_price', (new PurchaseItem())->getFillable());
        $this->assertContains('unit_snapshot', (new PurchaseItem())->getFillable());
    }

    public function test_decimal_purchase_line_totals_are_calculated_server_side(): void
    {
        $controller = app(PurchaseController::class);
        $method = new ReflectionMethod($controller, 'lineAmounts');

        $amounts = $method->invoke($controller, [
            'quantity' => '1.250',
            'unit_cost' => '99.99',
            'discount_type' => 'percentage',
            'discount_value' => '10',
            'tax_type' => 'fixed',
            'tax_value' => '5.50',
        ]);

        $this->assertSame(124.99, $amounts['subtotal']);
        $this->assertSame(12.5, $amounts['discount']);
        $this->assertSame(117.99, $amounts['total']);
    }

    public function test_payment_status_is_derived_from_the_authoritative_total(): void
    {
        $controller = app(PurchaseController::class);
        $method = new ReflectionMethod($controller, 'resolveInitialPayment');

        $credit = $method->invoke($controller, ['payment_type' => 'Full Credit'], 1000.00);
        $partial = $method->invoke($controller, [
            'payment_type' => 'Partial Payment',
            'paid_amount' => '400',
            'payment_method' => 'Cash',
        ], 1000.00);
        $paid = $method->invoke($controller, [
            'payment_type' => 'Full Payment',
            'payment_method' => 'Cash',
        ], 1000.00);

        $this->assertSame('Unpaid', $credit['status']);
        $this->assertSame(1000.00, $credit['balance']);
        $this->assertSame('Partial', $partial['status']);
        $this->assertSame(600.00, $partial['balance']);
        $this->assertSame('Paid', $paid['status']);
        $this->assertSame(0.00, $paid['balance']);
    }

    public function test_formatted_purchase_cost_is_normalized_without_truncation(): void
    {
        $controller = app(PurchaseController::class);
        $normalize = new ReflectionMethod($controller, 'normalizeWholePurchaseMoney');
        $lineAmounts = new ReflectionMethod($controller, 'lineAmounts');

        $this->assertSame('7500', $normalize->invoke($controller, '7,500'));
        $this->assertSame('20000', $normalize->invoke($controller, 'Rs 20,000'));
        $this->assertSame('7500.00', $normalize->invoke($controller, '7,500.00'));

        $amounts = $lineAmounts->invoke($controller, [
            'quantity' => 23,
            'unit_cost' => $normalize->invoke($controller, '7,500'),
            'discount_type' => 'percentage',
            'discount_value' => 0,
            'tax_type' => 'percentage',
            'tax_value' => 0,
        ]);

        $this->assertSame(172500.0, $amounts['subtotal']);
        $this->assertSame(172500.0, $amounts['total']);
    }

    public function test_goods_receipts_preserve_the_auditable_receipt_quantities(): void
    {
        $this->assertContains('receiving_status', (new \App\Models\Purchase())->getFillable());
        $this->assertContains('accepted_quantity', (new GoodsReceiptItem())->getFillable());
        $this->assertContains('damaged_quantity', (new GoodsReceiptItem())->getFillable());
        $this->assertContains('rejected_quantity', (new GoodsReceiptItem())->getFillable());
        $this->assertContains('submission_token', (new GoodsReceipt())->getFillable());
    }
}
