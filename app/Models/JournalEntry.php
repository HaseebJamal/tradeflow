<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = ['business_id', 'purchase_id', 'goods_receipt_id', 'purchase_return_id', 'voucher_number', 'entry_date', 'reference_type', 'reference_id', 'description', 'status', 'created_by', 'posted_by', 'posted_at'];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function lines() { return $this->hasMany(JournalEntryLine::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function poster() { return $this->belongsTo(User::class, 'posted_by'); }
}
