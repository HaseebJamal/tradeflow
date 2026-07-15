<?php

namespace App\Http\Controllers;

use App\Services\BusinessWorkspaceAccessService;

class DashboardRedirectController extends Controller
{
    public function __invoke(BusinessWorkspaceAccessService $workspaceAccess)
    {
        $user = auth()->user();
        $platformRoute = match ($user->role) {
            'super_admin' => redirect()->route('admin.dashboard'),
            'retailer' => redirect()->route('retailer.dashboard'),
            default => null,
        };

        if ($platformRoute) {
            return $platformRoute;
        }

        return redirect()->route($workspaceAccess->firstEnabledRoute($user) ?? 'business.access-denied');
    }
}
