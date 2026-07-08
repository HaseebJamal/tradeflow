@extends('layouts.dashboard')
@section('title', 'Business Dashboard | TradeFlow')
@section('page-title', 'Business Dashboard')
@section('page-subtitle', 'Wholesale operations overview')
@section('content')
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('business.products.create') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>Add Product</a>
    <a href="{{ route('business.orders.create') }}" class="btn btn-outline-primary"><i class="bi bi-bag-plus me-1"></i>Create Order</a>
    <a href="{{ route('business.customers.index') }}" class="btn btn-outline-primary"><i class="bi bi-person-plus me-1"></i>Add Customer</a>
    <a href="{{ route('business.payments') }}" class="btn btn-outline-primary"><i class="bi bi-cash-stack me-1"></i>Record Payment</a>
</div>
<div class="row g-3">
@foreach([
    ['Total Revenue','Rs '.number_format($revenue ?? 0),'bi-currency-dollar','bg-green','Paid income'],
    ['Today Revenue','Rs '.number_format($todayRevenue ?? 0),'bi-calendar-day','bg-green','Today'],
    ['Total Orders',$ordersCount ?? 0,'bi-bag-check','bg-blue','All orders'],
    ['Pending Orders',$pendingOrders ?? 0,'bi-hourglass-split','bg-amber','Needs action'],
    ['Completed Orders',$completedOrders ?? 0,'bi-check2-circle','bg-green','Delivered/completed'],
    ['Customers/Retailers',$customersCount ?? 0,'bi-people','bg-navy','Customer book'],
    ['Products Count',$productsCount ?? 0,'bi-box','bg-blue','Catalog'],
    ['Pending Payments','Rs '.number_format($pendingPayments ?? 0),'bi-wallet2','bg-amber','Khata balance'],
    ['Low Stock Products',$lowStock ?? 0,'bi-exclamation-triangle','bg-red','Restock soon'],
    ['Monthly Expenses','Rs '.number_format($expenses ?? 0),'bi-receipt','bg-amber','This month'],
    ['Profit/Loss','Rs '.number_format($profit ?? 0),'bi-graph-up-arrow','bg-green','Revenue minus expenses'],
] as [$label,$value,$icon,$color,$note])
<div class="col-md-6 col-xl-3">@include('components.card', compact('label','value','icon','color','note'))</div>
@endforeach
</div>
<div class="row g-4 mt-1">
    <div class="col-lg-7"><div class="tf-card p-4"><h2 class="h5">Recent Orders</h2><x-table><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead><tbody>@forelse($recentOrders ?? [] as $order)<tr><td>{{ $order->order_number }}</td><td>{{ $order->customer?->business_name ?? $order->customer?->name }}</td><td>Rs {{ number_format($order->grand_total ?: $order->total) }}</td><td>{{ $order->status }}</td></tr>@empty<tr><td colspan="4" class="text-center tf-muted py-4">No orders yet.</td></tr>@endforelse</tbody></x-table></div></div>
    <div class="col-lg-5"><div class="tf-card p-4"><h2 class="h5">Low Stock Alerts</h2><div class="d-grid gap-2">@forelse($lowStockProducts ?? [] as $product)<div class="p-3 border rounded d-flex justify-content-between"><span>{{ $product->name }} - {{ $product->stock_quantity }} {{ $product->unit }}</span><i class="bi bi-exclamation-circle text-danger"></i></div>@empty<p class="tf-muted mb-0">No low stock products.</p>@endforelse</div></div></div>
</div>
@endsection
