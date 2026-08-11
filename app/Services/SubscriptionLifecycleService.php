<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionStatusNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The single source of truth for effective subscription and trial state.
 *
 * Dates remain stored on Subscription; this service only derives display and
 * access state, and persists a transition when an entitlement has actually
 * crossed its end date.
 */
class SubscriptionLifecycleService
{
    // Keep the legacy constant for callers that only deal with trial access.
    // These thresholds are the single source for both the live dashboard
    // reminder and scheduled lifecycle-notification milestones.
    public const EXPIRING_SOON_DAYS = 7;
    public const TRIAL_EXPIRING_SOON_DAYS = 7;
    public const PAID_EXPIRING_SOON_DAYS = 5;

    public function __construct(private readonly CompanyPermissionService $permissions)
    {
    }

    /** @return array<string, mixed> */
    public function forBusiness(Business $business, bool $includeUsage = false): array
    {
        $subscription = $business->relationLoaded('subscription')
            ? $business->subscription
            : $business->subscription()->with('plan')->first();

        if ($subscription && ! $subscription->relationLoaded('plan')) {
            $subscription->load('plan');
        }

        if ($subscription) {
            $subscription = $this->synchronize($subscription);
        }

        return $this->state($subscription, $includeUsage);
    }

    /**
     * Keep persisted lifecycle statuses aligned with real dates. This is
     * idempotent and safe to invoke from middleware, dashboard, or a command.
     */
    public function synchronize(Subscription $subscription, bool $dispatchNotifications = false): Subscription
    {
        $subscription->loadMissing(['plan', 'business.owner']);
        $state = $this->state($subscription);
        $current = $subscription->status;
        $target = $current;

        // Suspensions and cancellations are deliberate administrative states;
        // never reopen those workspaces merely because a stored period exists.
        if (in_array($current, ['Suspended', 'Cancelled'], true)) {
            $target = $current;
        } elseif ($state['is_scheduled']) {
            $target = 'Pending';
        } elseif ($state['is_expired']) {
            $target = 'Expired';
        } elseif ($state['is_active_period']) {
            $target = $state['is_trial'] ? 'Trial' : ($state['is_expiring_soon'] ? 'Expiring' : 'Active');
        }

        if ($target !== $current) {
            $subscription->update(['status' => $target]);
            $subscription->refresh()->load('plan', 'business.owner');
            $state = $this->state($subscription);
        }

        if ($dispatchNotifications && $state['end_date']) {
            $this->dispatchLifecycleNotifications($subscription, $state);
        }

        return $subscription;
    }

    public function synchronizeAll(bool $dispatchNotifications = true): void
    {
        Subscription::with(['plan', 'business.owner'])
            // Include Expired records so a newly-recorded paid period with a
            // future stored end can be promoted from an old trial expiry
            // before filters and table presentation are evaluated.
            ->whereIn('status', ['Pending', 'Trial', 'Active', 'Expiring', 'Expired'])
            ->chunkById(100, function ($subscriptions) use ($dispatchNotifications): void {
                foreach ($subscriptions as $subscription) {
                    $this->synchronize($subscription, $dispatchNotifications);
                }
            });
    }

    /**
     * Limit platform access work scans to subscriptions that can plausibly be
     * expiring, expired, or restricted. Callers must still run `state()` on
     * each result: this query is only a performance prefilter, while this
     * service remains the source of truth for the final lifecycle decision.
     *
     * @return Builder<Business>
     */
    public function attentionCandidateBusinesses(): Builder
    {
        $trialCutoff = now(config('app.timezone'))
            ->addDays(self::TRIAL_EXPIRING_SOON_DAYS)
            ->toDateString();
        $paidCutoff = now(config('app.timezone'))
            ->addDays(self::PAID_EXPIRING_SOON_DAYS)
            ->toDateString();

        return Business::query()->where(function (Builder $businesses) use ($trialCutoff, $paidCutoff): void {
            // A business without an access record is already restricted.
            $businesses->whereDoesntHave('subscription')
                ->orWhereHas('subscription', function (Builder $subscription) use ($trialCutoff, $paidCutoff): void {
                    $subscription->whereIn('status', ['Pending', 'Expiring', 'Expired', 'Suspended', 'Cancelled'])
                        ->orWhere(function (Builder $paidAccess) use ($paidCutoff): void {
                            $paidAccess->where('payment_status', 'Received')
                                ->whereNotNull('ends_at')
                                ->where('ends_at', '<=', $paidCutoff);
                        })
                        ->orWhere(function (Builder $trialAccess) use ($trialCutoff): void {
                            $trialAccess->where(function (Builder $paymentStatus): void {
                                $paymentStatus->whereNull('payment_status')
                                    ->orWhere('payment_status', '!=', 'Received');
                            })
                                ->whereNotNull('trial_end_at')
                                ->where('trial_end_at', '<=', $trialCutoff);
                        });
                });
        });
    }

    /** @return array<string, mixed> */
    public function state(?Subscription $subscription, bool $includeUsage = false): array
    {
        $now = now(config('app.timezone'));
        // A paid period takes priority over the historic trial period. Its
        // financial end remains on Subscription, while complimentary days are
        // summed separately by the access-extension ledger.
        $hasPaidPeriod = (bool) ($subscription
            && $subscription->payment_status === 'Received'
            && $subscription->starts_at
            && $subscription->ends_at);
        $hasTrialPeriod = (bool) ($subscription
            && $subscription->trial_start_at
            && $subscription->trial_end_at);
        $isTrial = ! $hasPaidPeriod && $hasTrialPeriod;
        $extraAccessDays = $hasPaidPeriod ? $subscription->extraAccessDays() : 0;
        $effectivePaidEnd = $hasPaidPeriod ? $subscription->effectivePaidAccessEnd() : null;
        $paidDurationDays = $hasPaidPeriod
            ? $subscription->starts_at->diffInDays($subscription->ends_at)
            : null;
        $startDate = $hasPaidPeriod
            ? $subscription?->starts_at
            : ($hasTrialPeriod ? $subscription?->trial_start_at : null);
        $endDate = $hasPaidPeriod
            ? $effectivePaidEnd
            : ($hasTrialPeriod ? $subscription?->trial_end_at : null);
        $startBoundary = $startDate
            ? Carbon::parse($startDate->toDateString(), config('app.timezone'))->startOfDay()
            : null;
        $isScheduled = $subscription && $startBoundary && $now->lt($startBoundary);
        // Subscription trial/access columns are calendar dates. A stored end
        // date remains valid through that local calendar day, then expires
        // exactly once at the following end-of-day boundary.
        $expiryBoundary = $endDate
            ? Carbon::parse($endDate->toDateString(), config('app.timezone'))->endOfDay()
            : null;
        // Financial paid time is deliberately measured against the original
        // paid end, never the effective access end. Complimentary access can
        // keep a workspace available after this reaches zero, but it must not
        // make the paid/billing period appear longer.
        $paidExpiryBoundary = $hasPaidPeriod
            ? Carbon::parse($subscription->ends_at->toDateString(), config('app.timezone'))->endOfDay()
            : null;
        // Date-only subscription fields deliberately remain valid through the
        // local end of their stored end date. Use the actual local current
        // time for display and status; never derive remaining time from start.
        $daysRemaining = $endDate && ! $isScheduled
            ? max(0, (int) $now->diffInDays($expiryBoundary, false))
            : null;
        $paidDaysRemaining = $paidExpiryBoundary && ! $isScheduled
            ? max(0, (int) $now->diffInDays($paidExpiryBoundary, false))
            : null;
        // `end_now` and a reduction that consumes all remaining days store
        // today's date (never yesterday) and mark the record Expired. Keep
        // that explicit immediate restriction while ordinary same-day ends
        // continue to display as "Ends today" through local end-of-day.
        $wasEndedImmediately = $subscription
            && $subscription->status === 'Expired'
            && $endDate
            && $endDate->isSameDay($now);
        $isExpired = (bool) ($subscription
            && ! $isScheduled
            && ($wasEndedImmediately || ($expiryBoundary && $now->gte($expiryBoundary))));
        $effectiveStatus = in_array($subscription?->status, ['Suspended', 'Cancelled'], true)
            ? $subscription?->status
            : ($isScheduled
                ? 'Pending'
                : ($isExpired
                    ? 'Expired'
                    : ($isTrial ? 'Trial' : ($hasPaidPeriod ? 'Active' : $subscription?->status))));
        $warningDays = $isTrial ? self::TRIAL_EXPIRING_SOON_DAYS : self::PAID_EXPIRING_SOON_DAYS;
        $isExpiringSoon = ! $isExpired
            && $daysRemaining !== null
            && $daysRemaining >= 0
            && $daysRemaining <= $warningDays
            && in_array($effectiveStatus, ['Trial', 'Active', 'Expiring'], true);

        $usage = null;
        if ($includeUsage && $subscription?->business_id) {
            $usage = [
                'products' => Product::where('business_id', $subscription->business_id)->count(),
                'orders' => Order::where('business_id', $subscription->business_id)->count(),
                'staff' => User::where('business_id', $subscription->business_id)
                    ->where('role', '!=', 'business_owner')->where('status', '!=', 'archived')->count(),
            ];
        }

        return [
            'subscription' => $subscription,
            'plan' => $subscription?->plan,
            'status' => $effectiveStatus,
            // Explicit effective-access fields keep dashboard/banner consumers
            // independent from notification history and from trial-only dates.
            'effective_access_type' => $hasPaidPeriod ? 'paid' : ($hasTrialPeriod ? 'trial' : null),
            'is_paid_access_active' => (bool) $hasPaidPeriod && ! $isScheduled && ! $isExpired,
            'paid_access_start' => $hasPaidPeriod ? $subscription?->starts_at : null,
            'paid_access_end' => $hasPaidPeriod ? $subscription?->ends_at : null,
            'paid_duration_days' => $paidDurationDays,
            'extra_access_days' => $extraAccessDays,
            'effective_access_end' => $hasPaidPeriod ? $effectivePaidEnd : null,
            'paid_days_remaining' => $paidDaysRemaining,
            'is_trial' => (bool) $isTrial,
            'trial_start' => $subscription?->trial_start_at,
            'trial_end' => $subscription?->trial_end_at,
            'subscription_start' => $subscription?->starts_at,
            // Retain the financial paid end separately from the effective end
            // so payment/receipt consumers never absorb complimentary days.
            'subscription_end' => $subscription?->ends_at,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'billing_cycle' => $subscription?->billing_cycle,
            'payment_status' => $subscription?->payment_status ?? 'Pending',
            'days_remaining' => $isExpired ? 0 : $daysRemaining,
            'warning_days' => $warningDays,
            'is_scheduled' => (bool) $isScheduled,
            'is_active_period' => ! $isScheduled && ! $isExpired && in_array($effectiveStatus, ['Trial', 'Active', 'Expiring'], true),
            'is_expiring_soon' => $isExpiringSoon,
            'is_paid_access_expiring' => (bool) $hasPaidPeriod && $isExpiringSoon,
            'is_expired' => (bool) $isExpired || $effectiveStatus === 'Expired',
            // A workspace needs an actual, current trial or paid access record.
            // Legacy plan records remain attached for audit history only; they
            // never define an active business's access or feature limits.
            'can_access_business' => (bool) $subscription && ! $isScheduled && ! $isExpired && in_array($effectiveStatus, ['Trial', 'Active', 'Expiring'], true),
            'limits' => null,
            'usage' => $usage,
        ];
    }

    /**
     * Build the dynamic workspace reminder from the already-calculated access
     * state. This deliberately has no persistence side effects: lifecycle
     * milestone notifications are created only by synchronizeAll().
     *
     * @param array<string, mixed> $state
     * @return array<string, mixed>|null
     */
    public function dashboardExpiryAlert(array $state): ?array
    {
        if (! $state['can_access_business'] || ! $state['is_expiring_soon'] || ! $state['end_date']) {
            return null;
        }

        $isTrial = (bool) $state['is_trial'];
        $kind = $isTrial ? 'free trial' : 'paid access';
        $days = max(0, (int) $state['days_remaining']);
        $endDate = $state['end_date'];
        $timing = match ($days) {
            0 => "Your {$kind} ends today.",
            1 => "Your {$kind} ends tomorrow.",
            default => "Your {$kind} expires in {$days} days.",
        };

        return [
            'kind' => $kind,
            'title' => $isTrial ? 'Free trial ending soon' : 'Paid access ending soon',
            'message' => $timing,
            'days_remaining' => $days,
            'ends_at' => $endDate,
            // Tying dismissal to the cycle end prevents a stale dismissal from
            // hiding an alert after Super Admin changes the access end date.
            'dismiss_key' => $kind.'|'.$endDate->toDateString(),
        ];
    }

    /** @param array<string, mixed> $state */
    private function dispatchLifecycleNotifications(Subscription $subscription, array $state): void
    {
        $days = $state['days_remaining'];
        $milestones = $state['is_trial'] ? [7, 5, 3, 1] : [5, 4, 3, 2, 1];
        $milestone = $state['is_expired'] ? 'expired' : (in_array($days, $milestones, true) ? $days.'_days' : null);
        if (! $milestone) {
            return;
        }

        $business = $subscription->business;
        if (! $business) {
            return;
        }

        $isTrial = $state['is_trial'];
        $kind = $isTrial ? 'free trial' : 'paid access';
        $end = $state['end_date']?->format('d M, Y') ?? 'the scheduled end date';
        $title = $state['is_expired']
            ? ucfirst($kind).' expired'
            : ucfirst($kind).' expiring soon';
        $message = $state['is_expired']
            ? "{$business->business_name}'s {$kind} expired on {$end}."
            : "{$business->business_name}'s {$kind} expires in {$days} day".($days === 1 ? '' : 's').'.';
        $cycleKey = $subscription->id.'|'.$kind.'|'.($state['end_date']?->toDateString() ?? 'none').'|'.$milestone;
        $metadata = [
            'lifecycle_key' => 'subscription-lifecycle:'.$cycleKey,
            'subscription_id' => $subscription->id,
            'subscription_status' => $state['status'],
            'lifecycle_milestone' => $milestone,
            'expiry_date' => $state['end_date']?->toDateString(),
        ];

        User::where('role', 'super_admin')->whereIn('status', ['active', 'Active'])->get()
            ->each(fn (User $admin) => $this->notifyOnce($admin, $title, $message, $business->id, $metadata));

        $businessRecipients = User::where('business_id', $business->id)
            ->whereIn('role', ['business_owner', 'custom_staff'])
            ->whereIn('status', ['active', 'Active'])
            ->get()
            ->filter(fn (User $user) => $user->role === 'business_owner'
                || $this->permissions->allowsUser($user, 'subscriptions.view', $business));

        $businessTitle = $state['is_expired'] ? 'Your '.$kind.' has ended' : 'Your '.$kind.' is ending soon';
        $businessMessage = $state['is_expired']
            ? "Your {$kind} ended on {$end}. Your business data is safe; contact Profit Point to restore workspace access."
            : ($days === 1
                ? "Your {$kind} ends tomorrow. Contact Profit Point to renew."
                : "Your {$kind} ends in {$days} days. Contact Profit Point to renew.");
        $businessMetadata = $metadata + ['lifecycle_key' => 'business-'.$metadata['lifecycle_key']];
        $businessRecipients->each(fn (User $user) => $this->notifyOnce($user, $businessTitle, $businessMessage, $business->id, $businessMetadata));
    }

    /** @param array<string, mixed> $metadata */
    private function notifyOnce(User $user, string $title, string $message, int $businessId, array $metadata): void
    {
        if ($user->notifications()->where('data->lifecycle_key', $metadata['lifecycle_key'])->exists()) {
            return;
        }

        $user->notify(new SubscriptionStatusNotification($title, $message, $businessId, null, $metadata));
    }
}
