<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformPayment extends Model
{
    protected $fillable = [
        'business_id', 'subscription_id', 'subscription_plan_id', 'billing_cycle', 'amount', 'method', 'reference_number',
        'transaction_reference', 'payment_proof', 'status', 'paid_at', 'submitted_at', 'verified_at', 'verified_by',
        'rejection_reason', 'period_starts_at', 'period_ends_at', 'notes', 'recorded_by',
    ];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'period_starts_at' => 'date', 'period_ends_at' => 'date'];

    public function business() { return $this->belongsTo(Business::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function plan() { return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id'); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
    public function verifiedBy() { return $this->belongsTo(User::class, 'verified_by'); }
}
