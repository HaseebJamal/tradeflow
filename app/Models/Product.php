<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id', 'category_id', 'name', 'image', 'sku', 'barcode', 'batch_number', 'manufacturing_date',
        'expiry_date', 'expiry_alert_days', 'retail_price', 'wholesale_price', 'purchase_cost', 'opening_stock',
        'current_stock', 'minimum_order_quantity', 'stock_quantity', 'low_stock_alert_qty', 'unit', 'description',
        'brand', 'manufacturer', 'warehouse_location', 'has_batch_tracking', 'created_by', 'added_date', 'status',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'manufacturing_date' => 'date',
        'has_batch_tracking' => 'boolean',
        'added_date' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function inventory() { return $this->hasOne(Inventory::class); }
    public function movements() { return $this->hasMany(StockMovement::class); }
    public function inventoryMovements() { return $this->hasMany(InventoryMovement::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
