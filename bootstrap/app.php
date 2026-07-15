<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\RejectNegativeNumericInput::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request): string {
            return match ($request->user()?->role) {
                'super_admin' => route('admin.dashboard'),
                'retailer' => route('retailer.dashboard'),
                'custom_staff' => route('staff.dashboard'),
                default => route('business.dashboard'),
            };
        });

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'business.permission' => \App\Http\Middleware\BusinessPermissionMiddleware::class,
            'company.permission' => \App\Http\Middleware\CompanyPermissionMiddleware::class,
            'business.action' => \App\Http\Middleware\BusinessActionPermissionMiddleware::class,
            'business.approved' => \App\Http\Middleware\ApprovedBusinessMiddleware::class,
            'super_admin.context' => \App\Http\Middleware\SuperAdminBusinessContextMiddleware::class,
            'track.activity' => \App\Http\Middleware\TrackActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
