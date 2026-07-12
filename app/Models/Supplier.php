<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'supplier_name',
        'company_name',
        'phone',
        'email',
        'address',
        'city',
        'opening_balance',
        'status',
        'created_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function journalLines() { return $this->hasMany(JournalEntryLine::class); }
}
