<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyPermission extends Model
{
    protected $fillable = ['company_id', 'permission_key', 'allowed', 'assigned_by'];

    protected $casts = ['allowed' => 'boolean'];

    public function company() { return $this->belongsTo(Business::class, 'company_id'); }
    public function assigner() { return $this->belongsTo(User::class, 'assigned_by'); }
}
