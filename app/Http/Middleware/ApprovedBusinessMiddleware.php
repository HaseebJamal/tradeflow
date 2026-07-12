<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApprovedBusinessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user && $user->business, 403);

        if ($user->role === 'super_admin' && $request->session()->has('super_admin_business_context_id')) {
            return $next($request);
        }

        if (!in_array($user->business->status, ['Approved', 'approved'], true)) {
            auth()->logout();
            return redirect()->route('login')->withErrors(['email' => 'Your business is not approved for dashboard access.']);
        }

        return $next($request);
    }
}
