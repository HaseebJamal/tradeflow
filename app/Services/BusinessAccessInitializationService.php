<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PlatformPayment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates the first entitlement for an approved business.  It is deliberately
 * limited to new/pending records so it can never grant a fresh trial to an
 * established expired or restricted company.
 */
class BusinessAccessInitializationService
{
    public function initializeApprovedBusiness(Business $business): ?Subscription
    {
        return DB::transaction(function () use ($business): ?Subscription {
            $business = Business::query()->lockForUpdate()->findOrFail($business->id);
            if (! in_array(strtolower((string) $business->status), ['approved'], true)) {
                return null;
            }

            $subscription = Subscription::query()
                ->with('plan')
                ->where('business_id', $business->id)
                ->lockForUpdate()
                ->first();

            // An active/expired/cancelled lifecycle is real historical state.
            // Only a new missing record or a pre-approval Pending record may
            // be initialized automatically.
            if ($subscription && $subscription->status !== 'Pending') {
                return $subscription;
            }
            if ($subscription?->payment_status === 'Received') {
                return $subscription;
            }
            if (PlatformPayment::query()->where('business_id', $business->id)->exists()) {
                return $subscription;
            }

            $settings = app(PlatformSettingsService::class)->current();
            $trialDays = (int) $settings->trial_days;
            if ($trialDays < 1 || $trialDays > 365) {
                throw ValidationException::withMessages([
                    'trial' => 'Configure a valid global trial duration before approving a new company.',
                ]);
            }

            $plan = $subscription?->plan
                ?? SubscriptionPlan::query()->whereKey($business->selected_plan_id)->first()
                ?? SubscriptionPlan::query()->whereKey($settings->default_plan_id)->whereNull('archived_at')->first()
                ?? SubscriptionPlan::query()->where('status', 'Active')->whereNull('archived_at')->orderBy('sort_order')->first();
            if (! $plan) {
                throw ValidationException::withMessages([
                    'trial' => 'Configure an active default subscription plan before approving a new company.',
                ]);
            }

            $start = now(config('app.timezone'))->startOfDay();
            $end = $start->copy()->addDays($trialDays);
            $values = [
                'subscription_plan_id' => $plan->id,
                'billing_cycle' => $subscription?->billing_cycle ?? 'Custom',
                'amount' => 0,
                'payment_status' => 'Pending',
                'starts_at' => $start,
                'ends_at' => $end,
                'trial_start_at' => $start,
                'trial_end_at' => $end,
                'access_ended_at' => null,
                'status' => 'Trial',
                'cancelled_at' => null,
                'cancellation_scheduled_at' => null,
                'cancellation_reason' => null,
            ];

            $subscription = $subscription
                ? tap($subscription, fn (Subscription $record) => $record->update($values))
                : Subscription::create(['business_id' => $business->id] + $values);

            $business->update([
                'selected_plan_id' => $business->selected_plan_id ?? $plan->id,
                'selected_billing_cycle' => $business->selected_billing_cycle ?? 'Custom',
                'plan_selection_source' => $business->plan_selection_source ?? 'automatic_trial',
                'selected_plan_price' => $business->selected_plan_price ?? 0,
                'trial_eligible' => true,
                'requested_trial_days' => $trialDays,
                'subscription_request_status' => 'Trial Active',
                'plan_selected_at' => $business->plan_selected_at ?? now(),
            ]);

            return $subscription->fresh(['plan', 'business.owner']);
        });
    }

    /**
     * Recover only newly-approved companies which have no access lifecycle or
     * payment history. This fixes interrupted same-day onboarding without
     * reviving long-standing restricted accounts.
     */
    public function recoverNewApprovedBusiness(Business $business): ?Subscription
    {
        if (! $this->isRecoverableNewBusiness($business)) {
            return null;
        }

        $subscription = $this->initializeApprovedBusiness($business);
        if ($subscription?->status === 'Trial') {
            $this->removeErroneousRestrictionNotifications($business->id);
        }

        return $subscription;
    }

    public function recoverTodaysMissingApprovedBusinesses(): void
    {
        Business::query()
            ->whereIn('status', ['Approved', 'approved'])
            ->whereDate('created_at', now(config('app.timezone'))->toDateString())
            ->doesntHave('subscription')
            ->orderBy('id')
            ->each(fn (Business $business) => $this->recoverNewApprovedBusiness($business));
    }

    private function isRecoverableNewBusiness(Business $business): bool
    {
        return in_array(strtolower((string) $business->status), ['approved'], true)
            && $business->created_at?->isSameDay(now(config('app.timezone')))
            && ! $business->subscription()->exists()
            && ! PlatformPayment::query()->where('business_id', $business->id)->exists();
    }

    private function removeErroneousRestrictionNotifications(int $businessId): void
    {
        DatabaseNotification::query()
            ->whereJsonContains('data->business_id', $businessId)
            ->where('data->message', 'like', '%access restricted%')
            ->where('data->message', 'like', '%Restore a new trial period%')
            ->delete();
    }
}
