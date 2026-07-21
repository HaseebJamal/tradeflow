<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = ['business_id', 'product_id', 'available_stock', 'sold_stock', 'damaged_stock', 'returned_stock', 'sales_returned_stock', 'purchase_returned_stock', 'low_stock_alert'];

    public function business() { return $this->belongsTo(Business::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
