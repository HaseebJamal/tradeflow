<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\PlatformSettingsService;

class PlatformSetting extends Model
{
    protected $fillable = [
        'company_name', 'logo', 'support_email', 'support_phone', 'trial_days', 'default_paid_access_days', 'renewal_invoice_reminder_days', 'default_plan_id', 'max_upload_size',
        'demo_title', 'demo_subtitle', 'demo_video_type', 'demo_video_url', 'demo_poster', 'demo_is_active',
        'demo_en_title', 'demo_en_subtitle', 'demo_en_video_type', 'demo_en_video_url', 'demo_en_poster', 'demo_en_is_active',
        'demo_ur_title', 'demo_ur_subtitle', 'demo_ur_video_type', 'demo_ur_video_url', 'demo_ur_poster', 'demo_ur_is_active',
        'whatsapp_number', 'whatsapp_message', 'whatsapp_tooltip', 'whatsapp_is_active',
    ];

    protected $casts = [
        'demo_is_active' => 'boolean',
        'demo_en_is_active' => 'boolean',
        'demo_ur_is_active' => 'boolean',
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
