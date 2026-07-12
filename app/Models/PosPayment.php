<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosPayment extends Model
{
    protected $fillable = ['business_id', 'order_id', 'pos_register_id', 'method', 'amount', 'reference_number', 'created_by'];

    public function order() { return $this->belongsTo(Order::class); }
    public function register() { return $this->belongsTo(PosRegister::class, 'pos_register_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
