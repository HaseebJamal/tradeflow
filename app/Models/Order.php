<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = ['order_number', 'business_id', 'customer_id', 'retailer_id', 'pos_register_id', 'created_by', 'order_date', 'subtotal', 'discount', 'discount_percentage', 'discount_amount', 'tax_rate', 'tax_amount', 'total', 'grand_total', 'paid_amount', 'balance', 'payment_type', 'payment_status', 'sale_channel', 'status', 'stock_restored_at', 'cancelled_at', 'voided_at', 'void_reason'];

    protected $casts = [
        'order_date' => 'datetime',
        'stock_restored_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function retailer() { return $this->belongsTo(User::class, 'retailer_id'); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function delivery() { return $this->hasOne(Delivery::class); }
    public function invoice() { return $this->hasOne(Invoice::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function posRegister() { return $this->belongsTo(PosRegister::class); }
    public function posPayments() { return $this->hasMany(PosPayment::class); }
    public function posReturns() { return $this->hasMany(PosReturn::class); }
}
