<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyApprovalHistory extends Model
{
    protected $fillable = ['business_id', 'old_status', 'new_status', 'changed_by', 'note', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];

    public function business() { return $this->belongsTo(Business::class); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}
