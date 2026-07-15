<?php

namespace App\Support;

final class BusinessStaffRoles
{
    public const ROLES = [
        'custom_staff' => 'Custom Role',
    ];

    public const DASHBOARD_ROLES = [
        'custom_staff',
    ];

    public static function defaults(string $role): array
    {
        return [];
    }

    public static function canBeAssignedBy(string $actorRole, string $targetRole): bool
    {
        return in_array($actorRole, ['super_admin', 'business_owner'], true)
            && $targetRole === 'custom_staff';
    }
}
