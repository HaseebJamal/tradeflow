<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionAccessExtension extends Model
{
    protected $fillable = [
        'subscription_id', 'business_id', 'paid_access_start_at', 'paid_access_end_at',
        'days', 'kind', 'note', 'granted_by', 'granted_at',
    ];

    protected $casts = [
        'paid_access_start_at' => 'date',
        'paid_access_end_at' => 'date',
        'granted_at' => 'datetime',
    ];

    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function business() { return $this->belongsTo(Business::class); }
    public function grantedBy() { return $this->belongsTo(User::class, 'granted_by'); }
}
