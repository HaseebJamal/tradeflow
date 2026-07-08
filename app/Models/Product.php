<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['business_id', 'category_id', 'name', 'image', 'sku', 'barcode', 'batch_number', 'expiry_date', 'retail_price', 'wholesale_price', 'purchase_cost', 'minimum_order_quantity', 'stock_quantity', 'low_stock_alert_qty', 'unit', 'status'];

    protected $casts = ['expiry_date' => 'date'];

    public function business() { return $this->belongsTo(Business::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function inventory() { return $this->hasOne(Inventory::class); }
    public function movements() { return $this->hasMany(StockMovement::class); }
}
