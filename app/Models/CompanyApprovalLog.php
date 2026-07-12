<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyApprovalLog extends Model
{
    protected $fillable = ['company_id', 'old_status', 'new_status', 'note', 'changed_by', 'changed_at'];

    protected $casts = ['changed_at' => 'datetime'];

    public function company() { return $this->belongsTo(Business::class, 'company_id'); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by'); }
}
