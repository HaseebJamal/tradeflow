<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PlatformPayment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\SubscriptionStatusNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubscriptionPaymentService
{
    public function quote(Business $business, SubscriptionPlan $plan, string $cycle): array
    {
        abort_unless($plan->status === 'Active' && ! $plan->archived_at && $plan->is_public, 422, 'This plan is not currently available.');
        abort_unless(in_array($cycle, ['Monthly', 'Yearly'], true), 422, 'Choose a valid billing cycle.');

        $subscription = $business->subscription;
        $period = $this->periodFor($subscription, $cycle);

        return [
            'plan' => $plan,
            'billing_cycle' => $cycle,
            'amount' => $plan->priceFor($cycle),
            'subscription' => $subscription,
            'period_starts_at' => $period['starts_at'],
            'period_ends_at' => $period['ends_at'],
        ];
    }

    public function submit(Business $business, User $user, int $planId, string $cycle, string $method, ?string $transactionReference, ?string $note, ?UploadedFile $proof): PlatformPayment
    {
        return DB::transaction(function () use ($business, $user, $planId, $cycle, $method, $transactionReference, $note, $proof) {
            $lockedBusiness = Business::whereKey($business->id)->lockForUpdate()->firstOrFail();
            $subscription = $lockedBusiness->subscription()->with('plan')->lockForUpdate()->first();
            if (PlatformPayment::where('business_id', $lockedBusiness->id)->where('status', 'Pending')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['payment' => 'A subscription payment is already awaiting verification.']);
            }
            $plan = SubscriptionPlan::publicActive()->findOrFail($planId);
            $quote = $this->quote($lockedBusiness->setRelation('subscription', $subscription), $plan, $cycle);
            $proofPath = $proof?->store('subscription_payment_proofs', 'local');
            $payment = PlatformPayment::create([
                'business_id' => $lockedBusiness->id,
                'subscription_id' => $subscription?->id,
                'subscription_plan_id' => $plan->id,
                'billing_cycle' => $cycle,
                'amount' => $quote['amount'],
                'method' => $method,
                'transaction_reference' => $transactionReference,
                'payment_proof' => $proofPath,
                'status' => 'Pending',
                'paid_at' => now(),
                'submitted_at' => now(),
                'notes' => $note,
                'period_starts_at' => $quote['period_starts_at'],
                'period_ends_at' => $quote['period_ends_at'],
                'recorded_by' => $user->id,
            ]);
            $payment->update(['reference_number' => 'PP-'.now()->format('Ymd').'-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)]);

            User::where('role', 'super_admin')->where('status', 'active')->get()->each(fn (User $admin) => $admin->notify(
                new SubscriptionStatusNotification('Payment Awaiting Verification', $lockedBusiness->business_name.' submitted subscription payment '.$payment->reference_number.'.', $lockedBusiness->id, null, [
                    'related_type' => PlatformPayment::class,
                    'related_id' => $payment->id,
                    'payment_id' => $payment->id,
                ])
            ));
            $lockedBusiness->owner?->notify(new SubscriptionStatusNotification('Payment Submitted', 'Your payment '.$payment->reference_number.' was submitted for verification.', $lockedBusiness->id, $payment->id));
            return $payment->fresh(['plan', 'subscription.plan']);
        });
    }

    public function approve(PlatformPayment $payment, User $admin): PlatformPayment
    {
        return DB::transaction(function () use ($payment, $admin) {
            $payment = PlatformPayment::with('plan')->lockForUpdate()->findOrFail($payment->id);
            abort_unless($payment->status === 'Pending', 422, 'Only pending payments can be verified.');
            $business = Business::whereKey($payment->business_id)->lockForUpdate()->firstOrFail();
            $subscription = Subscription::where('business_id', $business->id)->lockForUpdate()->first() ?: new Subscription(['business_id' => $business->id]);
            $plan = $payment->plan ?: throw ValidationException::withMessages(['payment' => 'The requested plan is unavailable.']);
            $isCustomAccess = $payment->billing_cycle === 'Custom' && $payment->period_starts_at && $payment->period_ends_at;
            $isExtendingCurrentPeriod = $subscription->exists
                && $subscription->subscription_plan_id === $plan->id
                && in_array($subscription->status, ['Active', 'Expiring'], true)
                && $subscription->effectivePaidAccessEnd()?->gte(now()->startOfDay());
            $starts = $isCustomAccess
                ? $payment->period_starts_at
                : ($isExtendingCurrentPeriod
                ? ($subscription->starts_at?->toDateString() ?? now()->toDateString())
                : ($payment->period_starts_at ?: now()->toDateString()));
            $ends = $isCustomAccess
                ? $payment->period_ends_at
                : ($payment->period_ends_at ?: $this->periodFor($subscription->exists ? $subscription : null, $payment->billing_cycle ?: 'Monthly')['ends_at']);

            $subscription->fill([
                'subscription_plan_id' => $plan->id,
                'billing_cycle' => $payment->billing_cycle ?: 'Monthly',
                'amount' => $isCustomAccess ? $payment->amount : $plan->priceFor($payment->billing_cycle ?: 'Monthly'),
                'payment_method' => $payment->method,
                'payment_status' => 'Received',
                'payment_reference' => $payment->reference_number,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'access_ended_at' => null,
                'trial_start_at' => $isCustomAccess ? $subscription->trial_start_at : null,
                'trial_end_at' => $isCustomAccess ? $subscription->trial_end_at : null,
                'status' => 'Active',
                'renewed_at' => now(),
                'cancellation_scheduled_at' => null,
                'cancellation_reason' => null,
            ])->save();

            $payment->update([
                'subscription_id' => $subscription->id,
                'status' => 'Received',
                'paid_at' => now(),
                'verified_at' => now(),
                'verified_by' => $admin->id,
            ]);
            $business->owner?->notify(new SubscriptionStatusNotification('Payment Verified', $isCustomAccess ? 'Your payment '.$payment->reference_number.' was verified. Your paid access is now active.' : 'Your payment '.$payment->reference_number.' was verified. Your subscription is now active.', $business->id, $payment->id));
            return $payment->fresh(['business.owner', 'plan', 'subscription.plan']);
        });
    }

    public function reject(PlatformPayment $payment, User $admin, string $reason): PlatformPayment
    {
        return DB::transaction(function () use ($payment, $admin, $reason) {
            $payment = PlatformPayment::lockForUpdate()->findOrFail($payment->id);
            abort_unless($payment->status === 'Pending', 422, 'Only pending payments can be rejected.');
            $payment->update(['status' => 'Rejected', 'rejection_reason' => $reason, 'verified_at' => now(), 'verified_by' => $admin->id]);
            $payment->business?->owner?->notify(new SubscriptionStatusNotification('Payment Rejected', 'Your payment '.$payment->reference_number.' was rejected. Reason: '.$reason, $payment->business_id, $payment->id));
            return $payment->fresh();
        });
    }

    private function periodFor(?Subscription $subscription, string $cycle): array
    {
        $today = now()->startOfDay();
        $existingEnd = $subscription?->effectivePaidAccessEnd()?->copy()->startOfDay();
        $starts = $existingEnd && in_array($subscription?->status, ['Active', 'Expiring'], true) && $existingEnd->gte($today)
            ? $existingEnd->copy()->addDay()
            : $today;
        $ends = $cycle === 'Yearly' ? $starts->copy()->addYear() : $starts->copy()->addMonth();
        return ['starts_at' => $starts->toDateString(), 'ends_at' => $ends->toDateString()];
    }
}
