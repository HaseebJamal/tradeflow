<?php

namespace App\Http\Middleware;

use App\Services\CompanyPermissionService;
use App\Services\BusinessWorkspaceAccessService;
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

            if ($request->expectsJson()) {
                abort(403, $message);
            }

            $destination = $user ? app(BusinessWorkspaceAccessService::class)->firstEnabledRoute($user) : null;

            return redirect()->route($destination ?? 'business.access-denied')->withErrors(['company_permission' => $message]);
        }

        return $next($request);
    }
}
