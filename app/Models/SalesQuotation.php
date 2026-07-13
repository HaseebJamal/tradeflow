<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesQuotation extends Model
{
    protected $fillable = ['business_id', 'customer_id', 'created_by', 'quotation_number', 'quotation_date', 'valid_until', 'status', 'subtotal', 'discount_amount', 'tax_amount', 'grand_total', 'notes'];
    protected $casts = ['quotation_date' => 'date', 'valid_until' => 'date'];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function items() { return $this->hasMany(SalesQuotationItem::class); }
}
