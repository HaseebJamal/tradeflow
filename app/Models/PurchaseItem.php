<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'product_id', 'product_name_snapshot', 'quantity', 'received_quantity', 'unit_cost', 'selling_price', 'discount_type', 'discount_value', 'discount_amount', 'tax_type', 'tax_value', 'tax_amount', 'line_total'];
    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function returnItems() { return $this->hasMany(PurchaseReturnItem::class); }
}
