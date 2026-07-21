<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'short_description', 'price', 'monthly_price', 'yearly_price',
        'trial_days', 'product_limit', 'staff_limit', 'order_limit', 'included_modules',
        'features', 'is_public', 'is_recommended', 'sort_order', 'status', 'archived_at',
    ];

    protected $casts = [
        'included_modules' => 'array',
        'features' => 'array',
        'is_public' => 'boolean',
        'is_recommended' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function priceFor(string $cycle): int
    {
        return (int) ($cycle === 'Yearly'
            ? ($this->yearly_price ?? ((int) $this->price * 12))
            : ($this->monthly_price ?? $this->price));
    }

    public function scopePublicActive($query)
    {
        return $query->where('status', 'Active')->where('is_public', true)->whereNull('archived_at');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
