<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = ['company_name', 'logo', 'support_email', 'support_phone', 'trial_days', 'default_plan_id', 'max_upload_size'];
}
