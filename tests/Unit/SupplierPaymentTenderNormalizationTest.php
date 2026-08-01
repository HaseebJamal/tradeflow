<?php

namespace Tests\Unit;

use App\Http\Controllers\Business\PurchaseController;
use ReflectionMethod;
use Tests\TestCase;

class SupplierPaymentTenderNormalizationTest extends TestCase
{
    public function test_formatted_supplier_cash_tender_is_normalized_before_validation(): void
    {
        $method = new ReflectionMethod(app(PurchaseController::class), 'normalizePaymentTender');
        $controller = app(PurchaseController::class);

        $this->assertSame('1700', $method->invoke($controller, '1,700'));
        $this->assertSame('20340', $method->invoke($controller, 'Rs 20,340'));
        $this->assertSame('1700.50', $method->invoke($controller, 'Rs 1,700.50'));
    }
}
