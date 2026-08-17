<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Payment;
use App\Models\PosRegister;
use App\Models\SalesReturn;
use App\Models\User;
use App\Services\PosSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosRegisterReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('POS register reconciliation integration coverage requires the dedicated MySQL testing database.');
        }

        parent::setUp();
    }

    public function test_close_register_persists_the_authoritative_shift_reconciliation(): void
    {
        $business = Business::query()->create(['business_name' => 'Shift Test Business', 'business_type' => 'General']);
        $user = User::factory()->create(['business_id' => $business->id, 'role' => 'business_owner']);
        $this->actingAs($user);
        $register = PosRegister::query()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'status' => 'Open',
            'opening_cash' => 100,
            'opened_at' => now()->subHour(),
        ]);

        Payment::query()->create([
            'business_id' => $business->id,
            'pos_register_id' => $register->id,
            'method' => 'Cash',
            'amount' => 500,
            'payment_date' => now()->toDateString(),
            'status' => 'Paid',
        ]);
        $order = $business->orders()->create(['order_number' => 'SHIFT-ORDER-1']);
        SalesReturn::query()->create([
            'business_id' => $business->id,
            'return_number' => 'SHIFT-RETURN-1',
            'order_id' => $order->id,
            'pos_register_id' => $register->id,
            'processed_by' => $user->id,
            'refund_amount' => 75,
            'refund_method' => 'Cash',
            'reason' => 'Customer return',
            'returned_at' => now(),
        ]);

        $service = app(PosSaleService::class);
        $service->recordCashMovement($register, $business->id, $user->id, ['type' => 'Cash In', 'amount' => 200, 'reason' => 'Owner float']);
        $service->recordCashMovement($register, $business->id, $user->id, ['type' => 'Cash Out', 'amount' => 40, 'reason' => 'Bank deposit']);
        $closed = $service->closeRegister($register, $business->id, $user->id, ['closing_cash' => 670, 'closing_note' => 'Counted by cashier']);

        $this->assertSame('Closed', $closed->status);
        $this->assertEquals(500, $closed->cash_sales);
        $this->assertEquals(75, $closed->cash_refunds);
        $this->assertEquals(200, $closed->cash_in);
        $this->assertEquals(40, $closed->cash_out);
        $this->assertEquals(685, $closed->expected_cash);
        $this->assertEquals(-15, $closed->variance);
    }
}
