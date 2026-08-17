<?php

namespace Tests\Unit;

use App\Services\FinanceCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosLineDiscountTest extends TestCase
{
    public function test_percentage_and_fixed_line_discounts_use_the_gross_line_total(): void
    {
        $finance = app(FinanceCalculator::class);

        $percentage = $finance->calculatePosLineAmounts(2, 200, 'percentage', 10);
        $fixed = $finance->calculatePosLineAmounts(2, 200, 'fixed', 50);

        $this->assertSame(400.0, $percentage['lineSubtotal']);
        $this->assertSame(40.0, $percentage['discountAmount']);
        $this->assertSame(360.0, $percentage['lineTotal']);
        $this->assertSame(50.0, $fixed['discountAmount']);
        $this->assertSame(350.0, $fixed['lineTotal']);
    }

    public function test_invalid_fixed_or_percentage_line_discounts_are_rejected(): void
    {
        $finance = app(FinanceCalculator::class);

        $this->expectException(ValidationException::class);
        $finance->calculatePosLineAmounts(2, 200, 'percentage', 101);
    }
}
