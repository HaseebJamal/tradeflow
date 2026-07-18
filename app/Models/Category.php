<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ['business_id', 'name', 'type', 'status', 'description', 'created_by'];

    public function business() { return $this->belongsTo(Business::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
