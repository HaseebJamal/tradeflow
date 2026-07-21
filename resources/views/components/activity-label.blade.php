@props(['activity', 'field' => 'action'])

@php
    $route = strtolower(trim((string) ($activity->route ?? $activity->route_name ?? '')));
    $action = strtolower(trim((string) ($activity->action ?? '')));
    $rawModule = strtolower(trim((string) ($activity->module ?? '')));

    $moduleLabels = [
        'dashboard' => 'Dashboard', 'companies' => 'Companies', 'company' => 'Company',
        'products' => 'Products', 'inventory' => 'Inventory', 'customers' => 'Customers',
        'suppliers' => 'Suppliers', 'sales' => 'Sales', 'orders' => 'Sales', 'pos' => 'POS',
        'purchases' => 'Purchases', 'purchase returns' => 'Purchase Returns',
        'sales returns' => 'Sales Returns', 'payments' => 'Payments',
        'deliveries' => 'Deliveries', 'invoices' => 'Invoices',
        'accounting' => 'Accounting', 'ledger' => 'Accounting', 'reports' => 'Reports',
        'staff' => 'Staff', 'settings' => 'Settings', 'permissions' => 'Permissions',
        'subscriptions' => 'Subscriptions', 'notifications' => 'Notifications',
        'audit logs' => 'Audit Logs', 'authentication' => 'Authentication',
        'profile' => 'Profile', 'support' => 'Support',
    ];

    $moduleKey = str_replace(['_', '-'], ' ', $rawModule);
    $moduleLabel = $moduleLabels[$moduleKey] ?? ($moduleKey && !str_contains($moduleKey, '.')
        ? \Illuminate\Support\Str::title($moduleKey)
        : 'General');

    if ($field === 'module') {
        $label = $moduleLabel;
    } else {
        $routeLabels = [
            'admin.dashboard' => 'Viewed Dashboard',
            'admin.companies.index' => 'Viewed Companies',
            'admin.companies.show' => 'Viewed Company Details',
            'admin.audit-logs' => 'Viewed Audit Logs',
            'business.dashboard' => 'Viewed Dashboard',
            'business.products.index' => 'Viewed Products',
            'business.products.create' => 'Created Product',
            'business.products.store' => 'Created Product',
            'business.products.update' => 'Updated Product',
            'business.inventory.adjust' => 'Adjusted Inventory',
            'business.inventory.index' => 'Viewed Inventory',
            'business.customers.index' => 'Viewed Customers',
            'business.suppliers.index' => 'Viewed Suppliers',
            'business.sales.store' => 'Created Sale',
            'business.pos.index' => 'Opened POS',
            'business.purchases.store' => 'Created Purchase Order',
        ];
        $actionLabels = [
            'page_visit' => 'Viewed '.$moduleLabel,
            'login' => 'Logged In', 'logout' => 'Logged Out',
            'company viewed' => 'Viewed Company Details',
            'company approved' => 'Approved Company', 'company rejected' => 'Rejected Company',
            'company suspended' => 'Suspended Company', 'company activated' => 'Activated Company',
            'company permissions loaded' => 'Viewed Permissions',
            'company permissions updated' => 'Updated Permissions',
            'permissions updated' => 'Updated Permissions',
            'permission granted' => 'Updated Permissions', 'permission revoked' => 'Updated Permissions',
            'subscription updated' => 'Updated Subscription',
            'login as business started' => 'Opened Business Dashboard Preview',
            'login as business ended' => 'Returned to Super Admin Dashboard',
        ];

        $label = $routeLabels[$route] ?? $actionLabels[$action] ?? null;

        if (!$label && $route && str_contains($route, '.')) {
            $segments = explode('.', $route);
            $verb = array_pop($segments);
            $routeModule = $moduleLabels[str_replace(['_', '-'], ' ', (string) end($segments))] ?? $moduleLabel;
            $singular = \Illuminate\Support\Str::singular($routeModule);
            $label = match ($verb) {
                'index' => 'Viewed '.$routeModule,
                'show' => 'Viewed '.$singular.' Details',
                'create', 'store' => 'Created '.$singular,
                'edit', 'update' => 'Updated '.$singular,
                'destroy', 'delete' => 'Deleted '.$singular,
                'archive' => 'Archived '.$singular,
                'restore' => 'Restored '.$singular,
                'export' => 'Exported '.$routeModule,
                'print' => 'Printed '.$singular,
                default => 'Viewed '.$routeModule,
            };
        }

        if (!$label && $action && !str_contains($action, '.')) {
            $label = \Illuminate\Support\Str::title(str_replace('_', ' ', $action));
        }

        $label ??= 'Activity Recorded';
    }
@endphp

{{ $label }}
