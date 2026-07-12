<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionDefinition extends Model
{
    protected $fillable = ['module', 'permission_type', 'permission_key', 'label', 'description', 'status'];
}
