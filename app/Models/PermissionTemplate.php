<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionTemplate extends Model
{
    protected $fillable = ['name', 'description', 'created_by', 'status'];

    public function items() { return $this->hasMany(PermissionTemplateItem::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
