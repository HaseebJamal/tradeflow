<?php

namespace App\Providers;

use App\Services\CompanyPermissionService;
use Illuminate\Support\Facades\Blade;
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
        Blade::if('companyCan', fn (string $permission) => app(CompanyPermissionService::class)->allowsUser(auth()->user(), $permission));
        Blade::if('businessCan', fn (string $permission) => app(CompanyPermissionService::class)->allowsUser(auth()->user(), $permission));
    }
}
