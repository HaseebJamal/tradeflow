<?php

namespace Tests\Unit;

use App\Models\Subscription;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionLifecyclePaidAccessTest extends TestCase
{
    public function test_paid_remaining_excludes_manual_extension_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-11 12:00:00', config('app.timezone')));

        try {
            $subscription = new class extends Subscription {
                public function extraAccessDays(): int
                {
                    return 4;
                }

                public function effectivePaidAccessEnd(): ?Carbon
                {
                    return $this->ends_at?->copy()->addDays($this->extraAccessDays());
                }
            };
            $subscription->forceFill([
                'status' => 'Active',
                'payment_status' => 'Received',
                'starts_at' => '2026-08-11',
                'ends_at' => '2026-10-10',
            ]);

            $state = app(SubscriptionLifecycleService::class)->state($subscription);

            $this->assertSame(60, $state['paid_days_remaining']);
            $this->assertSame(4, $state['extra_access_days']);
            $this->assertSame('2026-10-14', $state['effective_access_end']?->toDateString());
            $this->assertSame(64, $state['days_remaining']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
