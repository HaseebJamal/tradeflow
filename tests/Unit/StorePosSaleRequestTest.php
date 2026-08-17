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

    public function test_formatted_pos_money_values_are_normalized_before_validation(): void
    {
        $request = StorePosSaleRequest::create('/', 'POST', [
            ...$this->payload('9,350'),
            'items' => [[
                'product_id' => 1,
                'quantity' => 3,
                'unit_price' => '8,500',
                'discount_rate' => '0',
                'tax_rate' => '0',
            ]],
        ]);

        $method = new \ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('9350', $request->input('cash_received'));
        $this->assertSame('8500', $request->input('items.0.unit_price'));
    }

    public function test_price_override_reason_is_optional_for_standard_prices_but_limited_when_present(): void
    {
        $rules = (new StorePosSaleRequest())->rules();
        $valid = Validator::make([
            ...$this->payload('2000'),
            'items' => [[
                'product_id' => 1,
                'quantity' => 1,
                'unit_price' => 500,
                'price_override_reason' => 'Approved customer price match.',
            ]],
        ], $rules);
        $tooLong = Validator::make([
            ...$this->payload('2000'),
            'items' => [[
                'product_id' => 1,
                'quantity' => 1,
                'unit_price' => 500,
                'price_override_reason' => str_repeat('x', 501),
            ]],
        ], $rules);

        $this->assertFalse($valid->fails());
        $this->assertTrue($tooLong->fails());
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
