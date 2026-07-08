<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['title', 'message', 'target_type', 'business_id', 'role'];

    public function business() { return $this->belongsTo(Business::class); }
}
