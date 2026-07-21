<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturnItem extends Model
{
    protected $fillable = ['sales_return_id', 'order_item_id', 'quantity', 'refund_total'];

    public function salesReturn() { return $this->belongsTo(SalesReturn::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
}
