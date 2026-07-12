<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionTemplateItem extends Model
{
    protected $fillable = ['permission_template_id', 'permission_key', 'allowed'];

    protected $casts = ['allowed' => 'boolean'];

    public function template() { return $this->belongsTo(PermissionTemplate::class, 'permission_template_id'); }
}
