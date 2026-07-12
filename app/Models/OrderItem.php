<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'product_name_snapshot', 'sku_snapshot', 'quantity', 'unit', 'unit_price', 'purchase_cost_snapshot', 'line_total', 'price', 'total'];

    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function posReturnItems() { return $this->hasMany(PosReturnItem::class); }
}
