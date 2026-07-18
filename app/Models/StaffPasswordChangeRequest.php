<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffPasswordChangeRequest extends Model
{
    protected $fillable = [
        'business_id', 'user_id', 'reason', 'status', 'requested_at',
        'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function business() { return $this->belongsTo(Business::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
