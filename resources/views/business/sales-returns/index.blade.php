@extends('layouts.dashboard')
@section('page-title', 'Sales Returns')
@section('page-subtitle', 'Review customer returns and the related inventory reversal')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="h5 mb-0">Sales Returns</h2><p class="tf-muted mb-0">Process returns from completed sales.</p></div>
    <div class="d-flex gap-2"><a href="{{ route('business.sales.index') }}" class="btn btn-outline-secondary">All Sales</a><a href="{{ route('business.sales.returns.create') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>New Return</a></div>
</div>
<form class="tf-card p-3 mb-3 row g-2 align-items-end">
    <div class="col-md-5"><label class="form-label">Search sale or return number</label><select name="search" class="form-select" data-placeholder="All sales and returns" autofocus><option value="">All</option>@foreach($references as $reference)<option value="{{ $reference }}" @selected(request('search') === $reference)>{{ $reference }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control"></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-outline-primary">Filter</button><a href="{{ route('business.sales.returns.index', ['clear' => 1]) }}" class="btn btn-outline-secondary">Clear</a></div>
</form>
<x-table class="tf-business-data-table">
    <thead><tr><th>Return Number</th><th>Sale Number</th><th>Customer</th><th>Return Date</th><th>Refund Method</th><th>Refund Amount</th><th>Status</th><th>Created By</th><th>Actions</th></tr></thead>
    <tbody>@forelse($returns as $return)
        <tr>
            <td><strong>{{ $return->return_number ?? '-' }}</strong></td>
            <td><strong>{{ $return->order?->invoice?->invoice_number ?? $return->order?->order_number ?? '-' }}</strong></td>
            <td>{{ $return->customer?->name ?? 'Walk-in Customer' }}</td>
            <td><x-date-time :value="$return->returned_at" /></td>
            <td>{{ $return->refund_method }}</td>
            <td>Rs {{ number_format($return->refund_amount, 2) }}</td>
            <td>{{ $return->order?->status ?? '-' }}</td>
            <td>{{ $return->processor?->name ?? '-' }}</td>
            <td><button type="button" class="btn btn-sm btn-outline-primary tf-table-view-action" data-bs-toggle="modal" data-bs-target="#salesReturnDetailsModal{{ $return->id }}">View</button></td>
        </tr>
    @empty
        <tr><td colspan="9" class="text-center tf-muted py-5">No sales returns found.</td></tr>
    @endforelse</tbody>
</x-table>
@foreach($returns as $return)
    <x-record-details-modal :id="'salesReturnDetailsModal'.$return->id" :title="'Return '.($return->return_number ?? '#'.$return->id)" :status="$return->order?->status" :open-url="route('business.sales.returns.show', $return)">
        <div class="tf-record-details-grid mb-4">
            <div><span>Sale</span><strong>{{ $return->order?->invoice?->invoice_number ?? $return->order?->order_number ?? '-' }}</strong></div>
            <div><span>Customer</span><strong>{{ $return->customer?->name ?? 'Walk-in Customer' }}</strong></div>
            <div><span>Returned on</span><strong><x-date-time :value="$return->returned_at" /></strong></div>
            <div><span>Refund</span><strong>Rs {{ number_format($return->refund_amount, 2) }}</strong></div>
            <div><span>Refund method</span><strong>{{ $return->refund_method ?? '-' }}</strong></div>
            <div><span>Processed by</span><strong>{{ $return->processor?->name ?? '-' }}</strong></div>
        </div>
        <h3 class="h6 mb-2">Returned items</h3>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Product</th><th class="text-end">Qty</th><th class="text-end">Refund</th></tr></thead><tbody>@forelse($return->items as $item)<tr><td>{{ $item->orderItem?->product?->name ?? 'Product' }}</td><td class="text-end">{{ $item->quantity }}</td><td class="text-end">Rs {{ number_format($item->refund_total, 2) }}</td></tr>@empty<tr><td colspan="3" class="text-center tf-muted py-3">No returned items found.</td></tr>@endforelse</tbody></table></div>
    </x-record-details-modal>
@endforeach
<div class="mt-3"><x-table-result-summary :paginator="$returns" />{{ $returns->links('pagination::bootstrap-5') }}</div>
@endsection
