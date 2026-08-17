<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosRegister extends Model
{
    protected $fillable = [
        'business_id', 'user_id', 'status', 'opening_cash', 'opening_note',
        'opened_at', 'cash_sales', 'cash_refunds', 'cash_in', 'cash_out',
        'closing_cash', 'expected_cash', 'variance', 'closing_note', 'closed_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'cash_sales' => 'decimal:2',
        'cash_refunds' => 'decimal:2',
        'cash_in' => 'decimal:2',
        'cash_out' => 'decimal:2',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function heldSales() { return $this->hasMany(HeldPosSale::class); }
    public function cashMovements() { return $this->hasMany(PosRegisterCashMovement::class, 'pos_register_id'); }
}
