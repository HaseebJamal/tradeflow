<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'business_id', 'subscription_plan_id', 'billing_cycle', 'amount', 'payment_method',
        'payment_status', 'payment_reference', 'starts_at', 'ends_at', 'trial_start_at',
        'trial_end_at', 'cancelled_at', 'renewed_at', 'auto_renew', 'note', 'status',
    ];

    protected $casts = [
        'starts_at' => 'date', 'ends_at' => 'date', 'trial_start_at' => 'date',
        'trial_end_at' => 'date', 'cancelled_at' => 'datetime', 'renewed_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function plan() { return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id'); }
}
