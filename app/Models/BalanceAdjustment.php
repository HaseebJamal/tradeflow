<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceAdjustment extends Model
{
    protected $fillable = [
        'business_id', 'party_type', 'party_id', 'reference', 'adjustment_type',
        'amount', 'previous_balance', 'new_balance', 'reason', 'external_reference',
        'notes', 'submission_token', 'created_by', 'reverses_adjustment_id', 'reversed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'previous_balance' => 'decimal:2',
        'new_balance' => 'decimal:2',
        'reversed_at' => 'datetime',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function reversalOf() { return $this->belongsTo(self::class, 'reverses_adjustment_id'); }
    public function reversal() { return $this->hasOne(self::class, 'reverses_adjustment_id'); }
}
