<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'business_id',
        'product_id',
        'type',
        'quantity',
        'previous_stock',
        'new_stock',
        'note',
        'created_by',
        'movement_date',
        'goods_receipt_id',
    ];

    protected $casts = [
        'movement_date' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function product() { return $this->belongsTo(Product::class)->withTrashed(); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
