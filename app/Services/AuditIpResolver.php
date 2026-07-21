<?php

namespace App\Services;

use Illuminate\Http\Request;

class AuditIpResolver
{
    /**
     * Resolve client IPs through Laravel's trusted-proxy-aware request API.
     * Forwarding headers are never read directly.
     */
    public function capture(?Request $request = null): ?string
    {
        $request ??= request();

        return self::normalize($request?->ip());
    }

    public static function normalize(?string $ip): ?string
    {
        return $ip === '::1' ? '127.0.0.1' : $ip;
    }

    public static function display(?string $ip, string $fallback = '-'): string
    {
        return self::normalize($ip) ?: $fallback;
    }

    /** @return array<int, string> */
    public static function searchable(?string $ip): array
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return ['127.0.0.1', '::1'];
        }

        return $ip ? [$ip] : [];
    }
}
