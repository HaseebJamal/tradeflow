<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessFooterChangeRequest extends Model
{
    protected $fillable = [
        'business_id', 'requester_id', 'field', 'current_value', 'requested_value',
        'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function business() { return $this->belongsTo(Business::class); }
    public function requester() { return $this->belongsTo(User::class, 'requester_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
