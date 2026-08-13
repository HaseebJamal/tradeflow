<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\PosController;
use App\Models\Business;
use App\Models\HeldPosSale;
use App\Models\PosRegister;
use App\Models\User;
use App\Services\PosDraftCartService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosResumeDraftGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('POS resume draft-guard integration coverage requires the dedicated MySQL testing database.');
        }

        parent::setUp();
    }

    public function test_empty_server_draft_allows_resume_for_the_current_business(): void
    {
        [$user, $register, $held] = $this->posContext();

        $controller = app(PosController::class);
        $request = Request::create('/', 'POST');
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => $user);
        $request->headers->set('Accept', 'application/json');

        $response = $controller->resume($request, $held);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Resumed', $held->fresh()->status);
    }

    public function test_active_server_draft_rejects_resume_even_when_a_crafted_request_claims_empty_cart(): void
    {
        [$user, $register, $held] = $this->posContext();
        $session = $this->app['session']->driver();
        app(PosDraftCartService::class)->sync($session, $user->business_id, $user->id, $register->id, [['id' => 99]]);

        $request = Request::create('/', 'POST', [
            'current_cart_item_count' => 0,
            'has_active_sale' => false,
        ]);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);

        try {
            app(PosController::class)->resume($request, $held);
            $this->fail('The active server draft must block resume.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Please hold or clear the current cart before resuming another sale.',
                $exception->errors()['current_sale'][0],
            );
        }

        $this->assertSame('Held', $held->fresh()->status);
    }

    public function test_another_tenants_hold_cannot_be_resumed(): void
    {
        [$user] = $this->posContext();
        $otherBusiness = Business::query()->create(['business_name' => 'Other Business', 'business_type' => 'General']);
        $otherUser = User::factory()->create(['business_id' => $otherBusiness->id]);
        $otherRegister = PosRegister::query()->create([
            'business_id' => $otherBusiness->id,
            'user_id' => $otherUser->id,
            'status' => 'Open',
            'opening_cash' => 0,
            'opened_at' => now(),
        ]);
        $foreignHold = HeldPosSale::query()->create([
            'business_id' => $otherBusiness->id,
            'pos_register_id' => $otherRegister->id,
            'user_id' => $otherUser->id,
            'hold_number' => 'HOLD-999991',
            'cart_payload' => [['id' => 1]],
            'checkout_payload' => [],
            'status' => 'Held',
            'held_at' => now(),
        ]);

        $request = Request::create('/', 'POST');
        $request->setLaravelSession($this->app['session']->driver());
        $request->setUserResolver(fn () => $user);

        try {
            app(PosController::class)->resume($request, $foreignHold);
            $this->fail('Cross-tenant resume must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame('Hold number not found.', $exception->errors()['held_sale'][0]);
        }
    }

    public function test_reholding_a_resumed_sale_updates_its_existing_hold(): void
    {
        [$user, $register, $held] = $this->posContext();
        $held->update(['status' => 'Resumed']);

        $updated = app(\App\Services\PosSaleService::class)->hold(
            $register,
            $user->business_id,
            $user->id,
            [['id' => 1, 'quantity' => 2]],
            [],
            '11',
            $held->id,
        );

        $this->assertSame($held->id, $updated->id);
        $this->assertSame('HOLD-000011', $updated->hold_number);
        $this->assertSame('Held', $updated->status);
        $this->assertSame(1, HeldPosSale::query()->where('business_id', $user->business_id)->count());
    }

    /** @return array{User, PosRegister, HeldPosSale} */
    private function posContext(): array
    {
        $business = Business::query()->create(['business_name' => 'POS Test Business', 'business_type' => 'General']);
        $user = User::factory()->create(['business_id' => $business->id]);
        $register = PosRegister::query()->create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'status' => 'Open',
            'opening_cash' => 0,
            'opened_at' => now(),
        ]);
        $held = HeldPosSale::query()->create([
            'business_id' => $business->id,
            'pos_register_id' => $register->id,
            'user_id' => $user->id,
            'hold_number' => 'HOLD-'.str_pad((string) random_int(1, 999990), 6, '0', STR_PAD_LEFT),
            'cart_payload' => [['id' => 1]],
            'checkout_payload' => [],
            'status' => 'Held',
            'held_at' => now(),
        ]);

        return [$user, $register, $held];
    }
}
