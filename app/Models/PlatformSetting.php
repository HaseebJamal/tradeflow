<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\PlatformSettingsService;

class PlatformSetting extends Model
{
    protected $fillable = ['company_name', 'logo', 'support_email', 'support_phone', 'trial_days', 'default_plan_id', 'max_upload_size'];

    public static function current(): self
    {
        return app(PlatformSettingsService::class)->current();
    }

    public static function forgetCurrent(): void
    {
        app(PlatformSettingsService::class)->forget();
    }
}
