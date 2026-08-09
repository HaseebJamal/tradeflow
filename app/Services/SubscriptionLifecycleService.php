<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionStatusNotification;
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
    public const EXPIRING_SOON_DAYS = 7;

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

        if ($state['is_scheduled'] && in_array($current, ['Trial', 'Active', 'Expiring'], true)) {
            $target = 'Pending';
        } elseif ($current === 'Pending' && ! $state['is_scheduled'] && ! $state['is_expired'] && ($state['is_trial'] || $subscription->payment_status === 'Received')) {
            $target = $state['is_trial'] ? 'Trial' : ($state['is_expiring_soon'] ? 'Expiring' : 'Active');
        } elseif ($state['is_expired'] && in_array($current, ['Trial', 'Active', 'Expiring'], true)) {
            $target = 'Expired';
        } elseif ($current === 'Active' && $state['is_expiring_soon']) {
            $target = 'Expiring';
        } elseif ($current === 'Expiring' && ! $state['is_expiring_soon'] && ! $state['is_expired']) {
            $target = 'Active';
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
            ->whereIn('status', ['Pending', 'Trial', 'Active', 'Expiring'])
            ->chunkById(100, function ($subscriptions) use ($dispatchNotifications): void {
                foreach ($subscriptions as $subscription) {
                    $this->synchronize($subscription, $dispatchNotifications);
                }
            });
    }

    /** @return array<string, mixed> */
    public function state(?Subscription $subscription, bool $includeUsage = false): array
    {
        $now = now(config('app.timezone'));
        $today = $now->copy()->startOfDay();
        // Trial dates are retained as historical evidence after a paid period
        // begins. They must not turn a paid Active subscription back into a
        // trial (or make it expire against the old trial end date).
        $isTrial = $subscription && (
            $subscription->status === 'Trial'
            || (in_array($subscription->status, ['Pending', 'Expired'], true)
                && $subscription->payment_status !== 'Received'
                && $subscription->trial_start_at
                && $subscription->trial_end_at)
        );
        $endDate = $subscription
            ? ($isTrial ? ($subscription->trial_end_at ?? $subscription->ends_at) : $subscription->ends_at)
            : null;
        $startDate = $subscription
            ? ($isTrial ? ($subscription->trial_start_at ?? $subscription->starts_at) : $subscription->starts_at)
            : null;
        $isScheduled = $subscription && $startDate && $today->lt($startDate->copy()->startOfDay());
        // Subscription trial/access columns are calendar dates. A stored end
        // date remains valid through that local calendar day, then expires
        // exactly once at the following end-of-day boundary.
        $expiryBoundary = $endDate?->copy()->endOfDay();
        $daysRemaining = $endDate && ! $isScheduled
            ? (int) $today->diffInDays($endDate->copy()->startOfDay(), false)
            : null;
        $isExpired = $subscription && ! $isScheduled && $expiryBoundary && $now->gt($expiryBoundary);
        $effectiveStatus = $isScheduled && in_array($subscription?->status, ['Trial', 'Active', 'Expiring'], true)
            ? 'Pending'
            : ($isExpired && in_array($subscription?->status, ['Trial', 'Active', 'Expiring'], true)
                ? 'Expired'
                : $subscription?->status);
        $isExpiringSoon = ! $isExpired
            && $daysRemaining !== null
            && $daysRemaining >= 0
            && $daysRemaining <= self::EXPIRING_SOON_DAYS
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
            'is_trial' => (bool) $isTrial,
            'trial_start' => $subscription?->trial_start_at,
            'trial_end' => $subscription?->trial_end_at ?? ($isTrial ? $subscription?->ends_at : null),
            'subscription_start' => $subscription?->starts_at,
            'subscription_end' => $subscription?->ends_at,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'billing_cycle' => $subscription?->billing_cycle,
            'payment_status' => $subscription?->payment_status ?? 'Pending',
            'days_remaining' => $isExpired ? 0 : $daysRemaining,
            'is_scheduled' => (bool) $isScheduled,
            'is_active_period' => ! $isScheduled && ! $isExpired && in_array($effectiveStatus, ['Trial', 'Active', 'Expiring'], true),
            'is_expiring_soon' => $isExpiringSoon,
            'is_expired' => (bool) $isExpired || $effectiveStatus === 'Expired',
            // A workspace needs an actual, current trial or paid access record.
            // Legacy plan records remain attached for audit history only; they
            // never define an active business's access or feature limits.
            'can_access_business' => (bool) $subscription && ! $isScheduled && ! $isExpired && in_array($effectiveStatus, ['Trial', 'Active', 'Expiring'], true),
            'limits' => null,
            'usage' => $usage,
        ];
    }

    /** @param array<string, mixed> $state */
    private function dispatchLifecycleNotifications(Subscription $subscription, array $state): void
    {
        $days = $state['days_remaining'];
        $milestones = $state['is_trial'] ? [7, 5, 3, 1] : [4, 3, 2, 1];
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
