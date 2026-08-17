<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBatchAllocation extends Model
{
    protected $fillable = ['business_id', 'product_batch_id', 'order_id', 'order_item_id', 'quantity', 'type', 'created_by'];
    protected $casts = ['quantity' => 'decimal:3'];
    public function batch() { return $this->belongsTo(ProductBatch::class, 'product_batch_id'); }
    public function order() { return $this->belongsTo(Order::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
