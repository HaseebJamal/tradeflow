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

        abort_unless(in_array(auth()->user()->role, $roles, true), 403);

        return $next($request);
    }
}
