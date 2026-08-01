<?php

namespace Tests\Unit;

use App\Http\Requests\Business\StorePosSaleRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StorePosSaleRequestTest extends TestCase
{
    public function test_cash_tender_allows_whole_number_overpayment_but_rejects_decimals(): void
    {
        $rules = (new StorePosSaleRequest())->rules();

        $cashTender = Validator::make($this->payload('2000'), $rules);
        $decimalTender = Validator::make($this->payload('2000.50'), $rules);

        $this->assertFalse($cashTender->fails());
        $this->assertTrue($decimalTender->fails());
    }

    private function payload(string $cashReceived): array
    {
        return [
            'payment_type' => 'Cash',
            'payment_method' => 'Cash',
            'cash_received' => $cashReceived,
            'items' => [[
                'product_id' => 1,
                'quantity' => 1,
            ]],
        ];
    }
}
