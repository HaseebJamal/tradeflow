<?php

namespace Tests\Unit;

use App\Services\BalanceAdjustmentService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BalanceAdjustmentServiceTest extends TestCase
{
    public function test_customer_and_supplier_adjustment_directions_preserve_a_non_negative_canonical_balance(): void
    {
        $customerDecrease = BalanceAdjustmentService::preview(10000, 'decrease_receivable', 2000);
        $customerIncrease = BalanceAdjustmentService::preview(10000, 'increase_receivable', 3000);
        $supplierDecrease = BalanceAdjustmentService::preview(20000, 'decrease_payable', 5000);
        $supplierIncrease = BalanceAdjustmentService::preview(20000, 'increase_payable', 5000);

        $this->assertSame(['adjustment' => -2000.0, 'new_balance' => 8000.0], $customerDecrease);
        $this->assertSame(['adjustment' => 3000.0, 'new_balance' => 13000.0], $customerIncrease);
        $this->assertSame(['adjustment' => -5000.0, 'new_balance' => 15000.0], $supplierDecrease);
        $this->assertSame(['adjustment' => 5000.0, 'new_balance' => 25000.0], $supplierIncrease);
    }

    public function test_adjustment_cannot_make_a_balance_negative_or_accept_zero(): void
    {
        try {
            BalanceAdjustmentService::preview(100, 'decrease_receivable', 101);
            $this->fail('Expected invalid decrease to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        BalanceAdjustmentService::preview(100, 'increase_payable', 0);
    }
}
