<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['business_id', 'product_id', 'goods_receipt_id', 'stock_count_id', 'product_batch_id', 'type', 'quantity', 'reason', 'note', 'user_id', 'created_by'];

    public function product() { return $this->belongsTo(Product::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function batch() { return $this->belongsTo(ProductBatch::class, 'product_batch_id'); }
}
