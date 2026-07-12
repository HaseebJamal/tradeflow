<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessUserAssignment extends Model
{
    protected $fillable = ['business_id', 'user_id', 'assignment_role', 'assigned_by', 'assigned_at', 'revoked_at', 'status'];

    protected $casts = [
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function assigner() { return $this->belongsTo(User::class, 'assigned_by'); }
}
