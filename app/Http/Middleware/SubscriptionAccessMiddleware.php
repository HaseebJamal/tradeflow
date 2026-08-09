<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Services\SubscriptionLifecycleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->role === 'super_admin') {
            return $next($request);
        }

        $business = $user->business ?: Business::find($user->business_id);
        if (! $business) {
            return $next($request);
        }

        $state = app(SubscriptionLifecycleService::class)->forBusiness($business);
        if ($state['can_access_business']) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'message' => 'Your workspace access has ended. Please sign in again when access is restored.',
                'redirect' => route('login'),
            ], 401);
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('access_ended', true);
    }
}
