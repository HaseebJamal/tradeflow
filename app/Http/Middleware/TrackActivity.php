<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if (!$user || !$request->route() || !$request->isMethod('GET')) {
            return $response;
        }

        $routeName = $request->route()->getName();
        if (!$routeName || str_contains($routeName, 'heartbeat')) {
            return $response;
        }

        $sessionId = $request->session()->getId();
        $recent = ActivityLog::where('actor_id', $user->id)
            ->where('route_name', $routeName)
            ->where('session_id', $sessionId)
            ->where('action', 'module_visit')
            ->where('occurred_at', '>=', now()->subSeconds(60))
            ->exists();

        $user->forceFill(['last_seen_at' => now(), 'last_activity_at' => now()])->save();

        if (!$recent && (str_starts_with((string) $routeName, 'admin.') || str_starts_with((string) $routeName, 'business.') || str_starts_with((string) $routeName, 'staff.'))) {
            ActivityLog::create([
                'actor_id' => $user->id,
                'actor_role' => $user->role,
                'actor_name_snapshot' => $user->name,
                'business_id' => $user->business_id,
                'admin_id' => in_array($user->role, ['platform_admin'], true) ? $user->id : null,
                'sub_admin_id' => in_array($user->role, ['platform_sub_admin'], true) ? $user->id : null,
                'module' => str($routeName)->replace(['admin.', 'business.', 'staff.'], '')->before('.')->replace('-', ' ')->title()->toString(),
                'action' => 'module_visit',
                'route_name' => $routeName,
                'method' => $request->method(),
                'description' => $user->name.' opened '.$routeName,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'session_id' => $sessionId,
                'occurred_at' => now(),
            ]);
        }

        return $response;
    }
}
