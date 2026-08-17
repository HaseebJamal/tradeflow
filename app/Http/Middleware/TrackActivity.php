<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Services\AuditIpResolver;
use App\Services\AuditDescriptionService;
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
        if (!$routeName || str_contains($routeName, 'heartbeat') || str_contains($routeName, 'audit-logs.live')) {
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
            $friendlyDescription = app(AuditDescriptionService::class)->routeVisit($user, $routeName, $request);
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
                'description' => $friendlyDescription,
                'ip_address' => app(AuditIpResolver::class)->capture($request),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'session_id' => $sessionId,
                'occurred_at' => now(),
            ]);

            if ($user->business_id && !in_array($user->role, ['super_admin', 'platform_admin', 'platform_sub_admin'], true)) {
                AuditLog::create([
                    'business_id' => $user->business_id,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'role' => $user->role,
                    'module' => str($routeName)->replace(['business.', 'staff.'], '')->before('.')->replace('-', ' ')->title()->toString(),
                    'action' => 'page_visit',
                    'description' => $friendlyDescription,
                    'route' => $routeName,
                    'ip_address' => app(AuditIpResolver::class)->capture($request),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    'occurred_at' => now(),
                ]);
            }
        }

        return $response;
    }
}
