<?php

namespace App\Services;

use App\Models\Business;
use App\Models\CompanyPermission;
use App\Models\PermissionDefinition;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

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
                ->mapWithKeys(fn ($item) => [$this->normalise((string) $item->permission_key) => (bool) $item->allowed])
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
            // Older company records may store a module toggle as "categories"
            // while current definitions use "categories.view". Treat both as
            // the same module gate without widening access beyond saved data.
            if (($configuredPermissions[$moduleKey] ?? false) !== true
                && ($configuredPermissions[$module] ?? false) !== true) {
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
        return $this->allowedDefinitionsFor($user)->pluck('permission_key')->values()->all();
    }

    /**
     * The canonical registry shared by Super Admin company configuration and
     * Business Owner role assignment. Legacy aliases are resolved for access,
     * but are never rendered as a second selectable permission.
     */
    public function activeDefinitions(): Collection
    {
        return PermissionDefinition::where('status', 'active')
            ->orderBy('module')
            ->orderBy('label')
            ->get()
            ->filter(fn (PermissionDefinition $definition) => $this->normalise($definition->permission_key) === strtolower($definition->permission_key))
            ->values();
    }

    /** Return only canonical permissions enabled for the user's company. */
    public function allowedDefinitionsFor(User $user, ?Business $business = null): Collection
    {
        return $this->activeDefinitions()
            ->filter(fn (PermissionDefinition $definition) => $this->allows($user, $definition->permission_key, $business))
            ->values();
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

        // Customers is an explicit workspace permission: creating a customer
        // for POS does not by itself grant access to the customer directory.
        if ($permission === 'customers.view') {
            return in_array('customers.view', $assigned, true);
        }

        if (str_ends_with($permission, '.view')) {
            // A module page is the entry point for its granted actions. For
            // example, products.create must allow a staff member to open the
            // Products workspace, without implicitly granting products.edit
            // or any other action. The company module gate above remains the
            // non-bypassable upper limit.
            return in_array($permission, $assigned, true)
                || in_array($module, $assigned, true)
                || collect($assigned)->contains(
                    fn (string $assignedPermission) => str_starts_with($assignedPermission, $module.'.')
                );
        }

        return in_array($permission, $assigned, true);
    }

    public function clear(int $companyId): void
    {
        Cache::forget('tradeflow.company-permissions.'.$companyId);
    }

    private function definitionKeys(): array
    {
        return Cache::remember('tradeflow.permission-definition-keys', now()->addMinutes(30), function (): array {
            $definitions = $this->activeDefinitions();

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
            'view_customers', 'customer.view', 'customers.index', 'customers.manage' => 'customers.view',
            'create_customer' => 'customers.create',
            'customers.add' => 'customers.create',
            'suppliers.add' => 'suppliers.create',
            'orders', 'orders.view' => 'sales.view',
            'orders.create' => 'sales.create',
            'orders.edit' => 'sales.edit',
            'orders.status', 'orders.update_status', 'orders.cancel', 'orders.delete' => 'sales.update_status',
            'orders.print_invoice' => 'sales.invoice_export',
            'orders.assign_delivery' => 'deliveries.assign',
            'payments', 'payments.view', 'payments.record', 'payments.create', 'payments.verify', 'payments.reverse' => 'sales.payments',
            'invoices', 'invoices.view', 'invoices.create' => 'sales.invoices',
            'invoices.print', 'invoices.export' => 'sales.invoice_export',
            'invoices.void' => 'sales.invoice_void',
            'purchases.return' => 'purchase_returns.process',
            'purchase_returns' => 'purchase_returns.view',
            'sales_returns' => 'sales_returns.view',
            'deliveries.update' => 'deliveries.update_status',
            'expenses.add' => 'expenses.create',
            'settings.manage' => 'settings.update',
            default => str_contains($permission, '.') ? $permission : $permission.'.view',
        };
    }
}
