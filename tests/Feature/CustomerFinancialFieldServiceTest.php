<?php

namespace Tests\Feature;

use App\Services\CustomerFinancialFieldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CustomerFinancialFieldServiceTest extends TestCase
{
    public function test_blank_customer_financial_fields_are_normalized_to_zero(): void
    {
        $request = Request::create('/', 'POST', [
            'credit_limit' => '',
            'opening_balance' => null,
        ]);

        app(CustomerFinancialFieldService::class)->normalizeRequest($request, true);

        $this->assertSame(0, $request->input('credit_limit'));
        $this->assertSame(0, $request->input('opening_balance'));
    }

    public function test_customer_financial_fields_only_allow_whole_non_negative_numbers(): void
    {
        $service = app(CustomerFinancialFieldService::class);
        $rules = ['amount' => $service->wholeNumberRules()];

        $this->assertTrue(Validator::make(['amount' => '0'], $rules)->passes());
        $this->assertTrue(Validator::make(['amount' => '50000'], $rules)->passes());
        $this->assertTrue(Validator::make(['amount' => '1.5'], $rules)->fails());
        $this->assertTrue(Validator::make(['amount' => '-1'], $rules)->fails());
        $this->assertTrue(Validator::make(['amount' => '1e5'], $rules)->fails());
    }
}
