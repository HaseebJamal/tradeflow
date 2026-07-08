<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['business_id', 'subscription_plan_id', 'amount', 'payment_method', 'starts_at', 'ends_at', 'status'];

    protected $casts = ['starts_at' => 'date', 'ends_at' => 'date'];

    public function business() { return $this->belongsTo(Business::class); }
    public function plan() { return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id'); }
}
