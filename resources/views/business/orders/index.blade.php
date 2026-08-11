@extends('layouts.dashboard')
@section('page-title', 'Sales')
@section('page-subtitle', 'Sales orders, invoices, customer payments, and sales history')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div>
        <h2 class="h5 mb-1">Sales Directory</h2>
        <p class="tf-muted mb-0">Search, filter, and manage sales orders, invoices, and customer balances.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @companyCan('sales.payments')
            <a href="{{ route('business.sales.payments.index') }}" class="btn btn-outline-primary"><i class="bi bi-cash-stack me-1"></i>Customer Payments</a>
        @endcompanyCan
        @companyCan('sales.invoices')
            <a href="{{ route('business.sales.invoices.index') }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-text me-1"></i>Sales Invoices</a>
        @endcompanyCan
    </div>
</div>
<form class="tf-card p-4 mb-3" data-code-lookup-form data-code-lookup-url="{{ route('business.sales.lookup') }}">
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label">Sale Number</label><select name="order_number" class="form-select" autofocus><option value="">All</option>@foreach($saleNumbers as $saleNumber)<option value="{{ $saleNumber }}" @selected(request('order_number') === $saleNumber)>{{ $saleNumber }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Customer</label><select name="customer_id" class="form-select"><option value="">All</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->display_name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Product</label><select name="product_id" class="form-select"><option value="">All</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option>@foreach(['New','Accepted','Packing','Ready','Out For Delivery','Delivered','Completed','Partially Returned','Returned','Cancelled','Void'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Payment Status</label><select name="payment_status" class="form-select"><option value="">All</option>@foreach(['Pending','Partial','Paid'] as $status)<option @selected(request('payment_status')===$status)>{{ $status }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Payment Type</label><select name="payment_type" class="form-select"><option value="">All</option>@foreach(['Cash','Credit','Partial'] as $type)<option @selected(request('payment_type')===$type)>{{ $type }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Created By</label><select name="created_by" class="form-select"><option value="">All</option>@foreach($creators as $creator)<option value="{{ $creator->id }}" @selected(request('created_by') == $creator->id)>{{ $creator->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ request('date_from', $dateFrom) }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ request('date_to', $dateTo) }}" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
        <div class="col-md-2"><a href="{{ route('business.sales.index', ['clear' => 1]) }}" class="btn btn-outline-secondary w-100">Clear Filters</a></div>
    </div>
</form>
<x-table class="tf-business-data-table">
    <thead><tr><th>Sale Number</th><th>Source</th><th>Customer</th><th>Product Summary</th><th>Status</th><th>Payment Status</th><th>Payment Type</th><th>Created By</th><th>Sale Date and Time</th><th>Updated At</th><th>Total</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($orders ?? [] as $order)
        <tr>
            <td>{{ $order->order_number }}</td>
            <td>Sale</td>
            <td>{{ $order->customer?->display_name ?? 'Walk-in' }}</td>
            <td>{{ $order->items->map(fn($item) => ($item->product_name_snapshot ?: $item->product?->name ?: 'Deleted Product').' x '.(rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') ?: '0'))->implode(', ') }}</td>
            <td>{{ $order->status }}</td>
            <td>{{ $order->payment_status }}</td>
            <td>{{ $order->payment_type }}</td>
            <td>{{ $order->creator?->name ?? '-' }}</td>
            <td><x-date-time :value="$order->order_date ?: $order->created_at" /></td>
            <td><x-date-time :value="$order->updated_at" /></td>
            <td>Rs {{ number_format($order->grand_total ?: $order->total) }}</td>
            <td class="text-end text-nowrap">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-display="dynamic">Actions</button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm">
                        <a href="{{ route('business.sales.show', $order) }}" class="dropdown-item">View</a>
                        @companyCan('sales.edit')<a href="{{ route('business.sales.edit', $order) }}" class="dropdown-item">Edit</a>@endcompanyCan
                        @if($order->invoice)<a href="{{ route('business.sales.invoices.show', $order) }}" class="dropdown-item">Invoice</a><a href="{{ route('business.sales.invoices.pdf', $order) }}" class="dropdown-item" target="_blank">Print</a>@endif
                        @companyCan('sales.returns')<a href="{{ route('business.sales.returns.process', $order) }}" class="dropdown-item">Return</a>@endcompanyCan
                        @companyCan('sales.delete')<div class="dropdown-divider"></div><form method="POST" action="{{ route('business.sales.destroy', $order) }}" onsubmit="return confirm('Delete this sale when safe?')">@csrf @method('DELETE')<button class="dropdown-item text-danger">Delete</button></form>@endcompanyCan
                    </div>
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="12" class="text-center tf-muted py-4">No sales yet.</td></tr>
    @endforelse
    </tbody>
</x-table>
@if(isset($orders) && method_exists($orders, 'links'))<div class="mt-3"><x-table-result-summary :paginator="$orders" />{{ $orders->links('pagination::bootstrap-5') }}</div>@endif
@endsection
