<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductBatch extends Model
{
    protected $fillable = ['business_id', 'product_id', 'purchase_id', 'goods_receipt_id', 'batch_number', 'manufacturing_date', 'expiry_date', 'received_quantity', 'remaining_quantity', 'unit_cost', 'source'];

    protected $casts = [
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'received_quantity' => 'decimal:3',
        'remaining_quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function goodsReceipt() { return $this->belongsTo(GoodsReceipt::class); }
    public function allocations() { return $this->hasMany(ProductBatchAllocation::class); }

    public function expiryStatus(): string
    {
        if ((float) $this->remaining_quantity <= 0.0001) return 'Depleted';
        if (! $this->expiry_date) return 'No expiry';
        $today = now(config('app.timezone'))->startOfDay();
        if ($this->expiry_date->lt($today)) return 'Expired';
        $days = max(0, (int) ($this->product?->expiry_alert_days ?? 30));
        return $this->expiry_date->lte($today->copy()->addDays($days)) ? 'Expiring Soon' : 'Valid';
    }

    public function getExpiryStatusAttribute(): string { return $this->expiryStatus(); }

    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('remaining_quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now(config('app.timezone'))->toDateString());
    }
}
