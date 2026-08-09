<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\PlatformSettingsService;

class PlatformSetting extends Model
{
    protected $fillable = [
        'company_name', 'logo', 'support_email', 'support_phone', 'trial_days', 'default_plan_id', 'max_upload_size',
        'demo_title', 'demo_subtitle', 'demo_video_type', 'demo_video_url', 'demo_poster', 'demo_is_active',
        'whatsapp_number', 'whatsapp_message', 'whatsapp_tooltip', 'whatsapp_is_active',
    ];

    protected $casts = [
        'demo_is_active' => 'boolean',
        'whatsapp_is_active' => 'boolean',
    ];

    public static function current(): self
    {
        return app(PlatformSettingsService::class)->current();
    }

    public static function forgetCurrent(): void
    {
        app(PlatformSettingsService::class)->forget();
    }
}
