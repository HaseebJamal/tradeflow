<?php

namespace Tests\Unit;

use App\Services\ReorderSuggestionService;
use PHPUnit\Framework\TestCase;

class ReorderSuggestionServiceTest extends TestCase
{
    public function test_it_calculates_reorder_suggestions_from_projected_stock(): void
    {
        $result = ReorderSuggestionService::calculate(8, 0, 20, 50);

        $this->assertSame(42.0, $result['suggested_quantity']);
        $this->assertSame('Below Reorder', $result['status']);
    }

    public function test_it_does_not_suggest_when_stock_is_at_or_above_reorder_level(): void
    {
        $result = ReorderSuggestionService::calculate(25, 0, 20, 50);

        $this->assertSame(0.0, $result['suggested_quantity']);
        $this->assertSame('Healthy', $result['status']);
    }

    public function test_it_marks_out_of_stock_and_accounts_for_open_incoming_stock(): void
    {
        $outOfStock = ReorderSuggestionService::calculate(0, 0, 20, 50);
        $withIncoming = ReorderSuggestionService::calculate(8, 20, 20, 50);

        $this->assertSame('Out of Stock', $outOfStock['status']);
        $this->assertSame(50.0, $outOfStock['suggested_quantity']);
        $this->assertSame(28.0, $withIncoming['projected_stock']);
        $this->assertSame(22.0, $withIncoming['suggested_quantity']);
    }

    public function test_it_uses_sellable_batch_stock_and_unreceived_paid_or_bonus_units(): void
    {
        // A batch-tracked product has 20 physical units but only five valid
        // units. The service receives that sellable quantity as its current
        // stock input; expired units cannot satisfy a reorder level.
        $batchStock = ReorderSuggestionService::calculate(5, 0, 20, 50);
        $partialReceipt = ReorderSuggestionService::calculate(8, 6, 20, 50);
        $bonusIncoming = ReorderSuggestionService::calculate(8, 12, 20, 50);

        $this->assertSame(45.0, $batchStock['suggested_quantity']);
        $this->assertSame(36.0, $partialReceipt['suggested_quantity']);
        $this->assertSame(30.0, $bonusIncoming['suggested_quantity']);
    }
}
