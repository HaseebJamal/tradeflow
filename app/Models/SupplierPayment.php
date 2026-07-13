<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    protected $fillable = ['business_id', 'supplier_id', 'purchase_id', 'created_by', 'amount', 'method', 'reference_number', 'payment_date', 'notes'];
    protected $casts = ['payment_date' => 'date'];
}
