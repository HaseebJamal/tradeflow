@extends('layouts.dashboard')

@section('title', 'Business Dashboard | TradeFlow')
@section('page-title', 'Business Dashboard')
@section('page-subtitle', 'Wholesale operations overview')

@section('content')
@php
    $companyPermissions = app(\App\Services\CompanyPermissionService::class);
    $dashboardCards = [
        ['sales.view', 'Total Sales', 'Rs '.number_format($totalSales ?? 0), 'bi-graph-up', 'bg-blue', 'All completed sales'],
        ['sales.view', "Today's Sales", 'Rs '.number_format($todaySales ?? 0), 'bi-calendar-day', 'bg-green', 'Sales created today'],
        ['inventory.view', 'Inventory Value', 'Rs '.number_format($inventoryValue ?? 0), 'bi-boxes', 'bg-navy', 'Current stock cost'],
        ['sales.view', 'Receivables', 'Rs '.number_format($receivables ?? 0), 'bi-wallet2', 'bg-amber', 'Customer balances due'],
        ['purchases.view', 'Payables', 'Rs '.number_format($payables ?? 0), 'bi-credit-card', 'bg-amber', 'Supplier balances due'],
        ['accounting.view', 'Profit / Loss', 'Rs '.number_format($profit ?? 0), 'bi-graph-up-arrow', 'bg-green', 'Sales less cost and expenses'],
        ['inventory.view', 'Low Stock', $lowStock ?? 0, 'bi-exclamation-triangle', 'bg-red', 'Restock required'],
        ['customers.view', 'Total Customers', $customersCount ?? 0, 'bi-people', 'bg-navy', 'Customer master records'],
        ['suppliers.view', 'Total Suppliers', $suppliersCount ?? 0, 'bi-building-add', 'bg-blue', 'Supplier master records'],
        ['deliveries.view', 'Pending Deliveries', $pendingDeliveries ?? 0, 'bi-truck', 'bg-blue', 'Awaiting delivery completion'],
        ['sales.view', 'Pending Customer Payments', 'Rs '.number_format($pendingCustomerPayments ?? 0), 'bi-cash-stack', 'bg-amber', 'Outstanding sales balances'],
        ['purchases.view', 'Pending Supplier Payments', 'Rs '.number_format($pendingSupplierPayments ?? 0), 'bi-cash-coin', 'bg-amber', 'Outstanding purchase balances'],
        ['purchases.view', "Today's Purchases", 'Rs '.number_format($todayPurchases ?? 0), 'bi-cart-plus', 'bg-navy', 'Purchase orders created today'],
        ['accounting.view', 'Monthly Profit', 'Rs '.number_format($monthlyProfit ?? 0), 'bi-calendar-month', 'bg-green', 'Current month after costs'],
    ];
    $dashboardCardPermissions = [
        'dashboard.card_total_sales', 'dashboard.card_today_sales', 'dashboard.card_inventory_value',
        'dashboard.card_receivables', 'dashboard.card_payables', 'dashboard.card_profit_loss',
        'dashboard.card_low_stock', 'dashboard.card_total_customers', 'dashboard.card_total_suppliers',
        'dashboard.card_pending_deliveries', 'dashboard.card_pending_customer_payments',
        'dashboard.card_pending_supplier_payments', 'dashboard.card_today_purchases', 'dashboard.card_monthly_profit',
    ];
@endphp

@if(!$hasOperationalAccess)
    <div class="tf-card p-5 text-center">
        <i class="bi bi-shield-lock fs-2 text-warning"></i>
        <h2 class="h5 mt-3">Operational Access Not Configured</h2>
        <p class="tf-muted mb-0">Your business has been approved, but no operational modules have been assigned to your company yet.</p>
    </div>
@else
    <div class="quick-actions mb-4">
        @if($companyPermissions->allowsUser(auth()->user(), 'dashboard.quick_add_product') && $companyPermissions->allowsUser(auth()->user(), 'products.create'))
            <a href="{{ route('business.products.create') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>Add Product</a>
        @endif
        @if($companyPermissions->allowsUser(auth()->user(), 'dashboard.quick_create_sale') && $companyPermissions->allowsUser(auth()->user(), 'sales.create'))
            <a href="{{ route('business.sales.create') }}" class="btn btn-outline-primary"><i class="bi bi-bag-plus me-1"></i>Create Sale</a>
        @endif
        @if($companyPermissions->allowsUser(auth()->user(), 'dashboard.quick_pos_sale') && $companyPermissions->allowsUser(auth()->user(), 'pos.create_sale'))
            <a href="{{ route('business.pos.index') }}" class="btn btn-outline-primary"><i class="bi bi-upc-scan me-1"></i>New POS Sale</a>
        @endif
        @if($companyPermissions->allowsUser(auth()->user(), 'dashboard.quick_add_customer') && $companyPermissions->allowsUser(auth()->user(), 'customers.create'))
            <a href="{{ route('business.customers.index') }}" class="btn btn-outline-primary"><i class="bi bi-person-plus me-1"></i>Add Customer</a>
        @endif
        @if($companyPermissions->allowsUser(auth()->user(), 'dashboard.quick_new_purchase') && $companyPermissions->allowsUser(auth()->user(), 'purchases.create'))
            <a href="{{ route('business.purchases.index', ['create' => 1]) }}#purchase-create" class="btn btn-outline-primary"><i class="bi bi-cart-plus me-1"></i>New Purchase</a>
        @endif
    </div>

    <div class="dashboard-cards">
        @foreach($dashboardCards as $dashboardIndex => $dashboardCard)
            @php([$permission, $label, $value, $icon, $color, $note] = $dashboardCard)
            @php($dashboardPermission = $dashboardCardPermissions[$dashboardIndex])
            @if($companyPermissions->allowsUser(auth()->user(), $dashboardPermission) && $companyPermissions->allowsUser(auth()->user(), $permission))
                <div>@include('components.card', compact('label', 'value', 'icon', 'color', 'note'))</div>
            @endif
        @endforeach
    </div>

    <div class="row g-4 mt-1">
        @if($companyPermissions->allowsUser(auth()->user(), 'dashboard.widget_recent_sales') && $companyPermissions->allowsUser(auth()->user(), 'sales.view'))
            <div class="col-lg-7"><div class="tf-card p-4"><h2 class="h5">Recent Sales</h2><x-table><thead><tr><th>Sale</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead><tbody>@forelse($recentOrders ?? [] as $order)<tr><td>{{ $order->order_number }}</td><td>{{ $order->customer?->display_name }}</td><td>Rs {{ number_format($order->grand_total ?: $order->total) }}</td><td>{{ $order->status }}</td></tr>@empty<tr><td colspan="4" class="text-center tf-muted py-4">No sales yet.</td></tr>@endforelse</tbody></x-table></div></div>
        @endif
        @if($companyPermissions->allowsUser(auth()->user(), 'dashboard.widget_low_stock_alerts') && $companyPermissions->allowsUser(auth()->user(), 'inventory.view'))
            <div class="col-lg-5"><div class="tf-card p-4"><h2 class="h5">Low Stock Alerts</h2><div class="d-grid gap-2">@forelse($lowStockProducts ?? [] as $product)<div class="p-3 border rounded d-flex justify-content-between"><span>{{ $product->name }} - {{ $product->stock_quantity }} {{ $product->unit }}</span><i class="bi bi-exclamation-circle text-danger"></i></div>@empty<p class="tf-muted mb-0">No low stock products.</p>@endforelse</div></div></div>
        @endif
        @if($companyPermissions->allowsUser(auth()->user(), 'notifications.view'))
            <div class="col-lg-5"><div class="tf-card p-4"><div class="d-flex justify-content-between align-items-center"><div><h2 class="h5 mb-1">Updates</h2><p class="tf-muted mb-0">{{ auth()->user()->unreadNotifications()->count() }} unread business and platform updates.</p></div><a href="{{ auth()->user()->role === 'super_admin' && session('super_admin_business_context_id') ? route('business.context.notifications') : route('notifications.index') }}" class="btn btn-outline-primary btn-sm">Open updates</a></div></div></div>
        @endif
    </div>
@endif
@endsection
