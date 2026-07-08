<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessReport extends Model
{
    protected $fillable = ['business_id', 'report_type', 'month', 'year', 'total_sales', 'total_orders', 'total_expense', 'profit', 'status', 'approved_by', 'approved_at', 'admin_note'];

    protected $casts = ['approved_at' => 'datetime'];

    public function business() { return $this->belongsTo(Business::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
