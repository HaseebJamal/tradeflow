<?php

namespace Tests\Unit;

use App\Models\PlatformPayment;
use App\Models\Subscription;
use App\Services\CompanyPermissionService;
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

    public function test_future_paid_access_is_scheduled_not_restricted_or_expired(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-12 12:00:00', config('app.timezone')));

        try {
            $subscription = new class extends Subscription {
                public function extraAccessDays(): int
                {
                    return 0;
                }

                public function effectivePaidAccessEnd(): ?Carbon
                {
                    return $this->ends_at?->copy();
                }
            };
            $subscription->forceFill([
                'status' => 'Pending',
                'payment_status' => 'Received',
                'starts_at' => '2026-08-17',
                'ends_at' => '2026-09-16',
            ]);

            $state = app(SubscriptionLifecycleService::class)->state($subscription);

            $this->assertSame('Pending', $state['status']);
            $this->assertTrue($state['is_scheduled']);
            $this->assertTrue($state['is_paid_access_scheduled']);
            $this->assertFalse($state['is_expired']);
            $this->assertFalse($state['is_paid_access_active']);
            $this->assertFalse($state['can_access_business']);
            $this->assertNull($state['paid_days_remaining']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_current_paid_cycle_remains_active_with_its_remaining_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 12:00:00', config('app.timezone')));

        try {
            $subscription = new class extends Subscription {
                public function extraAccessDays(): int
                {
                    return 0;
                }

                public function effectivePaidAccessEnd(): ?Carbon
                {
                    return $this->ends_at?->copy();
                }
            };
            $subscription->forceFill([
                'status' => 'Active',
                'payment_status' => 'Received',
                'starts_at' => '2026-08-13',
                'ends_at' => '2026-08-17',
            ]);

            $lifecycle = app(SubscriptionLifecycleService::class);
            $state = $lifecycle->state($subscription);

            $this->assertSame('Active', $state['status']);
            $this->assertTrue($lifecycle->hasActivePaidCycle($subscription));
            $this->assertTrue($state['can_access_business']);
            $this->assertSame(4, $state['paid_days_remaining']);
            $this->assertSame('2026-08-17', $state['paid_access_end']?->toDateString());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_paid_expiry_alert_is_hidden_when_a_continuous_paid_renewal_is_secured(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00', config('app.timezone')));

        try {
            $subscription = new class extends Subscription {
                public function extraAccessDays(): int
                {
                    return 0;
                }

                public function effectivePaidAccessEnd(): ?Carbon
                {
                    return $this->ends_at?->copy();
                }
            };
            $subscription->forceFill([
                'status' => 'Active',
                'payment_status' => 'Received',
                'starts_at' => '2026-08-15',
                'ends_at' => '2026-08-19',
            ]);
            $upcoming = new PlatformPayment();
            $upcoming->forceFill([
                'status' => 'Received',
                'period_starts_at' => '2026-08-20',
                'period_ends_at' => '2026-09-19',
            ]);
            $lifecycle = new class(app(CompanyPermissionService::class), $upcoming) extends SubscriptionLifecycleService {
                public function __construct(CompanyPermissionService $permissions, private readonly ?PlatformPayment $upcoming)
                {
                    parent::__construct($permissions);
                }

                public function upcomingPaidCycle(Subscription $subscription): ?PlatformPayment
                {
                    return $this->upcoming;
                }
            };

            $state = $lifecycle->state($subscription);

            $this->assertTrue($state['has_secured_upcoming_paid_renewal']);
            $this->assertFalse($state['is_paid_access_expiring']);
            $this->assertFalse($state['is_expiring_soon']);
            $this->assertTrue($lifecycle->hasSecuredUpcomingPaidRenewal($subscription));
            $this->assertNull($lifecycle->dashboardExpiryAlert($state));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pending_future_payment_does_not_hide_paid_expiry_alert(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 12:00:00', config('app.timezone')));

        try {
            $subscription = new class extends Subscription {
                public function extraAccessDays(): int
                {
                    return 0;
                }

                public function effectivePaidAccessEnd(): ?Carbon
                {
                    return $this->ends_at?->copy();
                }
            };
            $subscription->forceFill([
                'status' => 'Active',
                'payment_status' => 'Received',
                'starts_at' => '2026-08-15',
                'ends_at' => '2026-08-19',
            ]);
            $upcoming = new PlatformPayment();
            $upcoming->forceFill([
                'status' => 'Pending',
                'period_starts_at' => '2026-08-20',
                'period_ends_at' => '2026-09-19',
            ]);
            $lifecycle = new class(app(CompanyPermissionService::class), $upcoming) extends SubscriptionLifecycleService {
                public function __construct(CompanyPermissionService $permissions, private readonly ?PlatformPayment $upcoming)
                {
                    parent::__construct($permissions);
                }

                public function upcomingPaidCycle(Subscription $subscription): ?PlatformPayment
                {
                    return $this->upcoming;
                }
            };

            $state = $lifecycle->state($subscription);

            $this->assertFalse($lifecycle->hasSecuredUpcomingPaidRenewal($subscription));
            $this->assertNotNull($lifecycle->dashboardExpiryAlert($state));
        } finally {
            Carbon::setTestNow();
        }
    }
}
