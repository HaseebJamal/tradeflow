<?php

namespace Tests\Unit;

use App\Services\ProductPerformanceAnalyticsService;
use Tests\TestCase;

class ProductPerformanceAnalyticsServiceTest extends TestCase
{
    public function test_actual_line_revenue_discounts_returns_and_historical_cogs_reconcile(): void
    {
        // The line-revenue input is the saved line_total: it already reflects
        // line discounts and actual POS price overrides, not today's price.
        $metrics = ProductPerformanceAnalyticsService::calculateMetrics(
            soldQuantity: 100,
            lineRevenue: 20000,
            allocatedOrderDiscount: 1000,
            soldCogs: 12000,
            returnedQuantity: 10,
            returnValue: 2000,
            returnedCogs: 1200,
        );

        $this->assertSame(17000.0, $metrics['net_sales']);
        $this->assertSame(10800.0, $metrics['cogs']);
        $this->assertSame(6200.0, $metrics['gross_profit']);
        $this->assertSame(36.47, $metrics['gross_margin']);
        $this->assertSame(10.0, $metrics['return_rate']);
    }

    public function test_loss_making_and_zero_revenue_metrics_are_not_hidden_or_divided_by_zero(): void
    {
        $loss = ProductPerformanceAnalyticsService::calculateMetrics(10, 5000, 0, 5500, 0, 0, 0);
        $noSales = ProductPerformanceAnalyticsService::calculateMetrics(0, 0, 0, 0, 0, 0, 0);

        $this->assertSame(-500.0, $loss['gross_profit']);
        $this->assertSame(-10.0, $loss['gross_margin']);
        $this->assertNull($noSales['gross_margin']);
        $this->assertNull($noSales['return_rate']);
    }
}
