<?php

namespace Tests\Unit;

use App\Services\StockMovementAnalyticsService;
use Carbon\Carbon;
use Tests\TestCase;

class StockMovementAnalyticsServiceTest extends TestCase
{
    public function test_returns_reduce_net_quantity_without_creating_negative_velocity(): void
    {
        $this->assertSame(4.0, StockMovementAnalyticsService::netQuantity(6, 2));
        $this->assertSame(0.0, StockMovementAnalyticsService::netQuantity(2, 3));
    }

    public function test_classification_uses_relative_velocity_and_reliable_stock_age(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16 12:00:00', config('app.timezone')));
        try {
            $rows = collect([
                $this->row(1, 100, 200, '2026-08-15', '2026-01-01'),
                $this->row(2, 80, 50, '2026-08-14', '2026-01-01'),
                $this->row(3, 70, 2, '2026-08-13', '2026-01-01'),
                $this->row(4, 60, 1, '2026-08-12', '2026-01-01'),
                $this->row(5, 30, 0, '2026-04-01', '2026-01-01'),
                $this->row(6, 20, 0, null, '2026-08-12'),
                $this->row(7, 0, 0, '2026-01-01', '2026-01-01'),
            ]);

            $statuses = app(StockMovementAnalyticsService::class)->classify($rows, 90)->keyBy('product_id')->map->movement_status;

            $this->assertSame('Fast Moving', $statuses[1]);
            $this->assertSame('Slow Moving', $statuses[4]);
            $this->assertSame('Dead Stock', $statuses[5]);
            $this->assertSame('No Sales History', $statuses[6]);
            $this->assertSame('Normal', $statuses[7], 'Zero stock must never be classified as dead stock.');
        } finally {
            Carbon::setTestNow();
        }
    }

    private function row(int $id, float $stock, float $sold, ?string $lastSale, ?string $stockAge): object
    {
        return (object) [
            'product_id' => $id,
            'current_stock' => $stock,
            'qty_sold' => $sold,
            'last_sale_at' => $lastSale ? Carbon::parse($lastSale, config('app.timezone')) : null,
            'stock_age_at' => $stockAge ? Carbon::parse($stockAge, config('app.timezone')) : null,
        ];
    }
}
