<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformPayment extends Model
{
    protected $fillable = [
        'business_id', 'subscription_id', 'amount', 'method', 'reference_number',
        'status', 'paid_at', 'notes', 'recorded_by',
    ];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function business() { return $this->belongsTo(Business::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
}
