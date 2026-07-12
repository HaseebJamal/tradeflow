<?php

namespace App\Http\Controllers;

use App\Services\CompanyPermissionService;
use App\Support\BusinessStaffRoles;

class DashboardRedirectController extends Controller
{
    public function __invoke(CompanyPermissionService $companyPermissions)
    {
        $user = auth()->user();
        if (in_array($user->role, BusinessStaffRoles::DASHBOARD_ROLES, true)) {
            return redirect()->route('staff.dashboard');
        }

        return match ($user->role) {
            'super_admin' => redirect()->route('admin.dashboard'),
            'retailer' => redirect()->route('retailer.dashboard'),
            'business_owner' => redirect()->route('business.dashboard'),
            default => redirect()->route('business.dashboard'),
        };
    }
}
