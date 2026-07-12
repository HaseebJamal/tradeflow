<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'user_name', 'role', 'actor_id', 'actor_role', 'business_id', 'target_user_id', 'action', 'module', 'route', 'record_type', 'record_id', 'description', 'old_values', 'new_values', 'ip_address', 'user_agent', 'occurred_at'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $user = auth()->user();
            $log->user_id ??= $user?->id;
            $log->actor_id ??= $user?->id;
            $log->user_name ??= $user?->name;
            $log->role ??= $log->actor_role ?? $user?->role;
            $log->actor_role ??= $user?->role;
            $log->business_id ??= $user?->business_id;
            $log->route ??= request()?->route()?->getName();
            if ($log->record_id && !$log->record_type) $log->record_type = $log->module;
            $log->ip_address ??= request()?->ip();
            $log->user_agent ??= substr((string) request()?->userAgent(), 0, 1000);
            $log->occurred_at ??= now();
            $log->old_values = self::withoutSensitiveValues($log->old_values);
            $log->new_values = self::withoutSensitiveValues($log->new_values);
        });
    }

    private static function withoutSensitiveValues(mixed $values): mixed
    {
        if (!is_array($values)) return $values;

        foreach ($values as $key => $value) {
            if (preg_match('/password|token|proof|image|file/i', (string) $key)) {
                unset($values[$key]);
            } elseif (is_array($value)) {
                $values[$key] = self::withoutSensitiveValues($value);
            }
        }

        return $values;
    }
}
