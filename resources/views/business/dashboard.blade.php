@extends('layouts.dashboard')
@section('title', 'Business Dashboard | TradeFlow')
@section('page-title', 'Business Dashboard')
@section('page-subtitle', 'Wholesale operations overview')
@section('content')
@if(!$hasOperationalAccess)
    <div class="tf-card p-5 text-center"><i class="bi bi-shield-lock fs-2 text-warning"></i><h2 class="h5 mt-3">Operational Access Not Configured</h2><p class="tf-muted mb-0">Your business has been approved, but no operational modules have been assigned to your company yet.</p></div>
@else
    <div class="quick-actions mb-4">
        @companyCan('products.create')<a href="{{ route('business.products.create') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>Add Product</a>@endcompanyCan
        @companyCan('orders.create')<a href="{{ route('business.orders.create') }}" class="btn btn-outline-primary"><i class="bi bi-bag-plus me-1"></i>Create Order</a>@endcompanyCan
        @companyCan('pos.create_sale')<a href="{{ route('business.pos.index') }}" class="btn btn-outline-primary"><i class="bi bi-upc-scan me-1"></i>New POS Sale</a>@endcompanyCan
        @companyCan('customers.create')<a href="{{ route('business.customers.index') }}" class="btn btn-outline-primary"><i class="bi bi-person-plus me-1"></i>Add Customer</a>@endcompanyCan
        @companyCan('payments.create')<a href="{{ route('business.payments') }}" class="btn btn-outline-primary"><i class="bi bi-cash-stack me-1"></i>Record Payment</a>@endcompanyCan
    </div>
    <div class="dashboard-cards">
        @foreach([
            ['orders.view','Total Sales','Rs '.number_format($totalSales ?? 0),'bi-graph-up','bg-blue','Order value'], ['orders.view','Today Sales','Rs '.number_format($todaySales ?? 0),'bi-calendar-day','bg-green','Today'],
            ['pos.view','POS Sales Today','Rs '.number_format($todayPosSales ?? 0),'bi-upc-scan','bg-navy','Counter sales'],
            ['orders.view','Receivables','Rs '.number_format($receivables ?? 0),'bi-wallet2','bg-amber','Customer balance'], ['suppliers.view','Payables','Rs '.number_format($payables ?? 0),'bi-credit-card','bg-amber','Supplier balance'],
            ['inventory.view','Inventory Value','Rs '.number_format($inventoryValue ?? 0),'bi-boxes','bg-navy','Stock cost'], ['suppliers.view','Total Suppliers',$suppliersCount ?? 0,'bi-building-add','bg-blue','Supplier book'],
            ['customers.view','Total Customers',$customersCount ?? 0,'bi-people','bg-navy','Customer book'], ['orders.view','Orders',$ordersCount ?? 0,'bi-bag-check','bg-blue','All orders'],
            ['expenses.view','Expenses','Rs '.number_format($expenses ?? 0),'bi-receipt','bg-amber','This month'], ['payments.view','Profit/Loss','Rs '.number_format($profit ?? 0),'bi-graph-up-arrow','bg-green','Revenue minus expenses'],
            ['inventory.view','Low Stock Products',$lowStock ?? 0,'bi-exclamation-triangle','bg-red','Restock soon'],
        ] as [$permission,$label,$value,$icon,$color,$note])
            @companyCan($permission)<div>@include('components.card', compact('label','value','icon','color','note'))</div>@endcompanyCan
        @endforeach
    </div>
    <div class="row g-4 mt-1">
        @companyCan('orders.view')<div class="col-lg-7"><div class="tf-card p-4"><h2 class="h5">Recent Orders</h2><x-table><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead><tbody>@forelse($recentOrders ?? [] as $order)<tr><td>{{ $order->order_number }}</td><td>{{ $order->customer?->business_name ?? $order->customer?->name }}</td><td>Rs {{ number_format($order->grand_total ?: $order->total) }}</td><td>{{ $order->status }}</td></tr>@empty<tr><td colspan="4" class="text-center tf-muted py-4">No orders yet.</td></tr>@endforelse</tbody></x-table></div></div>@endcompanyCan
        @companyCan('inventory.view')<div class="col-lg-5"><div class="tf-card p-4"><h2 class="h5">Low Stock Alerts</h2><div class="d-grid gap-2">@forelse($lowStockProducts ?? [] as $product)<div class="p-3 border rounded d-flex justify-content-between"><span>{{ $product->name }} - {{ $product->stock_quantity }} {{ $product->unit }}</span><i class="bi bi-exclamation-circle text-danger"></i></div>@empty<p class="tf-muted mb-0">No low stock products.</p>@endforelse</div></div></div>@endcompanyCan
    </div>
@endif
@endsection
