<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id', 'category_id', 'unit_id', 'name', 'image', 'barcode', 'batch_number', 'manufacturing_date',
        'expiry_date', 'expiry_alert_days', 'retail_price', 'wholesale_price', 'purchase_cost', 'latest_purchase_price', 'average_purchase_price', 'opening_stock',
        'current_stock', 'minimum_order_quantity', 'stock_quantity', 'low_stock_alert_qty', 'unit', 'description',
        'brand', 'manufacturer', 'warehouse_location', 'has_batch_tracking', 'created_by', 'added_date', 'status',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'manufacturing_date' => 'date',
        'has_batch_tracking' => 'boolean',
        'added_date' => 'datetime',
        'latest_purchase_price' => 'decimal:2',
        'average_purchase_price' => 'decimal:2',
    ];

    /**
     * Keep every server-rendered and JSON product consumer on the exact same
     * public-storage URL. POS used to receive a raw path after an AJAX search
     * while its initial Blade cards received Storage::url(), which made an
     * uploaded image disappear as soon as the product panel was rebuilt.
     */
    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    /**
     * Selling prices are compared to the current receipt-derived purchase
     * price, matching the read-only value shown on the product form.
     */
    public function currentPurchasePrice(): float
    {
        return (float) ($this->latest_purchase_price
            ?? $this->average_purchase_price
            ?? $this->purchase_cost
            ?? 0);
    }

    public function hasPricingAttention(?float $purchasePrice = null): bool
    {
        $purchasePrice ??= $this->currentPurchasePrice();

        return $purchasePrice > 0
            && ((float) $this->retail_price <= $purchasePrice
                || (float) $this->wholesale_price <= $purchasePrice);
    }

    public function business() { return $this->belongsTo(Business::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function unitRecord() { return $this->belongsTo(Unit::class, 'unit_id'); }
    public function inventory() { return $this->hasOne(Inventory::class); }
    public function movements() { return $this->hasMany(StockMovement::class); }
    public function inventoryMovements() { return $this->hasMany(InventoryMovement::class); }
    public function purchaseItems() { return $this->hasMany(PurchaseItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
