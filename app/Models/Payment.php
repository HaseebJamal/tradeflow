<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['business_id', 'order_id', 'pos_register_id', 'customer_id', 'method', 'amount', 'transaction_reference', 'reference_number', 'payment_date', 'proof_image', 'screenshot', 'status'];

    protected $casts = ['payment_date' => 'date'];

    public function order() { return $this->belongsTo(Order::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function register() { return $this->belongsTo(PosRegister::class, 'pos_register_id'); }
}
