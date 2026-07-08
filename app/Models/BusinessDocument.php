<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessDocument extends Model
{
    protected $fillable = ['business_id', 'document_type', 'file_path', 'status'];

    public function business() { return $this->belongsTo(Business::class); }
}
