<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['business_id', 'order_id', 'invoice_number', 'customer_id', 'invoice_date', 'due_date', 'subtotal', 'discount_percentage', 'discount_amount', 'grand_total', 'paid_amount', 'balance', 'payment_status', 'status', 'notes', 'issued_by', 'issued_at', 'voided_by', 'voided_at', 'void_reason'];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'issued_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function items() { return $this->hasMany(InvoiceItem::class); }
    public function creditNotes() { return $this->hasMany(CreditNote::class); }
}
