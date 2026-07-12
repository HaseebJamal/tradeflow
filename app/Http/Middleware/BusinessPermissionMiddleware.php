<?php

namespace App\Http\Middleware;

use App\Services\CompanyPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BusinessPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        abort_unless($user && $user->business_id, 403);

        $permissions = app(CompanyPermissionService::class);
        $modulePermission = $permissions->normalise($permission.'.view');

        if (!$permissions->allows($user, $modulePermission)) {
            return redirect()
                ->back()
                ->withErrors(['company_permission' => 'This feature is not enabled for your company. Please contact TradeFlow support.']);
        }

        if (!$permissions->allowsUser($user, $modulePermission)) {
            $message = 'You do not have permission to access this module. Please contact your business owner.';

            if ($request->expectsJson()) {
                abort(403, $message);
            }

            return redirect()
                ->route(in_array($user->role, \App\Support\BusinessStaffRoles::DASHBOARD_ROLES, true) ? 'staff.dashboard' : 'business.dashboard')
                ->withErrors(['permission' => $message]);
        }

        return $next($request);
    }
}
