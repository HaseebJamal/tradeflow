<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\SupplierController;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class SupplierOpeningBalanceTest extends TestCase
{
    public function test_blank_supplier_opening_balance_is_normalized_to_zero(): void
    {
        $controller = app(SupplierController::class);
        $method = new ReflectionMethod($controller, 'normaliseSupplierFields');
        $method->setAccessible(true);

        $normalized = $method->invoke($controller, [
            'supplier_name' => 'Supplier',
            'opening_balance' => null,
        ]);

        $this->assertSame(0, $normalized['opening_balance']);
    }
}
