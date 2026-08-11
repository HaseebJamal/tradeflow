<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PlatformPayment;
use App\Models\RenewalInvoice;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionStatusNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RenewalInvoiceService
{
    public const DEFAULT_REMINDER_DAYS = 4;

    public function generateDue(?Carbon $now = null): int
    {
        $now = ($now ?? now(config('app.timezone')))->copy()->setTimezone(config('app.timezone'));
        $today = $now->copy()->startOfDay();
        $windowEnd = $today->copy()->addDays($this->reminderDays())->endOfDay();
        $count = 0;
        $eligible = 0;
        Subscription::with(['business.owner'])
            ->where('payment_status', 'Received')
            ->whereNotNull('ends_at')
            // A complimentary extension can move the effective end beyond the
            // stored paid end. Include older paid ends here; isEligible()
            // performs the authoritative effective-end check below.
            ->whereDate('ends_at', '<=', $windowEnd->toDateString())
            ->chunkById(100, function ($subscriptions) use (&$count, &$eligible, $now): void {
                foreach ($subscriptions as $subscription) {
                    $eligible++;
                    $this->generateFor($subscription, $now) && $count++;
                }
            });

        $this->markOverdue($today);
        $this->supersedeRenewedCycles();
        Log::info('Renewal invoice eligibility check completed.', [
            'eligible_subscriptions' => $eligible,
            'generated_invoices' => $count,
            'reminder_days' => $this->reminderDays(),
            'checked_at' => $now->toDateTimeString(),
        ]);

        return $count;
    }

    public function generateFor(Subscription $subscription, ?Carbon $now = null): ?RenewalInvoice
    {
        $now = ($now ?? now(config('app.timezone')))->copy()->setTimezone(config('app.timezone'));
        $invoice = DB::transaction(function () use ($subscription, $now) {
            $subscription = Subscription::with('business.owner')->lockForUpdate()->findOrFail($subscription->id);
            if (! $this->isEligible($subscription, $now)) {
                return null;
            }
            $effectiveEnd = $subscription->effectivePaidAccessEnd();

            $existing = RenewalInvoice::where('business_id', $subscription->business_id)->whereDate('access_ends_at', $effectiveEnd)->first();
            if ($existing) {
                Log::debug('Renewal invoice skipped because the paid-access cycle already has an invoice.', [
                    'business_id' => $subscription->business_id,
                    'access_ends_at' => $effectiveEnd->toDateString(),
                    'renewal_invoice_id' => $existing->id,
                ]);

                return null;
            }

            $lastPayment = PlatformPayment::where('business_id', $subscription->business_id)->where('status', 'Received')->latest('period_ends_at')->first();
            $invoice = RenewalInvoice::create([
                'business_id' => $subscription->business_id,
                'subscription_id' => $subscription->id,
                // The business and access end date are the idempotency key, so
                // this identifier is stable without relying on a racy MAX(id).
                'invoice_number' => 'PP-RNW-'.$subscription->business_id.'-'.$effectiveEnd->format('Ymd'),
                'amount' => $lastPayment?->amount ?? $subscription->amount,
                'last_payment_method' => $lastPayment?->method ?? $subscription->payment_method,
                'access_starts_at' => $subscription->starts_at,
                'access_ends_at' => $effectiveEnd,
                'due_date' => $effectiveEnd,
                'status' => 'Generated',
            ]);

            Log::info('Renewal invoice generated.', [
                'renewal_invoice_id' => $invoice->id,
                'business_id' => $subscription->business_id,
                'access_ends_at' => $effectiveEnd->toDateString(),
            ]);

            return $invoice;
        });

        // Notifications are intentionally sent after the invoice transaction
        // commits, so recipients never receive a link to a rolled-back record.
        if ($invoice) {
            $this->notifyGenerated($invoice, $invoice->business);
        }

        return $invoice;
    }

    public function markOverdue(?Carbon $today = null): void
    {
        $today ??= now(config('app.timezone'))->startOfDay();
        RenewalInvoice::whereIn('status', ['Generated', 'Sent', 'Pending Payment'])
            ->whereDate('access_ends_at', '<', $today->toDateString())
            ->update(['status' => 'Overdue']);
    }

    public function markPaid(RenewalInvoice $invoice, PlatformPayment $payment): void
    {
        $invoice->update(['platform_payment_id' => $payment->id, 'status' => 'Paid', 'paid_at' => now()]);
    }

    /**
     * A manual grace adjustment changes only operational expiry. Any pending
     * reminder for the superseded effective end must not look like a new paid
     * billing period or send a premature renewal request.
     */
    public function reconcileEffectiveAccessEnd(Subscription $subscription): void
    {
        $effectiveEnd = $subscription->effectivePaidAccessEnd();
        if (! $effectiveEnd) {
            return;
        }

        RenewalInvoice::where('subscription_id', $subscription->id)
            ->whereIn('status', ['Generated', 'Sent', 'Pending Payment', 'Overdue'])
            ->whereDate('access_ends_at', '!=', $effectiveEnd->toDateString())
            ->update(['status' => 'Superseded']);
    }

    private function reminderDays(): int
    {
        return min(30, max(1, (int) (app(PlatformSettingsService::class)->current()->renewal_invoice_reminder_days ?: self::DEFAULT_REMINDER_DAYS)));
    }

    private function isEligible(Subscription $subscription, Carbon $now): bool
    {
        if ($subscription->payment_status !== 'Received'
            || in_array($subscription->status, ['Expired', 'Cancelled', 'Suspended'], true)
            || ! $subscription->starts_at
            || ! $subscription->ends_at) {
            return false;
        }

        $start = Carbon::parse($subscription->starts_at->toDateString(), config('app.timezone'))->startOfDay();
        $effectiveEnd = $subscription->effectivePaidAccessEnd();
        if (! $effectiveEnd) {
            return false;
        }
        $end = Carbon::parse($effectiveEnd->toDateString(), config('app.timezone'))->endOfDay();
        $windowEnd = $now->copy()->startOfDay()->addDays($this->reminderDays())->endOfDay();

        return $start->lte($now) && $now->lte($end) && $end->lte($windowEnd);
    }

    /** Close reminders for cycles that have been manually renewed already. */
    private function supersedeRenewedCycles(): void
    {
        RenewalInvoice::with('subscription')
            ->whereIn('status', ['Generated', 'Sent', 'Pending Payment', 'Overdue'])
            ->chunkById(100, function ($invoices): void {
                foreach ($invoices as $invoice) {
                    $subscription = $invoice->subscription;
                    if ($subscription
                        && $subscription->payment_status === 'Received'
                        && $subscription->effectivePaidAccessEnd()
                        && $subscription->effectivePaidAccessEnd()->gt($invoice->access_ends_at)) {
                        $invoice->update(['status' => 'Superseded']);
                        Log::info('Renewal invoice superseded by a newer paid-access period.', [
                            'renewal_invoice_id' => $invoice->id,
                            'business_id' => $invoice->business_id,
                        ]);
                    }
                }
            });
    }

    private function notifyGenerated(RenewalInvoice $invoice, Business $business): void
    {
        $end = $invoice->access_ends_at->format('d M, Y');
        $message = "Your Profit Point access expires on {$end}. Renewal invoice {$invoice->invoice_number} is ready.";
        $metadata = ['renewal_invoice_id' => $invoice->id, 'renewal_invoice_number' => $invoice->invoice_number, 'lifecycle_key' => 'renewal-invoice:'.$invoice->id];
        $business->owner?->notify(new SubscriptionStatusNotification('Access renewal required', $message, $business->id, null, $metadata));
        User::where('role', 'super_admin')->whereIn('status', ['active', 'Active'])->get()
            ->each(fn (User $admin) => $admin->notify(new SubscriptionStatusNotification('Renewal invoice generated', "Renewal invoice {$invoice->invoice_number} was generated for {$business->business_name}. Access expires on {$end}.", $business->id, null, $metadata + ['lifecycle_key' => 'admin-renewal-invoice:'.$invoice->id])));
    }
}
