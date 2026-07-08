<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KhataLedger extends Model
{
    protected $fillable = [
        'business_id',
        'customer_id',
        'order_id',
        'payment_id',
        'type',
        'entry_type',
        'amount',
        'customer_debit',
        'customer_credit',
        'business_debit',
        'business_credit',
        'payment_method',
        'description',
        'balance',
        'balance_after',
        'entry_date',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'decimal:2',
        'customer_debit' => 'decimal:2',
        'customer_credit' => 'decimal:2',
        'business_debit' => 'decimal:2',
        'business_credit' => 'decimal:2',
        'balance' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function order() { return $this->belongsTo(Order::class); }
}
