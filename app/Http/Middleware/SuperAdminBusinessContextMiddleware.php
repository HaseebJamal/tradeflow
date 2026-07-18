<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminBusinessContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $businessId = $request->session()->get('super_admin_business_context_id');

        if (!$user || $user->role !== 'super_admin' || !$businessId) {
            return $next($request);
        }

        $business = Business::find($businessId);
        if (!$business) {
            $request->session()->forget(['super_admin_business_context_id', 'super_admin_business_context_name']);

            return redirect()->route('admin.dashboard')->withErrors([
                'company_context' => 'The selected company is no longer available.',
            ]);
        }

        $user->setAttribute('business_id', $business->id);
        $user->setRelation('business', $business);
        $request->attributes->set('super_admin_business_context', $business);

        return $next($request);
    }
}
