<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCountItem extends Model
{
    protected $fillable = ['stock_count_id', 'business_id', 'product_id', 'system_quantity', 'physical_quantity', 'variance', 'current_system_quantity', 'applied_variance', 'review_required', 'reason', 'notes'];

    protected $casts = [
        'system_quantity' => 'decimal:3',
        'physical_quantity' => 'decimal:3',
        'variance' => 'decimal:3',
        'current_system_quantity' => 'decimal:3',
        'applied_variance' => 'decimal:3',
        'review_required' => 'boolean',
    ];

    public function stockCount() { return $this->belongsTo(StockCount::class); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
}
