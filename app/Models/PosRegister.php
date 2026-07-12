<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosRegister extends Model
{
    protected $fillable = ['business_id', 'user_id', 'opening_cash', 'expected_cash', 'closing_cash', 'variance', 'status', 'opened_at', 'closed_at', 'opening_note', 'closing_note'];

    protected $casts = ['opened_at' => 'datetime', 'closed_at' => 'datetime'];

    public function business() { return $this->belongsTo(Business::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function payments() { return $this->hasMany(PosPayment::class); }
    public function orders() { return $this->hasMany(Order::class); }
}
