<?php

namespace App\Providers;

use App\Services\CompanyPermissionService;
use App\Services\PlatformSettingsService;
use Illuminate\Support\Facades\Config;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Dashboard pages use Bootstrap; Laravel's Tailwind paginator leaves
        // unstyled SVG navigation arrows when rendered here.
        Paginator::useBootstrapFive();
        Blade::if('companyCan', fn (string $permission) => app(CompanyPermissionService::class)->allowsUser(auth()->user(), $permission));
        Blade::if('businessCan', fn (string $permission) => app(CompanyPermissionService::class)->allowsUser(auth()->user(), $permission));
        $platformSettings = app(PlatformSettingsService::class)->current();
        Config::set('app.name', $platformSettings->company_name ?: 'TradeFlow');
        View::composer('*', fn ($view) => $view->with('platformSettings', app(PlatformSettingsService::class)->current()));
    }
}
