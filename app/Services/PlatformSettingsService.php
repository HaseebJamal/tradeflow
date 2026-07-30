<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class PlatformSettingsService
{
    private const CACHE_KEY = 'tradeflow.platform-settings';

    public function current(): PlatformSetting
    {
        if (! Schema::hasTable('platform_settings')) {
            return new PlatformSetting(['company_name' => 'TradeFlow', 'max_upload_size' => 2048]);
        }

        return Cache::rememberForever(self::CACHE_KEY, fn () => PlatformSetting::query()->firstOrCreate([], [
            'company_name' => 'TradeFlow',
            'max_upload_size' => 2048,
        ]));
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function name(): string
    {
        return $this->current()->company_name ?: 'TradeFlow';
    }
}
