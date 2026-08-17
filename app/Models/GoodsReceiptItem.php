<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $fillable = ['goods_receipt_id', 'purchase_item_id', 'product_id', 'accepted_quantity', 'damaged_quantity', 'rejected_quantity', 'paid_accepted_quantity', 'free_accepted_quantity', 'paid_damaged_quantity', 'free_damaged_quantity', 'paid_rejected_quantity', 'free_rejected_quantity', 'unit_cost', 'line_total'];

    public function goodsReceipt() { return $this->belongsTo(GoodsReceipt::class); }
    public function purchaseItem() { return $this->belongsTo(PurchaseItem::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
