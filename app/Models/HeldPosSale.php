<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeldPosSale extends Model
{
    protected $fillable = [
        'business_id', 'pos_register_id', 'user_id', 'hold_number', 'customer_id',
        'cart_payload', 'checkout_payload', 'status', 'held_at', 'resumed_at',
    ];

    protected $casts = [
        'cart_payload' => 'array',
        'checkout_payload' => 'array',
        'held_at' => 'datetime',
        'resumed_at' => 'datetime',
    ];

    public function register() { return $this->belongsTo(PosRegister::class, 'pos_register_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}
