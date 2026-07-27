<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    protected $fillable = ['business_id', 'supplier_id', 'purchase_id', 'account_id', 'created_by', 'amount', 'is_advance', 'applied_amount', 'remaining_amount', 'method', 'reference_number', 'cheque_number', 'cheque_due_date', 'payment_date', 'notes'];
    protected $casts = ['payment_date' => 'date'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function account() { return $this->belongsTo(Account::class); }
    public function advanceApplications() { return $this->hasMany(SupplierAdvanceApplication::class); }
}
