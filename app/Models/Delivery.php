<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = ['business_id', 'order_id', 'delivery_staff_id', 'customer_id', 'address', 'amount', 'status', 'proof_image', 'signature_image', 'receiver_name', 'receiver_phone', 'collected_amount', 'payment_method', 'payment_reference', 'payment_proof_image', 'failure_reason', 'started_at', 'delivered_at', 'note'];

    protected $casts = [
        'amount' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'started_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function staff() { return $this->belongsTo(User::class, 'delivery_staff_id'); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
