<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BusinessPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        abort_unless($user && $user->business_id, 403);

        if ($user->role === 'business_owner') {
            return $next($request);
        }

        $module = strtolower($permission);
        $permissions = collect($user->permissions ?? [])->map(fn ($value) => strtolower($value))->all();
        $permissionAllowsModule = in_array($module, $permissions, true)
            || collect($permissions)->contains(fn ($value) => str_starts_with($value, $module.'.'));

        if (!$permissionAllowsModule) {
            $message = 'You do not have permission to access this module. Please contact your business owner.';

            if ($request->expectsJson()) {
                abort(403, $message);
            }

            return redirect()
                ->route(in_array($user->role, ['manager', 'sales_staff', 'inventory_staff', 'accountant', 'delivery_staff'], true) ? 'staff.dashboard' : 'business.dashboard')
                ->withErrors(['permission' => $message]);
        }

        return $next($request);
    }
}
