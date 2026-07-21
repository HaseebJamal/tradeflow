<?php

namespace App\Models;

use App\Services\AuditIpResolver;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'actor_id', 'actor_role', 'actor_name_snapshot', 'business_id', 'admin_id', 'sub_admin_id',
        'module', 'action', 'route_name', 'method', 'description', 'subject_type', 'subject_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'session_id', 'occurred_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
    public function business() { return $this->belongsTo(Business::class); }
    public function admin() { return $this->belongsTo(User::class, 'admin_id'); }
    public function subAdmin() { return $this->belongsTo(User::class, 'sub_admin_id'); }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->ip_address = app(AuditIpResolver::class)->capture()
                ?? AuditIpResolver::normalize($log->ip_address);
        });
    }
}
