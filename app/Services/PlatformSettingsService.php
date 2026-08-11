<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PlatformSettingsService
{
    private const CACHE_KEY = 'tradeflow.platform-settings';

    /**
     * Return the only set of values used when platform branding is restored.
     */
    public function defaultBranding(): array
    {
        return [
            'company_name' => config('tradeflow.platform.name', 'TradeFlow'),
            'logo' => config('tradeflow.platform.logo'),
            'support_email' => config('tradeflow.platform.support_email'),
            'support_phone' => config('tradeflow.platform.support_phone'),
            'default_paid_access_days' => 30,
            'demo_title' => null,
            'demo_subtitle' => null,
            'demo_video_type' => null,
            'demo_video_url' => null,
            'demo_poster' => null,
            'demo_is_active' => false,
            'whatsapp_number' => null,
            'whatsapp_message' => null,
            'whatsapp_tooltip' => null,
            'whatsapp_is_active' => false,
        ];
    }

    public function current(): PlatformSetting
    {
        $defaults = $this->defaultBranding();

        if (! Schema::hasTable('platform_settings')) {
            return new PlatformSetting($defaults + ['max_upload_size' => 2048]);
        }

        return Cache::rememberForever(self::CACHE_KEY, fn () => PlatformSetting::query()->firstOrCreate([], $defaults + [
            'max_upload_size' => 2048,
        ]));
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function name(): string
    {
        return $this->current()->company_name ?: $this->defaultBranding()['company_name'];
    }
}
