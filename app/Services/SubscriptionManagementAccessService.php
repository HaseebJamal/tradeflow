<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;

/**
 * Resolves access to sensitive business subscription information and actions.
 */
class SubscriptionManagementAccessService
{
    private const MANAGEMENT_PERMISSIONS = [
        'subscriptions.request',
        'subscriptions.upgrade',
        'subscriptions.downgrade',
        'subscriptions.renew',
        'subscriptions.cancel',
    ];

    public function __construct(private readonly CompanyPermissionService $permissions)
    {
    }

    public function canManage(?User $user, ?Business $business = null): bool
    {
        if (! $user || ! $this->permissions->allowsUser($user, 'subscriptions.view', $business)) {
            return false;
        }

        // Owners and Super Admin preview sessions may manage the enabled
        // subscription module. Staff additionally need an explicit action.
        if (in_array($user->role, ['business_owner', 'super_admin'], true)) {
            return true;
        }

        foreach (self::MANAGEMENT_PERMISSIONS as $permission) {
            if ($this->permissions->allowsUser($user, $permission, $business)) {
                return true;
            }
        }

        return false;
    }
}
