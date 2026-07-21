<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;

class BusinessWorkspaceAccessService
{
    /**
     * Finds a safe landing route from the modules currently enabled for this
     * company and user. A disabled dashboard is never used as a fallback.
     */
    public function firstEnabledRoute(User $user, ?Business $business = null): ?string
    {
        $business ??= $user->business;

        foreach ([
            'dashboard' => 'business.dashboard',
            'products' => 'business.products.index',
            'units' => 'business.units.index',
            'categories' => 'business.categories.index',
            'inventory' => 'business.inventory',
            'suppliers' => 'business.suppliers.index',
            'purchases' => 'business.purchases.index',
            'purchase_returns' => 'business.purchase-returns.index',
            'customers' => 'business.customers.index',
            'sales' => 'business.sales.index',
            'pos' => 'business.pos.index',
            'sales_returns' => 'business.sales.returns.index',
            'deliveries' => 'business.deliveries',
            'accounting' => 'business.khata',
            'expenses' => 'business.expenses.index',
            'reports' => 'business.reports',
            'staff' => 'business.staff',
            'audit_logs' => 'business.audit-logs.index',
            'settings' => 'business.settings',
        ] as $module => $route) {
            if (app(CompanyPermissionService::class)->allowsUser($user, $module.'.view', $business)) {
                return $route;
            }
        }

        return null;
    }
}
