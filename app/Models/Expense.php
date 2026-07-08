<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['business_id', 'category', 'title', 'amount', 'date', 'expense_date', 'description'];

    protected $casts = ['date' => 'date', 'expense_date' => 'date'];

    public function business() { return $this->belongsTo(Business::class); }
}
