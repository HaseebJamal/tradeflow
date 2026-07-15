<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id', 'name', 'business_name', 'phone', 'email', 'address', 'city', 'province', 'customer_type',
        'credit_limit', 'opening_balance', 'current_balance', 'created_by', 'status',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function ledgers() { return $this->hasMany(KhataLedger::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function journalLines() { return $this->hasMany(JournalEntryLine::class); }

    /** Customer/person name is the primary identity; shop name is secondary. */
    public function getDisplayNameAttribute(): string
    {
        return (string) ($this->name ?: $this->business_name ?: 'Customer');
    }
}
