<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'business_id', 'subscription_plan_id', 'billing_cycle', 'amount', 'payment_method',
        'payment_status', 'payment_reference', 'starts_at', 'ends_at', 'trial_start_at',
        'trial_end_at', 'cancelled_at', 'cancellation_scheduled_at', 'cancellation_reason',
        'access_ended_at', 'renewed_at', 'auto_renew', 'note', 'status',
    ];

    protected $casts = [
        'starts_at' => 'date', 'ends_at' => 'date', 'trial_start_at' => 'date',
        'trial_end_at' => 'date', 'access_ended_at' => 'date', 'cancelled_at' => 'datetime', 'cancellation_scheduled_at' => 'date',
        'renewed_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function plan() { return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id'); }
    public function accessExtensions() { return $this->hasMany(SubscriptionAccessExtension::class); }

    public function extraAccessDays(): int
    {
        if (! $this->ends_at) {
            return 0;
        }

        return max(0, (int) $this->accessExtensions()
            ->whereDate('paid_access_start_at', $this->starts_at?->toDateString())
            ->whereDate('paid_access_end_at', $this->ends_at->toDateString())
            ->sum('days'));
    }

    public function effectivePaidAccessEnd(): ?\Illuminate\Support\Carbon
    {
        if (! $this->ends_at) {
            return null;
        }

        if ($this->access_ended_at) {
            return $this->access_ended_at->copy();
        }

        return $this->ends_at->copy()->addDays($this->extraAccessDays());
    }
}
