<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PlatformSettingsService
{
    private const CACHE_KEY = 'tradeflow.platform-settings';
    public const DEFAULT_TRIAL_DAYS = 14;

    /**
     * Return the only set of values used when platform branding is restored.
     */
    public function defaultBranding(): array
    {
        return [
            'company_name' => config('tradeflow.platform.name', 'Profit Point'),
            'logo' => config('tradeflow.platform.logo'),
            'support_email' => config('tradeflow.platform.support_email'),
            'support_phone' => config('tradeflow.platform.support_phone'),
            'trial_days' => self::DEFAULT_TRIAL_DAYS,
            'default_paid_access_days' => 30,
            'demo_title' => null,
            'demo_subtitle' => null,
            'demo_video_type' => null,
            'demo_video_url' => null,
            'demo_poster' => null,
            'demo_is_active' => false,
            'demo_en_title' => null, 'demo_en_subtitle' => null, 'demo_en_video_type' => null, 'demo_en_video_url' => null, 'demo_en_poster' => null, 'demo_en_is_active' => false,
            'demo_ur_title' => null, 'demo_ur_subtitle' => null, 'demo_ur_video_type' => null, 'demo_ur_video_url' => null, 'demo_ur_poster' => null, 'demo_ur_is_active' => false,
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

        return Cache::rememberForever($this->cacheKey(), fn () => PlatformSetting::query()->firstOrCreate([], $defaults + [
            'max_upload_size' => 2048,
        ]));
    }

    public function forget(): void
    {
        Cache::forget($this->cacheKey());
    }

    /**
     * A zero-day trial is not a supported state: settings UI validation also
     * requires at least one day. Repair legacy/imported settings rather than
     * making public registration unreachable.
     */
    public function registrationTrialDays(): int
    {
        $settings = $this->current();
        $days = (int) $settings->trial_days;

        if ($days >= 1 && $days <= 365) {
            return $days;
        }

        if (Schema::hasTable('platform_settings') && $settings->exists) {
            $settings->forceFill(['trial_days' => self::DEFAULT_TRIAL_DAYS])->save();
            $this->forget();
        }

        return self::DEFAULT_TRIAL_DAYS;
    }

    /**
     * Cache settings per database. This prevents a cache entry from a prior
     * TradeFlow database from being reused after a production DB switch.
     */
    private function cacheKey(): string
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        return self::CACHE_KEY.'.'.sha1($connection.'|'.$database);
    }

    public function name(): string
    {
        return $this->current()->company_name ?: $this->defaultBranding()['company_name'];
    }

    /**
     * Build the single public click-to-chat URL from the configured Floating
     * WhatsApp setting. Consumers receive null when that contact is inactive
     * or invalid, rather than exposing a broken link.
     */
    public function whatsAppUrl(?string $message = null): ?string
    {
        $settings = $this->current();
        if (! $settings->whatsapp_is_active) {
            return null;
        }

        $number = filled($settings->whatsapp_number)
            ? '+'.ltrim((string) $settings->whatsapp_number, '+')
            : null;
        $digits = app(PhoneNumberService::class)->whatsappDigits($number);
        if (! $digits) {
            return null;
        }

        return 'https://wa.me/'.$digits.(filled($message) ? '?text='.rawurlencode($message) : '');
    }
}
