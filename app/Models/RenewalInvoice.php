<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RenewalInvoice extends Model
{
    /**
     * Renewal states that still need a Super Admin billing decision.
     *
     * Delivery history (for example, Sent) is not billing work by itself.
     * Keeping this list explicit prevents every new terminal status from
     * accidentally appearing in an attention badge.
     */
    public const ADMIN_ACTIONABLE_STATUSES = [
        'Generated',
        'Pending Renewal',
        'Pending Payment',
        'Awaiting Verification',
        'Overdue',
        'Failed Send',
        'Delivery Failed',
    ];

    protected $fillable = ['business_id', 'subscription_id', 'platform_payment_id', 'invoice_number', 'amount', 'last_payment_method', 'access_starts_at', 'access_ends_at', 'due_date', 'status', 'email_sent_at', 'email_draft_opened_at', 'whatsapp_opened_at', 'paid_at', 'cancelled_at', 'email_error', 'generated_by', 'sent_by'];

    protected $casts = ['amount' => 'decimal:2', 'access_starts_at' => 'date', 'access_ends_at' => 'date', 'due_date' => 'date', 'email_sent_at' => 'datetime', 'email_draft_opened_at' => 'datetime', 'whatsapp_opened_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function business() { return $this->belongsTo(Business::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function payment() { return $this->belongsTo(PlatformPayment::class, 'platform_payment_id'); }
    public function generatedBy() { return $this->belongsTo(User::class, 'generated_by'); }
    public function sentBy() { return $this->belongsTo(User::class, 'sent_by'); }

    /**
     * Scope renewal invoices that require an administrator to take billing
     * action. Sent, Paid, Cancelled, and other finalised states are excluded.
     */
    public function scopeActionableForAdmin(Builder $query): Builder
    {
        return $query->whereIn('status', self::ADMIN_ACTIONABLE_STATUSES);
    }

    public function isActionableForAdmin(): bool
    {
        return in_array($this->status, self::ADMIN_ACTIONABLE_STATUSES, true);
    }
}
