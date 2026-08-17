<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $fillable = ['business_id', 'purchase_id', 'supplier_id', 'grn_number', 'submission_token', 'attachment_path', 'received_at', 'created_by'];
    protected $casts = ['received_at' => 'datetime'];

    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function items() { return $this->hasMany(GoodsReceiptItem::class); }
    public function batches() { return $this->hasMany(ProductBatch::class); }
}
