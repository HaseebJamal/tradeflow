<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(auth()->check(), 403);
        $roles = collect($roles)
            ->flatMap(fn (string $role) => array_map('trim', explode(',', $role)))
            ->filter()
            ->values()
            ->all();

        if (auth()->user()->status !== 'active') {
            auth()->logout();
            return redirect()->route('login')->withErrors(['email' => 'Your account is inactive. Please contact your business owner.']);
        }

        $user = auth()->user();
        if ($user->role === 'super_admin' && in_array('super_admin', $roles, true) && $request->is('business/*')) {
            abort_unless($request->session()->has('super_admin_business_context_id'), 403);

            return $next($request);
        }

        abort_unless(in_array($user->role, $roles, true), 403);

        return $next($request);
    }
}
