<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = ['business_id', 'supplier_id', 'created_by', 'purchase_number', 'supplier_invoice_number', 'status', 'purchase_date', 'received_at', 'subtotal', 'discount_amount', 'tax_amount', 'grand_total', 'paid_amount', 'balance', 'payment_status', 'notes'];
    protected $casts = ['purchase_date' => 'datetime', 'received_at' => 'datetime'];
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function items() { return $this->hasMany(PurchaseItem::class); }
    public function invoice() { return $this->hasOne(PurchaseInvoice::class); }
    public function payments() { return $this->hasMany(SupplierPayment::class); }
    public function returns() { return $this->hasMany(PurchaseReturn::class); }
}
