<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = ['business_id', 'supplier_id', 'created_by', 'updated_by', 'confirmed_by', 'purchase_number', 'submission_token', 'supplier_invoice_number', 'supplier_invoice_date', 'supplier_reference', 'purchase_order_reference', 'status', 'receiving_status', 'purchase_date', 'received_at', 'confirmed_at', 'payment_terms', 'due_date', 'subtotal', 'discount_amount', 'tax_amount', 'other_charges', 'grand_total', 'paid_amount', 'balance', 'payment_status', 'payment_method', 'payment_date', 'payment_reference', 'cheque_number', 'cheque_due_date', 'payment_account_id', 'notes'];
    protected $casts = ['purchase_date' => 'datetime', 'received_at' => 'datetime', 'confirmed_at' => 'datetime', 'supplier_invoice_date' => 'date', 'due_date' => 'date', 'payment_date' => 'date', 'cheque_due_date' => 'date'];
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function items() { return $this->hasMany(PurchaseItem::class); }
    public function invoice() { return $this->hasOne(PurchaseInvoice::class); }
    public function payments() { return $this->hasMany(SupplierPayment::class); }
    public function latestPayment() { return $this->hasOne(SupplierPayment::class)->latestOfMany(); }
    public function returns() { return $this->hasMany(PurchaseReturn::class); }
    public function refundSettlements() { return $this->hasMany(PurchaseRefundSettlement::class); }
    public function goodsReceipts() { return $this->hasMany(GoodsReceipt::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function confirmer() { return $this->belongsTo(User::class, 'confirmed_by'); }
    public function paymentAccount() { return $this->belongsTo(Account::class, 'payment_account_id'); }
}
