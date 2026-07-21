<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    protected $fillable = [
        'business_id', 'return_number', 'order_id', 'customer_id', 'processed_by',
        'refund_amount', 'refund_method', 'reason', 'returned_at',
    ];

    protected $casts = ['returned_at' => 'datetime'];

    public function order() { return $this->belongsTo(Order::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function items() { return $this->hasMany(SalesReturnItem::class); }
}
