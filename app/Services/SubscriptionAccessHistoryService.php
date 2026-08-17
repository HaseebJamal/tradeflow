<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PlatformPayment;
use App\Models\Subscription;
use App\Models\User;

/**
 * Appends immutable lifecycle snapshots to the existing audit log. The
 * Subscription row remains the current entitlement; it is never the source
 * of historical trial reporting after conversion.
 */
class SubscriptionAccessHistoryService
{
    public function recordTrialConvertedToPaid(Subscription $subscription, ?PlatformPayment $payment, ?User $actor = null, ?string $reference = null): void
    {
        if (! $subscription->exists || ! $subscription->trial_start_at || ! $subscription->trial_end_at) {
            return;
        }

        $alreadyRecorded = AuditLog::query()
            ->where('business_id', $subscription->business_id)
            ->where('module', 'Trial & Access')
            ->where('action', 'trial converted to paid')
            ->where('record_type', Subscription::class)
            ->where('record_id', $subscription->id)
            ->exists();
        if ($alreadyRecorded) {
            return;
        }

        $convertedAt = ($payment->verified_at ?? now(config('app.timezone')))->copy();
        AuditLog::create([
            'user_id' => $actor?->id,
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'business_id' => $subscription->business_id,
            'module' => 'Trial & Access',
            'action' => 'trial converted to paid',
            'description' => 'Free trial converted to paid access.',
            'record_type' => Subscription::class,
            'record_id' => $subscription->id,
            'old_values' => [
                'access_type' => 'Free Trial',
                'trial_start' => $subscription->trial_start_at->toDateString(),
                'scheduled_trial_end' => $subscription->trial_end_at->toDateString(),
                'trial_duration_days' => $subscription->trial_start_at->diffInDays($subscription->trial_end_at),
                'trial_status' => $subscription->status,
            ],
            'new_values' => [
                'outcome' => 'Converted to Paid',
                'actual_end' => $convertedAt->toDateString(),
                'payment_id' => $payment?->id,
                'payment_reference' => $payment?->transaction_reference ?: $payment?->reference_number ?: $reference,
                'converted_by' => $actor?->id,
            ],
            'occurred_at' => $convertedAt,
        ]);
    }
}
