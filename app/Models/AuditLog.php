<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'actor_id', 'actor_role', 'business_id', 'target_user_id', 'action', 'module', 'record_id', 'description', 'old_values', 'new_values', 'ip_address', 'user_agent'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }
}
