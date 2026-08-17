<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosRegisterCashMovement extends Model
{
    protected $fillable = [
        'business_id', 'pos_register_id', 'recorded_by', 'type', 'amount', 'reason', 'reference', 'occurred_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_at' => 'datetime',
    ];

    public function register() { return $this->belongsTo(PosRegister::class, 'pos_register_id'); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by'); }
    public function business() { return $this->belongsTo(Business::class); }
}
