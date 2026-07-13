<?php

namespace App\Support;

final class BusinessStaffRoles
{
    public const ROLES = [
        'business_admin' => 'Business Admin',
        'business_sub_admin' => 'Business Sub-Admin',
        'manager' => 'Manager',
        'sales_staff' => 'Sales Staff',
        'inventory_staff' => 'Inventory Staff',
        'accountant' => 'Accountant',
        'delivery_staff' => 'Delivery Staff',
        'cashier' => 'Cashier',
        'support_staff' => 'Support Staff',
        'custom_staff' => 'Custom Staff Role',
    ];

    public const DASHBOARD_ROLES = [
        'business_admin',
        'business_sub_admin',
        'manager',
        'sales_staff',
        'inventory_staff',
        'accountant',
        'delivery_staff',
        'cashier',
        'support_staff',
        'custom_staff',
    ];

    public static function defaults(string $role): array
    {
        return match ($role) {
            'business_admin' => [
                'dashboard.view', 'products.view', 'products.create', 'products.edit',
                'inventory.view', 'inventory.add_stock', 'inventory.adjust_stock',
                'customers.view', 'customers.create', 'customers.edit',
                'suppliers.view', 'suppliers.create', 'suppliers.edit',
                'purchases.view', 'purchases.create', 'purchases.receive', 'purchases.pay', 'purchases.return',
                'sales.view', 'sales.create', 'sales.update_status', 'sales.payments', 'accounting.view',
                'deliveries.view', 'deliveries.update_status', 'invoices.view', 'invoices.print',
                'expenses.view', 'expenses.create', 'reports.view', 'staff.view',
            ],
            'business_sub_admin', 'manager' => [
                'dashboard.view', 'products.view', 'products.create', 'products.edit',
                'inventory.view', 'inventory.add_stock', 'inventory.adjust_stock',
                'customers.view', 'customers.create', 'customers.edit',
                'suppliers.view', 'suppliers.create', 'suppliers.edit',
                'purchases.view', 'purchases.create', 'purchases.receive',
                'sales.view', 'sales.create', 'sales.update_status',
                'deliveries.view', 'deliveries.update_status', 'reports.view',
            ],
            'sales_staff' => [
                'dashboard.view', 'products.view', 'customers.view', 'customers.create',
                'sales.view', 'sales.create', 'sales.payments', 'invoices.view', 'invoices.print',
            ],
            'inventory_staff' => [
                'dashboard.view', 'products.view', 'inventory.view', 'inventory.add_stock',
                'inventory.adjust_stock', 'inventory.view_history',
            ],
            'accountant' => [
                'dashboard.view', 'sales.view', 'sales.payments', 'accounting.view',
                'accounting.create_journal', 'expenses.view', 'expenses.create',
                'invoices.view', 'invoices.print', 'reports.view', 'reports.export',
            ],
            'delivery_staff' => [
                'dashboard.view', 'deliveries.view', 'deliveries.update_status',
                'deliveries.upload_proof', 'deliveries.record_collection',
            ],
            'cashier' => [
                'dashboard.view', 'pos.view', 'pos.create_sale', 'pos.print_receipt', 'pos.open_register',
            ],
            'support_staff' => ['dashboard.view'],
            default => [],
        };
    }

    public static function canBeAssignedBy(string $actorRole, string $targetRole): bool
    {
        if ($actorRole === 'super_admin') {
            return array_key_exists($targetRole, self::ROLES);
        }

        if ($actorRole === 'business_owner') {
            return array_key_exists($targetRole, self::ROLES);
        }

        return $actorRole === 'business_admin'
            && array_key_exists($targetRole, self::ROLES)
            && $targetRole !== 'business_admin';
    }
}
