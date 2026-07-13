<?php

namespace App\Services;

use App\Models\Business;
use App\Models\CompanyPermission;
use App\Models\PermissionDefinition;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class CompanyPermissionService
{
    public function allows(?User $user, string $permission, ?Business $business = null): bool
    {
        if (!$user) {
            return false;
        }

        // A Super Admin has unrestricted platform access.  Once they open a
        // company dashboard, SuperAdminBusinessContextMiddleware attaches that
        // company to the user; from that point the company's enabled modules
        // and features must remain the ceiling for the preview as well.
        if ($user->role === 'super_admin' && !$business && !$user->business_id) {
            return true;
        }

        $business ??= $user->business;
        if (!$business || ($user->business_id && $business->id !== $user->business_id)) {
            return false;
        }

        $permission = $this->normalise($permission);
        $configuredPermissions = Cache::remember(
            'tradeflow.company-permissions.'.$business->id,
            now()->addMinutes(30),
            fn () => CompanyPermission::where('company_id', $business->id)
                ->get(['permission_key', 'allowed'])
                ->mapWithKeys(fn ($item) => [strtolower(trim($item->permission_key)) => (bool) $item->allowed])
                ->all()
        );

        if ($configuredPermissions === []) {
            return false;
        }

        $module = str($permission)->before('.')->toString();
        $definitions = $this->definitionKeys();
        $moduleKey = $definitions['modules'][$module] ?? null;
        $requestedKey = $definitions['permissions'][$permission] ?? $permission;

        // Module access is the hard ceiling. A stale child action can never
        // keep a disabled module visible or reachable.
        if ($moduleKey !== null) {
            if (($configuredPermissions[$moduleKey] ?? false) !== true) {
                return false;
            }
        } elseif (($configuredPermissions[$module] ?? false) !== true
            && ($configuredPermissions[$module.'.view'] ?? false) !== true) {
            return false;
        }

        if (str_ends_with($permission, '.view')) {
            return true;
        }

        return ($configuredPermissions[$requestedKey] ?? false) === true;
    }

    public function availableKeys(User $user): array
    {
        $keys = \App\Models\PermissionDefinition::where('status', 'active')->pluck('permission_key')->all();

        return array_values(array_filter($keys, fn ($key) => $this->allows($user, $key)));
    }

    /**
     * Resolves the permission a business user can actually use. Staff access is
     * always capped by the current company's enabled permissions.
     */
    public function allowsUser(?User $user, string $permission, ?Business $business = null): bool
    {
        if (!$this->allows($user, $permission, $business)) {
            return false;
        }

        if (in_array($user?->role, ['super_admin', 'business_owner'], true)) {
            return true;
        }

        if (!$user?->business_id || $user->status !== 'active') {
            return false;
        }

        $permission = $this->normalise($permission);
        $module = str($permission)->before('.')->toString();
        $assigned = collect($user->permissions ?? [])
            ->map(fn ($value) => $this->normalise((string) $value))
            ->unique()
            ->all();

        return in_array($permission, $assigned, true)
            || (str_ends_with($permission, '.view') && in_array($module, $assigned, true));
    }

    public function clear(int $companyId): void
    {
        Cache::forget('tradeflow.company-permissions.'.$companyId);
    }

    private function definitionKeys(): array
    {
        return Cache::remember('tradeflow.permission-definition-keys', now()->addMinutes(30), function () {
            $definitions = PermissionDefinition::where('status', 'active')
                ->get(['module', 'permission_key', 'permission_type']);

            return [
                'modules' => $definitions
                    ->where('permission_type', 'module')
                    ->mapWithKeys(fn (PermissionDefinition $definition) => [strtolower($definition->module) => strtolower($definition->permission_key)])
                    ->all(),
                'permissions' => $definitions
                    ->mapWithKeys(fn (PermissionDefinition $definition) => [$this->normalise($definition->permission_key) => strtolower($definition->permission_key)])
                    ->all(),
            ];
        });
    }

    public function normalise(string $permission): string
    {
        $permission = strtolower(trim($permission));
        $permission = str_replace([' ', '-'], ['_', '_'], $permission);

        return match ($permission) {
            'khata', 'khata.view' => 'accounting.view',
            'khata.add' => 'accounting.create_journal',
            'accounting' => 'accounting.view',
            'products.add' => 'products.create',
            'inventory.add' => 'inventory.add_stock',
            'inventory.adjust' => 'inventory.adjust_stock',
            'customers.add' => 'customers.create',
            'suppliers.add' => 'suppliers.create',
            'orders', 'orders.view' => 'sales.view',
            'orders.create', 'orders.edit' => 'sales.create',
            'orders.status', 'orders.update_status', 'orders.cancel', 'orders.delete', 'orders.assign_delivery', 'orders.void' => 'sales.update_status',
            'payments', 'payments.view' => 'sales.view',
            'payments.record', 'payments.create' => 'sales.payments',
            'deliveries.update' => 'deliveries.update_status',
            'expenses.add' => 'expenses.create',
            'settings.manage' => 'settings.update',
            default => str_contains($permission, '.') ? $permission : $permission.'.view',
        };
    }
}
