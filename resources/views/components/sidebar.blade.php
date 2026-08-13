@php
    $prefix = request()->segment(1);
    $role = auth()->user()?->role;
    $area = match (true) {
        $prefix === 'admin' || ($role === 'super_admin' && $prefix !== 'business') => 'admin',
        $prefix === 'retailer' || $role === 'retailer' => 'retailer',
        default => 'business',
    };
    $sidebarUser = auth()->user();
    // Business workspaces always use the tenant brand. Super Admin preview
    // sessions resolve their tenant from the context middleware rather than
    // the Super Admin's own (normally empty) business relationship.
    $sidebarBusiness = $area === 'business'
        ? (request()->attributes->get('super_admin_business_context') ?? $sidebarUser?->business ?? $sidebarUser?->ownedBusiness)
        : null;
    $sidebarBrandName = $sidebarBusiness?->business_name ?: $platformSettings->company_name;
    $sidebarLogoPath = ltrim((string) ($sidebarBusiness?->logo ?? ''), '/');
    $sidebarLogoPath = preg_replace('#^(?:public/|storage/)#', '', $sidebarLogoPath);
    $hasSidebarBusinessLogo = filled($sidebarLogoPath)
        && \Illuminate\Support\Facades\Storage::disk('public')->exists($sidebarLogoPath);
    $sidebarPlatformLogoPath = ltrim((string) ($platformSettings?->logo ?? ''), '/');
    $sidebarPlatformLogoPath = preg_replace('#^(?:public/|storage/)#', '', $sidebarPlatformLogoPath);
    $hasSidebarPlatformLogo = filled($sidebarPlatformLogoPath)
        && \Illuminate\Support\Facades\Storage::disk('public')->exists($sidebarPlatformLogoPath);

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
                ['Units', 'bi-rulers', route('business.units.index'), 'units'],
                ['Categories', 'bi-tags', route('business.categories.index'), 'categories'],
                ['Inventory', 'bi-clipboard-data', route('business.inventory'), 'inventory'],
                ['Suppliers', 'bi-building-add', route('business.suppliers.index'), 'suppliers'],
                ['Purchases', 'bi-cart-plus', route('business.purchases.index'), 'purchases'],
                ['Purchase Returns', 'bi-arrow-return-left', route('business.purchase-returns.index'), 'purchase_returns'],
                ['Sales', 'bi-bag-check', route('business.sales.index'), 'sales'],
                ['Sales Returns', 'bi-arrow-return-right', route('business.sales.returns.index'), 'sales_returns'],
                ['Customers', 'bi-people', route('business.customers.index'), 'customers'],
                ['POS', 'bi-calculator', route('business.pos.index'), 'pos'],
                ['Deliveries', 'bi-truck', route('business.deliveries'), 'deliveries'],
                ['Ledger', 'bi-journal-text', route('business.khata'), 'accounting'],
                ['Expenses', 'bi-receipt-cutoff', route('business.expenses.index'), 'expenses'],
                ['Reports', 'bi-graph-up', route('business.reports'), 'reports'],
                ['Roles & Users', 'bi-person-badge', route('business.staff'), 'staff'],
                ['Audit Logs', 'bi-activity', route('business.audit-logs.index'), 'audit_logs'],
            ]);

    if ($area === 'business') {
        $companyPermissions = app(\App\Services\CompanyPermissionService::class);
        $items = array_values(array_filter($items, function ($item) use ($companyPermissions) {
            $module = $item[3] ?? null;
            if ($module === 'purchase_returns' && !$companyPermissions->allowsUser(auth()->user(), 'purchases.view')) {
                return false;
            }
            if ($module === 'sales_returns' && !$companyPermissions->allowsUser(auth()->user(), 'sales.view')) {
                return false;
            }
            $visibilityPermissions = $module === 'staff'
                ? ['staff.view', 'staff.create', 'staff.edit', 'staff.permissions']
                : [$module.'.view'];

            if ($module && !collect($visibilityPermissions)
                ->contains(fn (string $permission) => $companyPermissions->allowsUser(auth()->user(), $permission))) {
                return false;
            }

            return true;
        }));
    }

    $businessDashboardItem = $area === 'business' && $companyPermissions->allowsUser(auth()->user(), 'dashboard.view')
        ? ['Dashboard', 'bi-speedometer2', route('business.dashboard')]
        : null;

    $businessItemsByModule = collect($items)->keyBy(fn ($item) => $item[3] ?? '');
    $businessGroups = [
        [
            'key' => 'products',
            'parent' => $businessItemsByModule->get('products'),
            'label' => 'Products',
            'icon' => 'bi-box',
            'routes' => ['business.products.*', 'business.units.*', 'business.categories.*'],
            'items' => array_values(array_filter([
                $businessItemsByModule->get('units'),
                $businessItemsByModule->get('categories'),
            ])),
        ],
        [
            'key' => 'sales',
            'parent' => $businessItemsByModule->get('sales'),
            'label' => 'Sales',
            'icon' => 'bi-bag-check',
            'routes' => ['business.sales.*'],
            'items' => array_values(array_filter([
                $businessItemsByModule->get('sales_returns'),
            ])),
        ],
        [
            'key' => 'purchases',
            'parent' => $businessItemsByModule->get('purchases'),
            'label' => 'Purchases',
            'icon' => 'bi-cart-plus',
            'routes' => ['business.purchases.*', 'business.purchase-returns.*'],
            'items' => array_values(array_filter([
                $businessItemsByModule->get('purchase_returns'),
            ])),
        ],
        [
            'key' => 'accounting',
            'parent' => $businessItemsByModule->get('accounting'),
            'label' => 'Ledger',
            'icon' => 'bi-journal-text',
            'routes' => ['business.khata', 'business.expenses.*'],
            'items' => array_values(array_filter([
                $businessItemsByModule->get('expenses'),
            ])),
        ],
    ];

    $businessGroupsByKey = collect($businessGroups)->keyBy('key');
    $businessItemRoutePatterns = [
        'inventory' => ['business.inventory', 'business.inventory.*'],
        'deliveries' => ['business.deliveries*'],
        'suppliers' => ['business.suppliers.*'],
        'reports' => ['business.reports*'],
        'staff' => ['business.staff', 'business.staff.*'],
        'audit_logs' => ['business.audit-logs.*'],
        'units' => ['business.units.*'],
        'categories' => ['business.categories.*'],
        'sales_returns' => ['business.sales.returns.*'],
        'pos' => ['business.pos.*'],
        'purchase_returns' => ['business.purchase-returns.*'],
        'customers' => ['business.customers.*'],
    ];
    $isBusinessItemActive = static function (array $item) use ($businessItemRoutePatterns): bool {
        $module = $item[3] ?? null;
        $patterns = $businessItemRoutePatterns[$module] ?? [];

        return $patterns !== [] && request()->routeIs(...$patterns);
    };
    $businessSidebarEntries = [
        ['type' => 'group', 'value' => $businessGroupsByKey->get('products')],
        ['type' => 'item', 'value' => $businessItemsByModule->get('inventory')],
        ['type' => 'group', 'value' => $businessGroupsByKey->get('sales')],
        ['type' => 'item', 'value' => $businessItemsByModule->get('customers')],
        ['type' => 'item', 'value' => $businessItemsByModule->get('pos')],
        ['type' => 'item', 'value' => $businessItemsByModule->get('deliveries')],
        ['type' => 'group', 'value' => $businessGroupsByKey->get('purchases')],
        ['type' => 'item', 'value' => $businessItemsByModule->get('suppliers')],
        ['type' => 'group', 'value' => $businessGroupsByKey->get('accounting')],
        ['type' => 'item', 'value' => $businessItemsByModule->get('reports')],
        ['type' => 'item', 'value' => $businessItemsByModule->get('staff')],
        ['type' => 'item', 'value' => $businessItemsByModule->get('audit_logs')],
    ];

@endphp
@if($area === 'admin')
    @include('components.super-admin-sidebar')
@else
<div class="tf-sidebar-inner p-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <a class="tf-brand tf-sidebar-brand text-white d-flex align-items-center mb-0" href="{{ route('public.home') }}">
            <span class="tf-brand-mark bg-blue tf-sidebar-company-mark">
                @if($hasSidebarBusinessLogo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($sidebarLogoPath) }}?v={{ $sidebarBusiness?->updated_at?->timestamp }}" alt="{{ $sidebarBrandName }} logo">
                @elseif($hasSidebarPlatformLogo)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($sidebarPlatformLogoPath) }}?v={{ $platformSettings->updated_at?->timestamp }}" alt="{{ $platformSettings->company_name }} logo" class="tf-brand-logo">
                @else
                    <i class="bi bi-boxes"></i>
                @endif
            </span>
            <span class="tf-sidebar-text tf-sidebar-company-name" title="{{ $sidebarBrandName }}">{{ $sidebarBrandName }}</span>
        </a>
        <button type="button" class="btn btn-sm btn-outline-light tf-sidebar-toggle tf-sidebar-toggle-inside" data-tf-sidebar-toggle aria-label="Toggle sidebar" title="Toggle sidebar"><i class="bi bi-list"></i></button>
        <button type="button" class="btn btn-sm btn-outline-light tf-sidebar-close" data-tf-sidebar-close aria-label="Close sidebar"><i class="bi bi-x-lg"></i></button>
    </div>
    <nav class="d-grid gap-1">
        @if($businessDashboardItem)
            @php
                [$label, $icon, $url] = $businessDashboardItem;
            @endphp
            <a href="{{ $url }}" class="{{ url()->current() === $url ? 'active' : '' }}" title="{{ $label }}"><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">{{ $label }}</span></a>
        @endif
        @if($area === 'retailer')
            @foreach($items as $item)
                @php
                    [$label, $icon, $url] = $item;
                @endphp
                <a href="{{ $url }}" class="{{ url()->current() === $url ? 'active' : '' }}" title="{{ $label }}"><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">{{ $label }}</span></a>
            @endforeach
        @else
            @foreach($businessSidebarEntries as $entry)
                @if($entry['type'] === 'item')
                    @php
                        $item = $entry['value'];
                    @endphp
                    @if($item)
                        @php
                            [$label, $icon, $url] = $item;
                        @endphp
                        <a href="{{ $url }}" class="{{ $isBusinessItemActive($item) ? 'active' : '' }}" title="{{ $label }}"><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">{{ $label }}</span></a>
                    @endif
                @else
                    @php
                        $group = $entry['value'];
                        $groupActive = $group && request()->routeIs(...$group['routes']);
                        $groupOpen = $groupActive || ($group && !$group['parent'] && !empty($group['items']));
                    @endphp
                    @if($group && ($group['parent'] || !empty($group['items'])))
                        <div class="tf-sidebar-module tf-sidebar-static-group {{ $groupOpen ? 'is-active' : '' }}">
                            @if($group['parent'])
                                @php
                                    [$label, $icon, $url] = $group['parent'];
                                @endphp
                                <a href="{{ $url }}" class="tf-sidebar-parent-link {{ $groupActive ? 'active' : '' }}" title="{{ $label }}"><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">{{ $label }}</span></a>
                            @else
                                <div class="tf-sidebar-parent-link is-disabled" title="{{ $group['label'] }}"><i class="bi {{ $group['icon'] }}"></i><span class="tf-sidebar-text">{{ $group['label'] }}</span></div>
                            @endif
                            @if(!empty($group['items']))
                                <div class="tf-sidebar-submenu">
                                    @foreach($group['items'] as $item)
                                        @php
                                            [$label, $icon, $url] = $item;
                                        @endphp
                                        <a href="{{ $url }}" class="{{ $isBusinessItemActive($item) ? 'active' : '' }}" title="{{ $label }}"><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">{{ $label }}</span></a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                @endif
            @endforeach
        @endif
    </nav>
</div>
@endif
