<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosReturnItem extends Model
{
    protected $fillable = ['pos_return_id', 'order_item_id', 'quantity', 'refund_total'];

    public function posReturn() { return $this->belongsTo(PosReturn::class); }
    public function orderItem() { return $this->belongsTo(OrderItem::class); }
}
