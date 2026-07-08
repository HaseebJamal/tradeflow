<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['order_number', 'business_id', 'customer_id', 'retailer_id', 'created_by', 'subtotal', 'discount', 'discount_percentage', 'discount_amount', 'total', 'grand_total', 'paid_amount', 'balance', 'payment_type', 'payment_status', 'status', 'stock_restored_at', 'cancelled_at'];

    protected $casts = [
        'stock_restored_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function retailer() { return $this->belongsTo(User::class, 'retailer_id'); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function delivery() { return $this->hasOne(Delivery::class); }
    public function invoice() { return $this->hasOne(Invoice::class); }
}
