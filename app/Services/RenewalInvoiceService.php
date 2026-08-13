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
use Illuminate\Validation\ValidationException;

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

        $this->markOverdue($now);
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
            $lastPayment = PlatformPayment::where('business_id', $subscription->business_id)
                ->where('status', 'Received')
                ->latest('period_ends_at')
                ->first();

            // One invoice is retained per business/access-end cycle at the
            // database level. A previous manual date correction may have
            // superseded that row, then later made the same cycle current
            // again. Lock the cycle row so it can be restored instead of
            // becoming a permanent generation blocker.
            $existing = RenewalInvoice::query()
                ->where('business_id', $subscription->business_id)
                ->whereDate('access_ends_at', $effectiveEnd)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                if ($existing->status === RenewalInvoice::STATUS_SUPERSEDED) {
                    $status = $existing->email_sent_at
                        ? RenewalInvoice::STATUS_SENT
                        : (($existing->email_draft_opened_at || $existing->whatsapp_opened_at)
                            ? RenewalInvoice::STATUS_PENDING_PAYMENT
                            : RenewalInvoice::STATUS_GENERATED);

                    $existing->update([
                        'subscription_id' => $subscription->id,
                        'amount' => $lastPayment?->amount ?? $subscription->amount,
                        'last_payment_method' => $lastPayment?->method ?? $subscription->payment_method,
                        'access_starts_at' => $subscription->starts_at,
                        'due_date' => $effectiveEnd,
                        'status' => $status,
                    ]);

                    Log::info('Superseded renewal invoice restored for its current paid-access cycle.', [
                        'renewal_invoice_id' => $existing->id,
                        'business_id' => $subscription->business_id,
                        'access_ends_at' => $effectiveEnd->toDateString(),
                        'status' => $status,
                    ]);

                    return $existing->fresh();
                }

                Log::debug('Renewal invoice skipped because the paid-access cycle already has an invoice.', [
                    'business_id' => $subscription->business_id,
                    'access_ends_at' => $effectiveEnd->toDateString(),
                    'renewal_invoice_id' => $existing->id,
                    'status' => $existing->status,
                ]);

                return null;
            }

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
                'status' => RenewalInvoice::STATUS_GENERATED,
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
        if ($invoice?->wasRecentlyCreated) {
            $this->notifyGenerated($invoice, $invoice->business);
        }

        return $invoice;
    }

    /**
     * Opening a compose/click-to-chat window is not confirmed delivery. It
     * advances the invoice into payment follow-up without making a false
     * "Sent" claim.
     */
    public function markDraftOpened(RenewalInvoice $invoice, string $channel, ?int $adminId = null): RenewalInvoice
    {
        $field = match ($channel) {
            'email' => 'email_draft_opened_at',
            'whatsapp' => 'whatsapp_opened_at',
            default => throw ValidationException::withMessages(['channel' => 'Unsupported renewal invoice delivery channel.']),
        };

        // Do not allow a stale Generated/Pending record to be revived by a
        // draft action after its due date has passed.
        $this->markOverdue();
        $invoice->refresh();

        // An already overdue unpaid invoice must remain overdue; opening a
        // second draft records its history but cannot make the invoice look
        // current again.
        $target = $invoice->status === RenewalInvoice::STATUS_OVERDUE
            ? RenewalInvoice::STATUS_OVERDUE
            : RenewalInvoice::STATUS_PENDING_PAYMENT;

        return $this->transition($invoice, $target, [
            $field => now(config('app.timezone')),
            'sent_by' => $adminId,
            'email_error' => null,
        ]);
    }

    public function markOverdue(?Carbon $now = null): void
    {
        $now = ($now ?? now(config('app.timezone')))->copy()->setTimezone(config('app.timezone'));

        // due_date is date-only today, so invoices remain payable for the
        // whole local due date and become overdue the following day.
        RenewalInvoice::whereIn('status', [
            RenewalInvoice::STATUS_GENERATED,
            RenewalInvoice::STATUS_SENT,
            RenewalInvoice::STATUS_PENDING_PAYMENT,
        ])
            ->whereDate('due_date', '<', $now->toDateString())
            ->update(['status' => RenewalInvoice::STATUS_OVERDUE]);
    }

    public function markPaid(RenewalInvoice $invoice, PlatformPayment $payment): RenewalInvoice
    {
        return $this->transition($invoice, RenewalInvoice::STATUS_PAID, [
            'platform_payment_id' => $payment->id,
            'paid_at' => now(config('app.timezone')),
        ]);
    }

    public function markPendingPayment(RenewalInvoice $invoice, PlatformPayment $payment): RenewalInvoice
    {
        return $this->transition($invoice, RenewalInvoice::STATUS_PENDING_PAYMENT, [
            'platform_payment_id' => $payment->id,
        ]);
    }

    public function cancel(RenewalInvoice $invoice): RenewalInvoice
    {
        return $this->transition($invoice, RenewalInvoice::STATUS_CANCELLED, [
            'cancelled_at' => now(config('app.timezone')),
        ]);
    }

    public function canManage(RenewalInvoice $invoice): bool
    {
        return in_array($invoice->status, RenewalInvoice::MANAGEABLE_STATUSES, true);
    }

    public function canRecordPayment(RenewalInvoice $invoice): bool
    {
        return $this->canManage($invoice);
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
            ->whereIn('status', RenewalInvoice::MANAGEABLE_STATUSES)
            ->whereDate('access_ends_at', '!=', $effectiveEnd->toDateString())
            ->update(['status' => RenewalInvoice::STATUS_SUPERSEDED]);
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
            ->whereIn('status', RenewalInvoice::MANAGEABLE_STATUSES)
            ->chunkById(100, function ($invoices): void {
                foreach ($invoices as $invoice) {
                    $subscription = $invoice->subscription;
                    if ($subscription
                        && $subscription->payment_status === 'Received'
                        && $subscription->effectivePaidAccessEnd()
                        && $subscription->effectivePaidAccessEnd()->gt($invoice->access_ends_at)) {
                        $invoice->update(['status' => RenewalInvoice::STATUS_SUPERSEDED]);
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

    /**
     * The sole state writer for administrator-driven renewal actions. The
     * lock prevents a payment, cancellation, or draft action in separate tabs
     * from producing an invalid combination of timestamps and status.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function transition(RenewalInvoice $invoice, string $to, array $attributes = []): RenewalInvoice
    {
        return DB::transaction(function () use ($invoice, $to, $attributes): RenewalInvoice {
            $locked = RenewalInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $from = $locked->status;

            if (! $this->allows($from, $to)) {
                throw ValidationException::withMessages([
                    'renewal_invoice' => "This renewal invoice cannot transition from {$from} to {$to}.",
                ]);
            }

            $locked->update($attributes + ['status' => $to]);

            return $locked->fresh();
        });
    }

    private function allows(string $from, string $to): bool
    {
        // Reopening another draft leaves an already pending payment invoice in
        // the same state while recording the new channel timestamp.
        if ($from === $to && in_array($to, [
            RenewalInvoice::STATUS_PENDING_PAYMENT,
            RenewalInvoice::STATUS_OVERDUE,
        ], true)) {
            return true;
        }

        return match ($from) {
            RenewalInvoice::STATUS_GENERATED, RenewalInvoice::STATUS_SENT => in_array($to, [
                RenewalInvoice::STATUS_PENDING_PAYMENT,
                RenewalInvoice::STATUS_PAID,
                RenewalInvoice::STATUS_CANCELLED,
                RenewalInvoice::STATUS_OVERDUE,
                RenewalInvoice::STATUS_SUPERSEDED,
            ], true),
            RenewalInvoice::STATUS_PENDING_PAYMENT => in_array($to, [
                RenewalInvoice::STATUS_PAID,
                RenewalInvoice::STATUS_CANCELLED,
                RenewalInvoice::STATUS_OVERDUE,
                RenewalInvoice::STATUS_SUPERSEDED,
            ], true),
            RenewalInvoice::STATUS_OVERDUE => in_array($to, [
                RenewalInvoice::STATUS_PENDING_PAYMENT,
                RenewalInvoice::STATUS_PAID,
                RenewalInvoice::STATUS_CANCELLED,
                RenewalInvoice::STATUS_SUPERSEDED,
            ], true),
            default => false,
        };
    }
}
