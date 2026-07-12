<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['business_id', 'code', 'name', 'account_type', 'normal_balance', 'status'];

    public function business() { return $this->belongsTo(Business::class); }
    public function lines() { return $this->hasMany(JournalEntryLine::class); }
}
