@extends('layouts.dashboard')

@section('title', 'Business Dashboard | TradeFlow')
@section('page-title', 'Business Dashboard')
@section('page-subtitle', 'Wholesale operations overview')

@section('content')
@php
    $companyPermissions = app(\App\Services\CompanyPermissionService::class);
    $dashboardCards = [
        ['dashboard.card_total_sales', 'sales.view', 'Total Sales', 'Rs '.number_format($totalSales ?? 0), 'bi-graph-up', 'bg-blue', 'All completed sales'],
        ['dashboard.card_today_sales', 'sales.view', "Today's Sales", 'Rs '.number_format($todaySales ?? 0), 'bi-calendar-day', 'bg-green', 'Sales created today'],
        ['dashboard.card_inventory_value', 'inventory.view', 'Inventory Value', 'Rs '.number_format($inventoryValue ?? 0), 'bi-boxes', 'bg-navy', 'Current stock cost'],
        ['dashboard.card_receivables', 'sales.view', 'Receivables', 'Rs '.number_format($receivables ?? 0), 'bi-wallet2', 'bg-amber', 'Customer balances due'],
        ['dashboard.card_payables', 'purchases.view', 'Payables', 'Rs '.number_format($payables ?? 0), 'bi-credit-card', 'bg-amber', 'Supplier balances due'],
        ['dashboard.card_profit_loss', 'accounting.view', 'Total Profit / Loss', 'Rs '.number_format($profit ?? 0), 'bi-graph-up-arrow', 'bg-green', 'All-time sales less cost and expenses'],
        ['dashboard.card_low_stock', 'inventory.view', 'Low Stock', $lowStock ?? 0, 'bi-exclamation-triangle', 'bg-red', 'Restock required'],
        ['dashboard.card_total_customers', 'customers.view', 'Total Customers', $customersCount ?? 0, 'bi-people', 'bg-navy', 'Customer master records'],
        ['dashboard.card_total_suppliers', 'suppliers.view', 'Total Suppliers', $suppliersCount ?? 0, 'bi-building-add', 'bg-blue', 'Supplier master records'],
        ['dashboard.card_pending_deliveries', 'deliveries.view', 'Pending Deliveries', $pendingDeliveries ?? 0, 'bi-truck', 'bg-blue', 'Awaiting delivery completion'],
        ['dashboard.card_today_purchases', 'purchases.view', "Today's Purchases", 'Rs '.number_format($todayPurchases ?? 0), 'bi-cart-plus', 'bg-navy', 'Purchase orders created today'],
        ['dashboard.card_monthly_profit', 'accounting.view', 'Monthly Profit / Loss', 'Rs '.number_format($monthlyProfit ?? 0), 'bi-calendar-month', 'bg-green', 'Current month sales less cost and expenses'],
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
        @if($companyPermissions->allowsUser(auth()->user(), 'customers.create'))
            <a href="{{ route('business.customers.index') }}" class="btn btn-outline-primary"><i class="bi bi-person-plus me-1"></i>Add Customer</a>
        @endif
        @if($companyPermissions->allowsUser(auth()->user(), 'dashboard.quick_new_purchase') && $companyPermissions->allowsUser(auth()->user(), 'purchases.create'))
            <a href="{{ route('business.purchases.index', ['create' => 1]) }}#purchase-create" class="btn btn-outline-primary"><i class="bi bi-cart-plus me-1"></i>New Purchase</a>
        @endif
        @if($companyPermissions->allowsUser(auth()->user(), 'pos.view'))
            <a href="{{ route('business.pos.index') }}" class="btn btn-outline-primary"><i class="bi bi-cash-register me-1"></i>Open POS</a>
        @endif
    </div>

    <div class="dashboard-cards">
        @foreach($dashboardCards as $dashboardCard)
            @php([$dashboardPermission, $permission, $label, $value, $icon, $color, $note] = $dashboardCard)
            @if($companyPermissions->allowsUser(auth()->user(), $dashboardPermission) && $companyPermissions->allowsUser(auth()->user(), $permission))
                <div>@include('components.card', compact('label', 'value', 'icon', 'color', 'note'))</div>
            @endif
        @endforeach
    </div>

@endif
@endsection
