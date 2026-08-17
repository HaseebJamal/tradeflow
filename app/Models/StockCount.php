<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCount extends Model
{
    protected $fillable = ['business_id', 'reference', 'counted_at', 'status', 'notes', 'created_by', 'completed_by', 'completed_at', 'cancelled_by', 'cancelled_at'];

    protected $casts = [
        'counted_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function items() { return $this->hasMany(StockCountItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function completedBy() { return $this->belongsTo(User::class, 'completed_by'); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by'); }
}
