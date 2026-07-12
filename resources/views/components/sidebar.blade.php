@php
    $prefix = request()->segment(1);
    $role = auth()->user()?->role;
    $area = match (true) {
        $prefix === 'admin' || ($role === 'super_admin' && $prefix !== 'business') => 'admin',
        $prefix === 'retailer' || $role === 'retailer' => 'retailer',
        default => 'business',
    };

    $items = $area === 'admin'
        ? []
        : ($area === 'retailer'
            ? [
                ['Dashboard', 'bi-speedometer2', route('retailer.dashboard')],
                ['Browse Products', 'bi-grid', route('retailer.products')],
                ['Cart', 'bi-cart3', route('retailer.cart')],
                ['Orders', 'bi-receipt', route('retailer.orders')],
                ['Credit Balance', 'bi-wallet2', route('retailer.credit-balance')],
            ]
            : [
                ['Products', 'bi-box', route('business.products.index'), 'products'],
                ['Inventory', 'bi-clipboard-data', route('business.inventory'), 'inventory'],
                ['Customers', 'bi-person-lines-fill', route('business.customers.index'), 'customers'],
                ['Suppliers', 'bi-building-add', route('business.suppliers.index'), 'suppliers'],
                ['Orders', 'bi-bag-check', route('business.orders.index'), 'orders'],
                ['POS', 'bi-upc-scan', route('business.pos.index'), 'pos'],
                ['Payments', 'bi-cash-stack', route('business.payments'), 'payments'],
                ['Accounting / Ledger', 'bi-journal-text', route('business.khata'), 'khata'],
                ['Deliveries', 'bi-truck', route('business.deliveries'), 'deliveries'],
                ['Invoices', 'bi-file-earmark-text', route('business.invoices.index'), 'invoices'],
                ['Expenses', 'bi-receipt-cutoff', route('business.expenses.index'), 'expenses'],
                ['Reports', 'bi-graph-up', route('business.reports'), 'reports'],
                ['Staff', 'bi-person-badge', route('business.staff'), 'staff'],
            ]);

    if ($area === 'business') {
        $companyPermissions = app(\App\Services\CompanyPermissionService::class);
        $items = array_values(array_filter($items, function ($item) use ($companyPermissions, $role) {
            $module = $item[3] ?? null;
            if ($module && !$companyPermissions->allowsUser(auth()->user(), $module.'.view')) {
                return false;
            }

            if ($role === 'business_owner') {
                return true;
            }

            if ($item[3] === 'settings') {
                return false;
            }

            return $item[3] === null || $companyPermissions->allowsUser(auth()->user(), $item[3].'.view');
        }));
    }

    $businessDashboardItem = $area === 'business'
        ? ['Dashboard', 'bi-speedometer2', in_array($role, \App\Support\BusinessStaffRoles::DASHBOARD_ROLES, true) ? route('staff.dashboard') : route('business.dashboard')]
        : null;

    $basicBusinessItems = $area === 'business' ? [
        ['Profile', 'bi-person-circle', route('profile.edit')],
        ['Notifications', 'bi-bell', route('notifications.index')],
        ['Support', 'bi-life-preserver', route('business.support')],
    ] : [];

    if ($area === 'business' && in_array($role, ['super_admin', 'business_owner'], true) && $companyPermissions->allowsUser(auth()->user(), 'settings.view')) {
        $basicBusinessItems[] = ['Settings', 'bi-gear', route('business.settings')];
    }
@endphp
@if($area === 'admin')
    @include('components.super-admin-sidebar')
@else
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
        @if($businessDashboardItem)
            @php([$label, $icon, $url] = $businessDashboardItem)
            <a href="{{ $url }}" class="{{ url()->current() === $url ? 'active' : '' }}" title="{{ $label }}"><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">{{ $label }}</span></a>
        @endif
        @foreach($items as $item)
            @php([$label, $icon, $url] = $item)
            <a href="{{ $url }}" class="{{ url()->current() === $url ? 'active' : '' }}" title="{{ $label }}"><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">{{ $label }}</span></a>
        @endforeach
        @foreach($basicBusinessItems as $item)
            @php([$label, $icon, $url] = $item)
            <a href="{{ $url }}" class="{{ url()->current() === $url ? 'active' : '' }}" title="{{ $label }}"><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">{{ $label }}</span></a>
        @endforeach
    </nav>
</div>
@endif
