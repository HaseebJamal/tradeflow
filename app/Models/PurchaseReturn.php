<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    protected $fillable = ['business_id', 'purchase_id', 'supplier_id', 'created_by', 'return_number', 'return_date', 'total_amount', 'reason'];
    protected $casts = ['return_date' => 'date'];
    public function items() { return $this->hasMany(PurchaseReturnItem::class); }
    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
}
