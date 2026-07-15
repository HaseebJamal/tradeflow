<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDetailChangeRequest extends Model
{
    protected $fillable = [
        'business_id', 'user_id', 'old_values', 'requested_values', 'reason',
        'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'requested_values' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function business() { return $this->belongsTo(Business::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
