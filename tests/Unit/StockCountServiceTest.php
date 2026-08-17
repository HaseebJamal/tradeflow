<?php

namespace Tests\Unit;

use App\Services\StockCountService;
use Tests\TestCase;

class StockCountServiceTest extends TestCase
{
    public function test_variance_math_preserves_fractional_stock_precision(): void
    {
        $this->assertSame(0.0, StockCountService::variance(100, 100));
        $this->assertSame(-4.0, StockCountService::variance(100, 96));
        $this->assertSame(5.0, StockCountService::variance(100, 105));
        $this->assertSame(-0.125, StockCountService::variance(10.5, 10.375));
    }
}
