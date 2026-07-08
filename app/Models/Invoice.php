<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['invoice_number', 'order_id', 'paid_amount', 'balance'];

    public function order() { return $this->belongsTo(Order::class); }
}
