<?php

namespace Tests\Unit;

use App\Services\ProfitabilityBreakdownService;
use Tests\TestCase;

class ProfitabilityBreakdownServiceTest extends TestCase
{
    public function test_canonical_profitability_waterfall_reconciles_exactly(): void
    {
        $breakdown = ProfitabilityBreakdownService::calculate(
            grossSales: 100000,
            invoiceDiscounts: 5000,
            salesReturns: 5000,
            soldCogs: 55000,
            returnedCogs: 0,
            expenses: 15000,
        );

        $this->assertSame(90000.0, $breakdown['net_sales']);
        $this->assertSame(55000.0, $breakdown['cogs']);
        $this->assertSame(35000.0, $breakdown['gross_profit']);
        $this->assertSame(20000.0, $breakdown['net_profit']);
        $this->assertSame(38.89, $breakdown['gross_margin']);
    }

    public function test_returns_and_split_payment_composition_are_counted_once(): void
    {
        // Payment method rows are deliberately absent from the profit source.
        // A Rs 10,000 sale remains one sale regardless of Cash/Card split.
        $breakdown = ProfitabilityBreakdownService::calculate(10000, 500, 2000, 6000, 1200, 1000);

        $this->assertSame(7500.0, $breakdown['net_sales']);
        $this->assertSame(4800.0, $breakdown['cogs']);
        $this->assertSame(2700.0, $breakdown['gross_profit']);
        $this->assertSame(1700.0, $breakdown['net_profit']);
    }

    public function test_loss_and_zero_sales_states_are_explicit_and_safe(): void
    {
        $loss = ProfitabilityBreakdownService::calculate(50000, 0, 0, 40000, 0, 15000);
        $zero = ProfitabilityBreakdownService::calculate(0, 0, 0, 0, 0, 0);

        $this->assertSame(-5000.0, $loss['net_profit']);
        $this->assertSame(20.0, $loss['gross_margin']);
        $this->assertNull($zero['gross_margin']);
        $this->assertSame(0.0, $zero['net_profit']);
    }
}
