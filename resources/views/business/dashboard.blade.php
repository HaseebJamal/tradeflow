@extends('layouts.dashboard')

@section('title', 'Business Dashboard | TradeFlow')
@section('page-title', 'Business Dashboard')
@section('page-subtitle', 'Your business command center')

@section('content')
@php
    $companyPermissions = app(\App\Services\CompanyPermissionService::class);
    $dashboardUser = auth()->user();
    $can = static fn (string $dashboardPermission, string $permission): bool => $companyPermissions->allowsUser($dashboardUser, $dashboardPermission) && $companyPermissions->allowsUser($dashboardUser, $permission);
    $canSales = $can('dashboard.card_today_sales', 'sales.view');
    $canReceivables = $can('dashboard.card_receivables', 'sales.view') && $companyPermissions->allowsUser($dashboardUser, 'customers.view');
    $canPayables = $can('dashboard.card_payables', 'purchases.view');
    $canMonthlyProfit = $can('dashboard.card_monthly_profit', 'accounting.view');
    $canInventory = $can('dashboard.card_inventory_value', 'inventory.view');
    $canCustomers = $can('dashboard.card_total_customers', 'customers.view');
    $canSuppliers = $can('dashboard.card_total_suppliers', 'suppliers.view');
    $canPurchases = $can('dashboard.card_today_purchases', 'purchases.view');
    $canLowStock = $can('dashboard.card_low_stock', 'inventory.view');
    $canDeliveries = $can('dashboard.card_pending_deliveries', 'deliveries.view');
    $primaryKpis = [
        ['show' => $canSales, 'label' => "Today's Sales", 'value' => 'Rs '.number_format($todaySales ?? 0), 'note' => 'Completed sales today', 'icon' => 'bi-calendar-day', 'tone' => 'blue', 'route' => 'business.sales.index'],
        ['show' => $canReceivables, 'label' => 'Receivables', 'value' => 'Rs '.number_format($receivables ?? 0), 'note' => 'Customer balances due', 'icon' => 'bi-wallet2', 'tone' => 'amber', 'route' => 'business.customers.index'],
        ['show' => $canPayables, 'label' => 'Payables', 'value' => 'Rs '.number_format($payables ?? 0), 'note' => 'Supplier balances due', 'icon' => 'bi-credit-card', 'tone' => 'orange', 'route' => 'business.purchases.index'],
        ['show' => $canMonthlyProfit, 'label' => 'Monthly Profit / Loss', 'value' => 'Rs '.number_format($monthlyProfit ?? 0), 'note' => 'This month after costs', 'icon' => 'bi-graph-up-arrow', 'tone' => ($monthlyProfit ?? 0) < 0 ? 'red' : 'green', 'route' => 'business.khata'],
    ];
    $secondaryKpis = [
        ['show' => $canInventory, 'label' => 'Inventory Value', 'value' => 'Rs '.number_format($inventoryValue ?? 0), 'icon' => 'bi-boxes', 'tone' => 'violet', 'route' => 'business.inventory'],
        ['show' => $canCustomers, 'label' => 'Total Customers', 'value' => number_format($customersCount ?? 0), 'icon' => 'bi-people', 'tone' => 'blue', 'route' => 'business.customers.index'],
        ['show' => $canSuppliers, 'label' => 'Total Suppliers', 'value' => number_format($suppliersCount ?? 0), 'icon' => 'bi-building-add', 'tone' => 'slate', 'route' => 'business.suppliers.index'],
        ['show' => $canPurchases, 'label' => "Today's Purchases", 'value' => 'Rs '.number_format($todayPurchases ?? 0), 'icon' => 'bi-cart-plus', 'tone' => 'orange', 'route' => 'business.purchases.index'],
    ];
    $salesTrend = collect($salesTrend ?? []);
    $trendMaximum = max(1, (float) $salesTrend->max('total'));
    $attentionItems = collect([
        $canLowStock && ($lowStock ?? 0) > 0 ? ['icon' => 'bi-exclamation-triangle', 'tone' => 'danger', 'title' => ($lowStock ?? 0).' low-stock '.(($lowStock ?? 0) === 1 ? 'product' : 'products'), 'description' => 'Review inventory and restock where needed.', 'route' => 'business.inventory'] : null,
        $canDeliveries && ($pendingDeliveries ?? 0) > 0 ? ['icon' => 'bi-truck', 'tone' => 'warning', 'title' => ($pendingDeliveries ?? 0).' pending '.(($pendingDeliveries ?? 0) === 1 ? 'delivery' : 'deliveries'), 'description' => 'Orders still awaiting completion.', 'route' => 'business.deliveries'] : null,
        $canReceivables && ($receivables ?? 0) > 0 ? ['icon' => 'bi-wallet2', 'tone' => 'warning', 'title' => 'Rs '.number_format($receivables).' receivable', 'description' => 'Customer balances need follow-up.', 'route' => 'business.customers.index'] : null,
        $canPayables && ($payables ?? 0) > 0 ? ['icon' => 'bi-credit-card', 'tone' => 'danger', 'title' => 'Rs '.number_format($payables).' payable', 'description' => 'Supplier balances are outstanding.', 'route' => 'business.purchases.index'] : null,
    ])->filter()->values();
    $primaryKpiCount = collect($primaryKpis)->where('show', true)->count();
    $secondaryKpiCount = collect($secondaryKpis)->where('show', true)->count();
@endphp

@if($accessExpiryAlert ?? null)
    <x-business-access-expiry-alert :alert="$accessExpiryAlert" />
@endif

@if(!$hasOperationalAccess)
    <div class="tf-card p-5 text-center"><i class="bi bi-shield-lock fs-2 text-warning"></i><h2 class="h5 mt-3">Operational Access Not Configured</h2><p class="tf-muted mb-0">Your business has been approved, but no operational modules have been assigned to your company yet.</p></div>
@else
    <div class="tf-business-command-center">
        <header class="tf-business-command-header">
            <div><span class="tf-dashboard-eyebrow">Daily overview</span><h2>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ $dashboardUser->name }}.</h2><p>Here’s what’s happening across your business today.</p></div>
            <div class="quick-actions tf-business-command-actions" aria-label="Quick actions">
                @if($companyPermissions->allowsUser($dashboardUser, 'dashboard.quick_add_product') && $companyPermissions->allowsUser($dashboardUser, 'products.create'))<a href="{{ route('business.products.create') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg"></i><span>Add Product</span></a>@endif
                @if($companyPermissions->allowsUser($dashboardUser, 'customers.create'))<a href="{{ route('business.customers.index') }}" class="btn btn-outline-primary"><i class="bi bi-person-plus"></i><span>Add Customer</span></a>@endif
                @if($companyPermissions->allowsUser($dashboardUser, 'dashboard.quick_new_purchase') && $companyPermissions->allowsUser($dashboardUser, 'purchases.create'))<a href="{{ route('business.purchases.index', ['create' => 1]) }}#purchase-create" class="btn btn-outline-primary"><i class="bi bi-cart-plus"></i><span>New Purchase</span></a>@endif
                @if($companyPermissions->allowsUser($dashboardUser, 'pos.view'))<a href="{{ route('business.pos.index') }}" class="btn btn-outline-primary"><i class="bi bi-cash-register"></i><span>Open POS</span></a>@endif
            </div>
        </header>

        <section class="tf-business-kpi-grid tf-business-kpi-grid--{{ min(4, $primaryKpiCount) }}" aria-label="Financial overview">
            @foreach($primaryKpis as $kpi)
                @if($kpi['show'])
                    <a href="{{ route($kpi['route']) }}" class="tf-business-kpi tf-business-kpi--{{ $kpi['tone'] }}"><span class="tf-business-kpi__icon"><i class="bi {{ $kpi['icon'] }}"></i></span><span class="tf-business-kpi__label">{{ $kpi['label'] }}</span><strong>{{ $kpi['value'] }}</strong><small>{{ $kpi['note'] }}</small><i class="bi bi-arrow-up-right tf-business-kpi__arrow"></i></a>
                @endif
            @endforeach
        </section>

        <section class="tf-business-insights" data-has-chart="{{ $canSales ? 'true' : 'false' }}">
            @if($canSales)
                <article class="tf-business-panel tf-business-sales-panel">
                    <header class="tf-business-panel__heading"><div><span class="tf-dashboard-eyebrow">Sales trend</span><h2>Sales &amp; Profit Overview</h2><p>Completed sales from the last seven days.</p></div><a href="{{ route('business.sales.index') }}">View sales <i class="bi bi-arrow-up-right"></i></a></header>
                    @if($salesTrend->contains(fn ($point) => $point['total'] > 0))
                        <div class="tf-business-chart" role="img" aria-label="Completed sales for the last seven days">
                            @foreach($salesTrend as $point)
                                @php($height = max(7, round(($point['total'] / $trendMaximum) * 100)))
                                <div class="tf-business-chart__column"><span class="tf-business-chart__value">Rs {{ number_format($point['total']) }}</span><span class="tf-business-chart__bar" style="--tf-bar-height: {{ $height }}%"></span><small>{{ $point['label'] }}</small></div>
                            @endforeach
                        </div>
                    @else
                        <div class="tf-business-empty"><i class="bi bi-bar-chart"></i><span>No completed sales recorded in the last seven days.</span></div>
                    @endif
                </article>
            @endif
            <aside class="tf-business-panel tf-business-health-panel"><header class="tf-business-panel__heading"><div><span class="tf-dashboard-eyebrow">At a glance</span><h2>Business Health</h2><p>Operational balances and open work.</p></div></header><div class="tf-business-health-list">
                @if($canLowStock)<a href="{{ route('business.inventory') }}" class="tf-business-health-row"><span class="tf-business-health-row__icon is-danger"><i class="bi bi-exclamation-triangle"></i></span><span><strong>Low Stock</strong><small>{{ number_format($lowStock ?? 0) }} products need attention</small></span><b>{{ number_format($lowStock ?? 0) }}</b></a>@endif
                @if($canDeliveries)<a href="{{ route('business.deliveries') }}" class="tf-business-health-row"><span class="tf-business-health-row__icon is-blue"><i class="bi bi-truck"></i></span><span><strong>Pending Deliveries</strong><small>Orders awaiting completion</small></span><b>{{ number_format($pendingDeliveries ?? 0) }}</b></a>@endif
                @if($canReceivables)<a href="{{ route('business.customers.index') }}" class="tf-business-health-row"><span class="tf-business-health-row__icon is-amber"><i class="bi bi-wallet2"></i></span><span><strong>Receivables</strong><small>Customer balances due</small></span><b>Rs {{ number_format($receivables ?? 0) }}</b></a>@endif
                @if($canPayables)<a href="{{ route('business.purchases.index') }}" class="tf-business-health-row"><span class="tf-business-health-row__icon is-orange"><i class="bi bi-credit-card"></i></span><span><strong>Payables</strong><small>Supplier balances due</small></span><b>Rs {{ number_format($payables ?? 0) }}</b></a>@endif
                @if(! $canLowStock && ! $canDeliveries && ! $canReceivables && ! $canPayables)<div class="tf-business-empty"><i class="bi bi-shield-check"></i><span>No business-health modules are enabled for your account.</span></div>@endif
            </div></aside>
        </section>

        <section class="tf-business-section">
            <header class="tf-business-section__heading">
                <div><span class="tf-dashboard-eyebrow">Operations</span><h2>Operational Overview</h2></div>
            </header>
            <div class="tf-business-secondary-grid tf-business-secondary-grid--{{ min(4, $secondaryKpiCount) }}">
                @foreach($secondaryKpis as $kpi)
                    @if($kpi['show'])
                        <a href="{{ route($kpi['route']) }}" class="tf-business-secondary-card tf-business-secondary-card--{{ $kpi['tone'] }}">
                            <span><small>{{ $kpi['label'] }}</small><strong>{{ $kpi['value'] }}</strong></span>
                            <i class="bi {{ $kpi['icon'] }}"></i>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>

        <section class="tf-business-lower-grid">
            <article class="tf-business-panel"><header class="tf-business-panel__heading"><div><span class="tf-dashboard-eyebrow">Priorities</span><h2>Needs Attention</h2></div></header>
                @if($attentionItems->isNotEmpty())<div class="tf-business-attention-list">@foreach($attentionItems as $item)<a href="{{ route($item['route']) }}" class="tf-business-attention-row"><span class="tf-business-attention-row__icon is-{{ $item['tone'] }}"><i class="bi {{ $item['icon'] }}"></i></span><span><strong>{{ $item['title'] }}</strong><small>{{ $item['description'] }}</small></span><i class="bi bi-arrow-right"></i></a>@endforeach</div>@else<div class="tf-business-empty"><i class="bi bi-check2-circle"></i><span>Everything looks good. No urgent items right now.</span></div>@endif
            </article>
            @if($canSales)<article class="tf-business-panel"><header class="tf-business-panel__heading"><div><span class="tf-dashboard-eyebrow">Latest updates</span><h2>Recent Activity</h2></div><a href="{{ route('business.sales.index') }}">View all <i class="bi bi-arrow-up-right"></i></a></header>
                @if(($recentOrders ?? collect())->isNotEmpty())<div class="tf-business-recent-list">@foreach($recentOrders as $order)<a href="{{ route('business.sales.show', $order) }}" class="tf-business-recent-row"><span class="tf-business-recent-row__icon"><i class="bi bi-receipt"></i></span><span><strong>{{ $order->invoice_no ?: 'Sale #'.$order->id }}</strong><small>{{ $order->customer?->name ?: 'Walk-in customer' }} · <x-date-time :value="$order->created_at" /></small></span><b>Rs {{ number_format($order->grand_total ?? 0) }}</b></a>@endforeach</div>@else<div class="tf-business-empty"><i class="bi bi-clock-history"></i><span>No recent sales activity yet.</span></div>@endif
            </article>@endif
        </section>
    </div>
@endif
@endsection
