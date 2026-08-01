<?php

namespace Tests\Unit;

use App\Http\Middleware\RejectNegativeNumericInput;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RejectNegativeNumericInputTest extends TestCase
{
    public function test_purchase_date_fields_are_not_treated_as_numeric_amounts(): void
    {
        $request = Request::create('/business/purchases', 'POST', [
            'payment_date' => '2026-08-01',
            'due_date' => '2026-08-31',
            'items' => [[
                'quantity' => '20',
                'unit_cost' => '70',
                'discount_value' => '0',
                'tax_value' => '0',
            ]],
            'paid_amount' => '1400',
        ]);

        $response = app(RejectNegativeNumericInput::class)->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_purchase_decimal_amounts_remain_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $request = Request::create('/business/purchases', 'POST', [
            'items' => [['unit_cost' => '70.5']],
        ]);

        app(RejectNegativeNumericInput::class)->handle($request, fn () => response('ok'));
    }
}
