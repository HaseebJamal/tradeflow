<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'product_name_snapshot', 'quantity', 'unit', 'unit_price', 'standard_unit_price', 'is_price_overridden', 'price_override_reason', 'purchase_cost_snapshot', 'line_subtotal', 'discount_type', 'discount_value', 'discount_rate', 'discount_amount', 'tax_rate', 'tax_amount', 'line_total', 'price', 'total'];

    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function salesReturnItems() { return $this->hasMany(SalesReturnItem::class); }
    public function batchAllocations() { return $this->hasMany(ProductBatchAllocation::class); }
}
