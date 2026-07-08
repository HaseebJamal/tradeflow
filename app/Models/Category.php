<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['business_id', 'name', 'type', 'status'];

    public function business() { return $this->belongsTo(Business::class); }
    public function products() { return $this->hasMany(Product::class); }
}
