@extends('layouts.dashboard')

@section('title', 'Business Dashboard | TradeFlow')
@section('page-title', 'Business Dashboard')
@section('page-subtitle', 'Your business command center')

@section('content')
@php
    $companyPermissions = app(\App\Services\CompanyPermissionService::class);
    $dashboardUser = auth()->user();
    $permissions = $dashboardPermissions ?? [];
    $can = fn (string $key): bool => (bool) ($permissions[$key] ?? false);

    $primaryKpis = collect([
        $can('sales') ? ['label' => "Today's Sales", 'value' => 'Rs '.number_format($todaySales ?? 0), 'note' => 'Completed sales today', 'icon' => 'bi-calendar-day', 'tone' => 'blue', 'route' => 'business.sales.index'] : null,
        $can('receivables') ? ['label' => 'Receivables', 'value' => 'Rs '.number_format($receivables ?? 0), 'note' => 'Customer balances due', 'icon' => 'bi-wallet2', 'tone' => 'amber', 'route' => 'business.customers.index'] : null,
        $can('payables') ? ['label' => 'Payables', 'value' => 'Rs '.number_format($payables ?? 0), 'note' => 'Supplier balances due', 'icon' => 'bi-credit-card', 'tone' => 'orange', 'route' => 'business.purchases.index'] : null,
        $can('monthly_profit') ? ['label' => 'Monthly Profit / Loss', 'value' => 'Rs '.number_format($monthlyProfit ?? 0), 'note' => 'This month after costs', 'icon' => 'bi-graph-up-arrow', 'tone' => ($monthlyProfit ?? 0) < 0 ? 'red' : 'green', 'route' => 'business.khata'] : null,
    ])->filter()->values();

    $secondaryKpis = collect([
        $can('inventory') ? ['label' => 'Inventory Value', 'value' => 'Rs '.number_format($inventoryValue ?? 0), 'icon' => 'bi-boxes', 'tone' => 'violet', 'route' => 'business.inventory'] : null,
        $can('customers') ? ['label' => 'Total Customers', 'value' => number_format($customersCount ?? 0), 'icon' => 'bi-people', 'tone' => 'blue', 'route' => 'business.customers.index'] : null,
        $can('suppliers') ? ['label' => 'Total Suppliers', 'value' => number_format($suppliersCount ?? 0), 'icon' => 'bi-building-add', 'tone' => 'slate', 'route' => 'business.suppliers.index'] : null,
        $can('purchases') ? ['label' => "Today's Purchases", 'value' => 'Rs '.number_format($todayPurchases ?? 0), 'icon' => 'bi-cart-plus', 'tone' => 'orange', 'route' => 'business.purchases.index'] : null,
    ])->filter()->values();

    $healthItems = collect([
        $can('low_stock') ? ['label' => 'Low Stock', 'note' => number_format($lowStock ?? 0).' products need attention', 'value' => number_format($lowStock ?? 0), 'icon' => 'bi-exclamation-triangle', 'tone' => 'danger', 'route' => 'business.inventory'] : null,
        $can('deliveries') ? ['label' => 'Pending Deliveries', 'note' => 'Orders awaiting completion', 'value' => number_format($pendingDeliveries ?? 0), 'icon' => 'bi-truck', 'tone' => 'blue', 'route' => 'business.deliveries'] : null,
        $can('receivables') ? ['label' => 'Receivables', 'note' => 'Customer balances due', 'value' => 'Rs '.number_format($receivables ?? 0), 'icon' => 'bi-wallet2', 'tone' => 'amber', 'route' => 'business.customers.index'] : null,
        $can('payables') ? ['label' => 'Payables', 'note' => 'Supplier balances due', 'value' => 'Rs '.number_format($payables ?? 0), 'icon' => 'bi-credit-card', 'tone' => 'orange', 'route' => 'business.purchases.index'] : null,
    ])->filter()->values();

    $attentionItems = collect([
        $can('low_stock') && ($lowStock ?? 0) > 0 ? ['icon' => 'bi-exclamation-triangle', 'tone' => 'danger', 'title' => ($lowStock ?? 0).' low-stock products', 'description' => 'Review inventory and restock where needed.', 'route' => 'business.inventory'] : null,
        $can('deliveries') && ($pendingDeliveries ?? 0) > 0 ? ['icon' => 'bi-truck', 'tone' => 'warning', 'title' => ($pendingDeliveries ?? 0).' pending deliveries', 'description' => 'Orders still awaiting completion.', 'route' => 'business.deliveries'] : null,
        $can('receivables') && ($receivables ?? 0) > 0 ? ['icon' => 'bi-wallet2', 'tone' => 'warning', 'title' => 'Rs '.number_format($receivables).' receivable', 'description' => 'Customer balances need follow-up.', 'route' => 'business.customers.index'] : null,
        $can('payables') && ($payables ?? 0) > 0 ? ['icon' => 'bi-credit-card', 'tone' => 'danger', 'title' => 'Rs '.number_format($payables).' payable', 'description' => 'Supplier balances are outstanding.', 'route' => 'business.purchases.index'] : null,
    ])->filter()->values();

    $salesTrend = collect($salesTrend ?? []);
    $recentOrders = collect($recentOrders ?? []);
    $recentActivity = collect($recentActivity ?? []);
    $trendMaximum = max(1, (float) $salesTrend->max('total'));
    $hasAttentionSource = $healthItems->isNotEmpty();
    $hasRecentActivity = ($canViewSalesActivity ?? false) || ($canViewActivity ?? false);
    $lowerPanelCount = ($hasAttentionSource ? 1 : 0) + ($hasRecentActivity ? 1 : 0);
    $canAddProduct = $companyPermissions->allowsUser($dashboardUser, 'dashboard.quick_add_product') && $companyPermissions->allowsUser($dashboardUser, 'products.create');
    $canAddCustomer = $companyPermissions->allowsUser($dashboardUser, 'customers.create');
    $canNewPurchase = $companyPermissions->allowsUser($dashboardUser, 'dashboard.quick_new_purchase') && $companyPermissions->allowsUser($dashboardUser, 'purchases.create');
    $canOpenPos = $companyPermissions->allowsUser($dashboardUser, 'pos.view');
    $hasQuickActions = $canAddProduct || $canAddCustomer || $canNewPurchase || $canOpenPos;
@endphp

@if($accessExpiryAlert ?? null)
    <x-business-access-expiry-alert :alert="$accessExpiryAlert" />
@endif

@if(! $hasOperationalAccess)
    <div class="tf-card p-5 text-center">
        <i class="bi bi-shield-lock fs-2 text-warning"></i>
        <h2 class="h5 mt-3">Operational Access Not Configured</h2>
        <p class="tf-muted mb-0">Your business has been approved, but no operational modules have been assigned to your company yet.</p>
    </div>
@else
    <div class="tf-business-command-center">
        <header class="tf-business-command-header">
            <div>
                <span class="tf-dashboard-eyebrow">Daily overview</span>
                <h2>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ $dashboardUser->name }}.</h2>
                <p>Here&rsquo;s what&rsquo;s happening across your permitted workspace today.</p>
            </div>

            @if($hasQuickActions)
                <div class="quick-actions tf-business-command-actions" aria-label="Quick actions">
                    @if($canAddProduct)
                        <a href="{{ route('business.products.create') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg"></i><span>Add Product</span></a>
                    @endif
                    @if($canAddCustomer)
                        <a href="{{ route('business.customers.index') }}" class="btn btn-outline-primary"><i class="bi bi-person-plus"></i><span>Add Customer</span></a>
                    @endif
                    @if($canNewPurchase)
                        <a href="{{ route('business.purchases.index', ['create' => 1]) }}#purchase-create" class="btn btn-outline-primary"><i class="bi bi-cart-plus"></i><span>New Purchase</span></a>
                    @endif
                    @if($canOpenPos)
                        <a href="{{ route('business.pos.index') }}" class="btn btn-outline-primary"><i class="bi bi-cash-register"></i><span>Open POS</span></a>
                    @endif
                </div>
            @endif
        </header>

        @if($primaryKpis->isNotEmpty())
            <section class="tf-business-kpi-grid tf-business-kpi-grid--{{ $primaryKpis->count() }}" aria-label="Financial overview">
                @foreach($primaryKpis as $kpi)
                    <a href="{{ route($kpi['route']) }}" class="tf-business-kpi tf-business-kpi--{{ $kpi['tone'] }}">
                        <span class="tf-business-kpi__icon"><i class="bi {{ $kpi['icon'] }}"></i></span>
                        <span class="tf-business-kpi__label">{{ $kpi['label'] }}</span>
                        <strong>{{ $kpi['value'] }}</strong>
                        <small>{{ $kpi['note'] }}</small>
                        <i class="bi bi-arrow-up-right tf-business-kpi__arrow"></i>
                    </a>
                @endforeach
            </section>
        @endif

        @if($can('sales') || $healthItems->isNotEmpty())
            <section class="tf-business-insights tf-business-insights--{{ ($can('sales') ? 1 : 0) + ($healthItems->isNotEmpty() ? 1 : 0) }}">
                @if($can('sales'))
                    <article class="tf-business-panel tf-business-sales-panel">
                        <header class="tf-business-panel__heading">
                            <div><span class="tf-dashboard-eyebrow">Sales trend</span><h2>Sales &amp; Profit Overview</h2><p>Completed sales from the last seven days.</p></div>
                            <a href="{{ route('business.sales.index') }}">View sales <i class="bi bi-arrow-up-right"></i></a>
                        </header>
                        @if($salesTrend->contains(fn ($point) => $point['total'] > 0))
                            <div class="tf-business-chart" role="img" aria-label="Completed sales for the last seven days">
                                @foreach($salesTrend as $point)
                                    @php($height = max(7, round(($point['total'] / $trendMaximum) * 100)))
                                    <div class="tf-business-chart__column"><span class="tf-business-chart__value">Rs {{ number_format($point['total']) }}</span><span class="tf-business-chart__bar" style="--tf-bar-height: {{ $height }}%"></span><small>{{ $point['label'] }}</small></div>
                                @endforeach
                            </div>
                        @else
                            <div class="tf-business-empty tf-business-empty--compact"><i class="bi bi-bar-chart"></i><span>No completed sales recorded in the last seven days.</span></div>
                        @endif
                    </article>
                @endif

                @if($healthItems->isNotEmpty())
                    <aside class="tf-business-panel tf-business-health-panel">
                        <header class="tf-business-panel__heading"><div><span class="tf-dashboard-eyebrow">At a glance</span><h2>Business Health</h2><p>Operational balances and open work.</p></div></header>
                        <div class="tf-business-health-list">
                            @foreach($healthItems as $item)
                                <a href="{{ route($item['route']) }}" class="tf-business-health-row"><span class="tf-business-health-row__icon is-{{ $item['tone'] }}"><i class="bi {{ $item['icon'] }}"></i></span><span><strong>{{ $item['label'] }}</strong><small>{{ $item['note'] }}</small></span><b>{{ $item['value'] }}</b></a>
                            @endforeach
                        </div>
                    </aside>
                @endif
            </section>
        @endif

        @if($secondaryKpis->isNotEmpty())
            <section class="tf-business-section">
                <header class="tf-business-section__heading"><div><span class="tf-dashboard-eyebrow">Operations</span><h2>Operational Overview</h2></div></header>
                <div class="tf-business-secondary-grid tf-business-secondary-grid--{{ $secondaryKpis->count() }}">
                    @foreach($secondaryKpis as $kpi)
                        <a href="{{ route($kpi['route']) }}" class="tf-business-secondary-card tf-business-secondary-card--{{ $kpi['tone'] }}"><span><small>{{ $kpi['label'] }}</small><strong>{{ $kpi['value'] }}</strong></span><i class="bi {{ $kpi['icon'] }}"></i></a>
                    @endforeach
                </div>
            </section>
        @endif

        @if($lowerPanelCount)
            <section class="tf-business-lower-grid tf-business-lower-grid--{{ $lowerPanelCount }}">
                @if($hasAttentionSource)
                    <article class="tf-business-panel">
                        <header class="tf-business-panel__heading"><div><span class="tf-dashboard-eyebrow">Priorities</span><h2>Needs Attention</h2></div></header>
                        @if($attentionItems->isNotEmpty())
                            <div class="tf-business-attention-list">
                                @foreach($attentionItems as $item)
                                    <a href="{{ route($item['route']) }}" class="tf-business-attention-row"><span class="tf-business-attention-row__icon is-{{ $item['tone'] }}"><i class="bi {{ $item['icon'] }}"></i></span><span><strong>{{ $item['title'] }}</strong><small>{{ $item['description'] }}</small></span><i class="bi bi-arrow-right"></i></a>
                                @endforeach
                            </div>
                        @else
                            <div class="tf-business-empty tf-business-empty--compact"><i class="bi bi-check2-circle"></i><span>Everything looks good. No urgent items right now.</span></div>
                        @endif
                    </article>
                @endif

                @if($hasRecentActivity)
                    <article class="tf-business-panel">
                        <header class="tf-business-panel__heading">
                            <div><span class="tf-dashboard-eyebrow">Latest updates</span><h2>Recent Activity</h2></div>
                            @if($canViewSalesActivity ?? false)
                                <a href="{{ route('business.sales.index') }}">View sales <i class="bi bi-arrow-up-right"></i></a>
                            @elseif($canViewActivity ?? false)
                                <a href="{{ route('business.audit-logs.index') }}">View all <i class="bi bi-arrow-up-right"></i></a>
                            @endif
                        </header>

                        @if($canViewSalesActivity ?? false)
                            @forelse($recentOrders as $order)
                                <a href="{{ route('business.sales.show', $order) }}" class="tf-business-recent-row"><span class="tf-business-recent-row__icon"><i class="bi bi-receipt"></i></span><span><strong>{{ $order->invoice_no ?: 'Sale #'.$order->id }}</strong><small>{{ $order->customer?->name ?: 'Walk-in customer' }} &middot; <x-date-time :value="$order->created_at" /></small></span><b>Rs {{ number_format($order->grand_total ?? 0) }}</b></a>
                            @empty
                                <div class="tf-business-empty tf-business-empty--compact"><i class="bi bi-clock-history"></i><span>No recent sales activity yet.</span></div>
                            @endforelse
                        @else
                            @forelse($recentActivity as $activity)
                                <a href="{{ route('business.audit-logs.index') }}" class="tf-business-recent-row"><span class="tf-business-recent-row__icon"><i class="bi bi-activity"></i></span><span><strong>{{ $activity->action ?: $activity->module }}</strong><small>{{ $activity->module }} &middot; <x-date-time :value="$activity->occurred_at ?? $activity->created_at" /></small></span></a>
                            @empty
                                <div class="tf-business-empty tf-business-empty--compact"><i class="bi bi-clock-history"></i><span>No permitted activity yet.</span></div>
                            @endforelse
                        @endif
                    </article>
                @endif
            </section>
        @endif

        @if($primaryKpis->isEmpty() && $secondaryKpis->isEmpty() && ! $can('sales') && $healthItems->isEmpty() && ! $hasRecentActivity)
            <section class="tf-business-panel tf-business-welcome-panel"><i class="bi bi-grid-1x2"></i><div><span class="tf-dashboard-eyebrow">Your workspace</span><h2>Your permitted tools are ready</h2><p>Use the sidebar to begin work in the modules assigned to you.</p></div></section>
        @endif
    </div>
@endif
@endsection
