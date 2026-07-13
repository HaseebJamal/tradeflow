<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoice extends Model
{
    protected $fillable = ['business_id', 'purchase_id', 'supplier_id', 'invoice_number', 'invoice_date', 'grand_total', 'paid_amount', 'balance', 'status'];
    protected $casts = ['invoice_date' => 'date'];
    public function purchase() { return $this->belongsTo(Purchase::class); }
}
