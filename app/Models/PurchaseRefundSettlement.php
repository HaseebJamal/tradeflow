<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRefundSettlement extends Model
{
    protected $fillable = [
        'business_id', 'purchase_id', 'supplier_id', 'created_by',
        'amount', 'method', 'reference_number', 'settled_at', 'notes',
    ];

    protected $casts = ['amount' => 'decimal:2', 'settled_at' => 'datetime'];

    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
