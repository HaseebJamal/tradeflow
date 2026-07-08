<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['business_id', 'name', 'business_name', 'phone', 'address', 'city', 'customer_type', 'credit_limit', 'current_balance', 'status'];

    public function business() { return $this->belongsTo(Business::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function ledgers() { return $this->hasMany(KhataLedger::class); }
    public function payments() { return $this->hasMany(Payment::class); }
}
