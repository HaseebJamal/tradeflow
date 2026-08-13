<?php

namespace App\Http\Middleware;

use App\Services\CompanyPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user || !$user->business_id || !app(CompanyPermissionService::class)->allows($user, $permission)) {
            $message = 'This feature is not enabled for your company. Please contact TradeFlow support.';

            abort(403, $message);
        }

        return $next($request);
    }
}
