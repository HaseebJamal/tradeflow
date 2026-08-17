<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    protected $fillable = ['purchase_return_id', 'purchase_item_id', 'product_id', 'quantity', 'paid_quantity', 'free_quantity', 'unit_cost', 'line_total'];
    public function purchaseReturn() { return $this->belongsTo(PurchaseReturn::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function purchaseItem() { return $this->belongsTo(PurchaseItem::class); }
}
