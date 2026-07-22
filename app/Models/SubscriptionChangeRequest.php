<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionChangeRequest extends Model
{
    protected $fillable = [
        'business_id', 'subscription_id', 'current_plan_id', 'requested_plan_id', 'requested_by',
        'type', 'billing_cycle', 'expected_amount', 'payment_method', 'trial_eligible', 'trial_days', 'starts_at', 'ends_at', 'note', 'admin_note', 'status', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = ['reviewed_at' => 'datetime', 'trial_eligible' => 'boolean', 'starts_at' => 'date', 'ends_at' => 'date'];

    public function business() { return $this->belongsTo(Business::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function currentPlan() { return $this->belongsTo(SubscriptionPlan::class, 'current_plan_id'); }
    public function requestedPlan() { return $this->belongsTo(SubscriptionPlan::class, 'requested_plan_id'); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
}
