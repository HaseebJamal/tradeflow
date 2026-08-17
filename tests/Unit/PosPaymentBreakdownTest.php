<?php

namespace Tests\Unit;

use App\Services\PosPaymentBreakdown;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosPaymentBreakdownTest extends TestCase
{
    public function test_full_split_payment_keeps_each_method_separate(): void
    {
        $breakdown = app(PosPaymentBreakdown::class)->calculate([
            'payment_type' => 'Split',
            'split_payments' => [
                ['method' => 'Cash', 'amount' => 4000],
                ['method' => 'Card', 'amount' => 3000, 'reference' => 'CARD-12'],
                ['method' => 'Jazz Cash', 'amount' => 3000, 'reference' => 'JC-88'],
            ],
        ], 10000);

        $this->assertSame(10000, $breakdown['paid']);
        $this->assertSame(0, $breakdown['balance']);
        $this->assertSame(0, $breakdown['change']);
        $this->assertSame(4000, $breakdown['lines'][0]['amount']);
        $this->assertSame('Card', $breakdown['lines'][1]['method']);
    }

    public function test_partial_split_payment_leaves_the_correct_balance(): void
    {
        $breakdown = app(PosPaymentBreakdown::class)->calculate([
            'payment_type' => 'Split',
            'split_payments' => [
                ['method' => 'Cash', 'amount' => 3000],
                ['method' => 'Card', 'amount' => 2000],
            ],
        ], 10000);

        $this->assertSame(5000, $breakdown['paid']);
        $this->assertSame(5000, $breakdown['balance']);
    }

    public function test_cash_tender_can_create_change_without_overpaying_the_invoice(): void
    {
        $breakdown = app(PosPaymentBreakdown::class)->calculate([
            'payment_type' => 'Split',
            'split_payments' => [
                ['method' => 'Cash', 'amount' => 700],
                ['method' => 'Card', 'amount' => 400],
            ],
        ], 1000);

        $this->assertSame(1000, $breakdown['paid']);
        $this->assertSame(100, $breakdown['change']);
        $this->assertSame(600, $breakdown['lines'][0]['amount']);
    }

    public function test_non_cash_overpayment_and_duplicate_methods_are_rejected(): void
    {
        $service = app(PosPaymentBreakdown::class);

        try {
            $service->calculate(['payment_type' => 'Split', 'split_payments' => [['method' => 'Card', 'amount' => 1100]]], 1000);
            $this->fail('Expected non-cash overpayment to be rejected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(ValidationException::class);
        $service->calculate(['payment_type' => 'Split', 'split_payments' => [['method' => 'Cash', 'amount' => 500], ['method' => 'Cash', 'amount' => 500]]], 1000);
    }
}
