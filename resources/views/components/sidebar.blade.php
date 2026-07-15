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
                ['Suppliers', 'bi-building-add', route('business.suppliers.index'), 'suppliers'],
                ['Purchases', 'bi-cart-plus', route('business.purchases.index'), 'purchases'],
                ['Purchase Returns', 'bi-arrow-return-left', route('business.purchase-returns.index'), 'purchase_returns'],
                ['Customers', 'bi-person-lines-fill', route('business.customers.index'), 'customers'],
                ['Sales', 'bi-bag-check', route('business.sales.index'), 'sales'],
                ['Sales Returns', 'bi-arrow-return-right', route('business.sales.returns.index'), 'sales_returns'],
                ['POS', 'bi-upc-scan', route('business.pos.index'), 'pos'],
                ['Deliveries', 'bi-truck', route('business.deliveries'), 'deliveries'],
                ['Accounting / Ledger', 'bi-journal-text', route('business.khata'), 'accounting'],
                ['Expenses', 'bi-receipt-cutoff', route('business.expenses.index'), 'expenses'],
                ['Reports', 'bi-graph-up', route('business.reports'), 'reports'],
                ['Roles & Users', 'bi-person-badge', route('business.staff'), 'staff'],
                ['Audit Logs', 'bi-activity', route('business.audit-logs.index'), 'audit_logs'],
                ['Settings', 'bi-gear', route('business.settings'), 'settings'],
            ]);

    if ($area === 'business') {
        $companyPermissions = app(\App\Services\CompanyPermissionService::class);
        $items = array_values(array_filter($items, function ($item) use ($companyPermissions, $role) {
            $module = $item[3] ?? null;
            if ($role === 'custom_staff' && $module === 'staff') {
                return false;
            }
            if ($module === 'purchase_returns' && !$companyPermissions->allowsUser(auth()->user(), 'purchases.view')) {
                return false;
            }
            if ($module === 'sales_returns' && !$companyPermissions->allowsUser(auth()->user(), 'sales.view')) {
                return false;
            }
            if ($module && !$companyPermissions->allowsUser(auth()->user(), $module.'.view')) {
                return false;
            }

            if ($role === 'business_owner') {
                return true;
            }

            return $item[3] === null || $companyPermissions->allowsUser(auth()->user(), $item[3].'.view');
        }));
    }

    $businessDashboardItem = $area === 'business' && $companyPermissions->allowsUser(auth()->user(), 'dashboard.view')
        ? ['Dashboard', 'bi-speedometer2', in_array($role, \App\Support\BusinessStaffRoles::DASHBOARD_ROLES, true) ? route('staff.dashboard') : route('business.dashboard')]
        : null;

    $superAdminBusinessContext = $area === 'business'
        && $role === 'super_admin'
        && request()->attributes->get('super_admin_business_context');

    $purchaseReturnItem = collect($items)->first(fn ($item) => ($item[3] ?? null) === 'purchase_returns');
    $salesReturnItem = collect($items)->first(fn ($item) => ($item[3] ?? null) === 'sales_returns');

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
            @if($label === 'Purchase Returns' || $label === 'Sales Returns')
                @continue
            @endif
            @if($label === 'Purchases')
                <div class="tf-sidebar-module" data-tf-sidebar-module>
                    <a href="{{ $url }}" class="{{ request()->routeIs('business.purchases.*', 'business.purchase-returns.*') ? 'active' : '' }}" title="Purchases"><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">Purchases</span></a>
                    @if($purchaseReturnItem)<div id="purchase-sidebar-submenu" class="tf-sidebar-submenu {{ request()->routeIs('business.purchases.*', 'business.purchase-returns.*') ? 'is-open' : '' }}"><a href="{{ $purchaseReturnItem[2] }}" class="{{ request()->routeIs('business.purchase-returns.*') ? 'active' : '' }}" title="Purchase Returns"><i class="bi bi-arrow-return-left"></i><span class="tf-sidebar-text">Purchase Returns</span></a></div>@endif
                </div>
            @elseif($label === 'Sales')
                <div class="tf-sidebar-module" data-tf-sidebar-module>
                    <a href="{{ $url }}" class="{{ request()->routeIs('business.sales.*') ? 'active' : '' }}" title="Sales"><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">Sales</span></a>
                    @if($salesReturnItem)<div id="sales-sidebar-submenu" class="tf-sidebar-submenu {{ request()->routeIs('business.sales.*') ? 'is-open' : '' }}"><a href="{{ $salesReturnItem[2] }}" class="{{ request()->routeIs('business.sales.returns.*') ? 'active' : '' }}" title="Sales Returns"><i class="bi bi-arrow-return-right"></i><span class="tf-sidebar-text">Sales Returns</span></a></div>@endif
                </div>
            @else
                <a href="{{ $url }}" class="{{ url()->current() === $url ? 'active' : '' }}" title="{{ $label }}"><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">{{ $label }}</span></a>
            @endif
        @endforeach
    </nav>
</div>
@endif
