@php
    $prefix = request()->segment(1);
    $role = auth()->user()?->role;
    $area = match (true) {
        $prefix === 'admin' || $role === 'super_admin' => 'admin',
        $prefix === 'retailer' || $role === 'retailer' => 'retailer',
        default => 'business',
    };

    $items = $area === 'admin'
        ? [
            ['Dashboard', 'bi-speedometer2', route('admin.dashboard')],
            ['Businesses', 'bi-buildings', route('admin.businesses')],
            ['Business Reports', 'bi-file-earmark-bar-graph', route('admin.business-reports')],
            ['Users', 'bi-people', route('admin.users')],
            ['Subscriptions', 'bi-credit-card', route('admin.subscriptions')],
            ['Categories', 'bi-tags', route('admin.categories')],
            ['Payments', 'bi-cash-stack', route('admin.payments')],
            ['Support Tickets', 'bi-life-preserver', route('admin.support-tickets')],
            ['Notifications', 'bi-bell', route('admin.notifications')],
            ['Audit Logs', 'bi-shield-check', route('admin.audit-logs')],
            ['Settings', 'bi-gear', route('admin.settings')],
        ]
        : ($area === 'retailer'
            ? [
                ['Dashboard', 'bi-speedometer2', route('retailer.dashboard')],
                ['Browse Products', 'bi-grid', route('retailer.products')],
                ['Cart', 'bi-cart3', route('retailer.cart')],
                ['Orders', 'bi-receipt', route('retailer.orders')],
                ['Credit Balance', 'bi-wallet2', route('retailer.credit-balance')],
            ]
            : [
                ['Dashboard', 'bi-speedometer2', in_array($role, ['manager', 'sales_staff', 'inventory_staff', 'accountant', 'delivery_staff'], true) ? route('staff.dashboard') : route('business.dashboard'), null],
                ['Products', 'bi-box', route('business.products.index'), 'products'],
                ['Inventory', 'bi-clipboard-data', route('business.inventory'), 'inventory'],
                ['Customers', 'bi-person-lines-fill', route('business.customers.index'), 'customers'],
                ['Orders', 'bi-bag-check', route('business.orders.index'), 'orders'],
                ['Payments', 'bi-cash-stack', route('business.payments'), 'payments'],
                ['Khata', 'bi-journal-text', route('business.khata'), 'khata'],
                ['Deliveries', 'bi-truck', route('business.deliveries'), 'deliveries'],
                ['Invoices', 'bi-file-earmark-text', route('business.invoices.index'), 'invoices'],
                ['Expenses', 'bi-receipt-cutoff', route('business.expenses.index'), 'expenses'],
                ['Reports', 'bi-graph-up', route('business.reports'), 'reports'],
                ['Staff', 'bi-person-badge', route('business.staff'), 'staff'],
                ['Settings', 'bi-gear', route('business.settings'), 'settings'],
            ]);

    if ($area === 'business' && $role !== 'business_owner') {
        $permissions = collect(auth()->user()?->permissions ?? [])->map(fn ($value) => strtolower($value))->all();
        $items = array_values(array_filter($items, function ($item) use ($permissions) {
            if (in_array($item[3], ['staff', 'settings'], true)) {
                return false;
            }

            $permissionAllowsModule = in_array($item[3], $permissions, true)
                || collect($permissions)->contains(fn ($value) => str_starts_with($value, $item[3].'.'));

            return $item[3] === null || $permissionAllowsModule;
        }));
    }
@endphp
<div class="tf-sidebar-inner p-3">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <a class="tf-brand text-white d-flex align-items-center mb-0" href="{{ route('public.home') }}">
            <span class="tf-brand-mark bg-blue"><i class="bi bi-box-seam"></i></span>
            <span class="tf-sidebar-text">TradeFlow</span>
        </a>
        <button type="button" class="btn btn-sm btn-outline-light tf-sidebar-toggle tf-sidebar-toggle-inside d-none d-lg-inline-flex" data-tf-sidebar-toggle aria-label="Toggle sidebar" title="Toggle sidebar"><i class="bi bi-list"></i></button>
        <button type="button" class="btn btn-sm btn-outline-light tf-sidebar-close d-lg-none" data-tf-sidebar-close aria-label="Close sidebar"><i class="bi bi-x-lg"></i></button>
    </div>
    <nav class="d-grid gap-1">
        @foreach($items as $item)
            @php([$label, $icon, $url] = $item)
            <a href="{{ $url }}" class="{{ url()->current() === $url ? 'active' : '' }}" title="{{ $label }}"><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">{{ $label }}</span></a>
        @endforeach
    </nav>
</div>
