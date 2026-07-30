<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessDocument extends Model
{
    protected $fillable = [
        'business_id', 'document_type', 'file_path', 'status',
        'verified_by', 'verified_at',
        'rejected_by', 'rejected_at', 'rejection_reason',
        'reupload_requested_by', 'reupload_requested_at', 'reupload_reason',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
        'reupload_requested_at' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function verifiedBy() { return $this->belongsTo(User::class, 'verified_by'); }
    public function rejectedBy() { return $this->belongsTo(User::class, 'rejected_by'); }
    public function reuploadRequestedBy() { return $this->belongsTo(User::class, 'reupload_requested_by'); }
}
