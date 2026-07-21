<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosRegister extends Model
{
    protected $fillable = [
        'business_id', 'user_id', 'status', 'opening_cash', 'opening_note',
        'opened_at', 'closing_cash', 'expected_cash', 'variance', 'closing_note', 'closed_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function heldSales() { return $this->hasMany(HeldPosSale::class); }
}
