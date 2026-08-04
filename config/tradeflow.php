<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform branding defaults
    |--------------------------------------------------------------------------
    |
    | These are the single fallback values used whenever Super Admin restores
    | the platform defaults. A null logo intentionally uses the existing
    | built-in TradeFlow icon rendered by the shared layouts.
    |
    */
    'platform' => [
        'name' => env('TRADEFLOW_PLATFORM_NAME', 'TradeFlow'),
        'logo' => null,
        'support_email' => env('TRADEFLOW_SUPPORT_EMAIL'),
        'support_phone' => env('TRADEFLOW_SUPPORT_PHONE'),
    ],
];
