<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'product_id', 'product_name_snapshot', 'unit_snapshot', 'quantity', 'free_quantity', 'received_quantity', 'damaged_quantity', 'rejected_quantity', 'unit_cost', 'discount_type', 'discount_value', 'discount_amount', 'tax_type', 'tax_value', 'tax_amount', 'line_total'];
    public function goodsReceiptItems() { return $this->hasMany(GoodsReceiptItem::class); }
    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function returnItems() { return $this->hasMany(PurchaseReturnItem::class); }
}
