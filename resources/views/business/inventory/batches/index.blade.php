@extends('layouts.dashboard')
@section('page-title', 'Batches & Expiry')
@section('page-subtitle', 'Track received batches, expiry dates, and sellable batch stock')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="h5 mb-1">Batch inventory</h2><p class="tf-muted mb-0">Expired batches stay in physical stock but are never included in POS sellable stock.</p></div>
    <a href="{{ route('business.inventory') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>Back to Inventory</a>
</div>
@if($unallocated->isNotEmpty())
<div class="tf-card border-warning p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center"><div><strong><i class="bi bi-exclamation-triangle text-warning me-1"></i>Opening batch allocation required</strong><div class="tf-muted small">Existing stock for these tracked products cannot be sold by FEFO until it is allocated to a real batch.</div></div></div>
    <div class="row g-2 mt-1">
    @foreach($unallocated as $product)
        @php($amount = max(0, (float) $product->stock_quantity - (float) $product->batches()->sum('remaining_quantity')))
        <div class="col-lg-6"><form method="POST" action="{{ route('business.inventory.batches.opening-allocation', $product) }}" class="border rounded p-2 row g-2 align-items-end">@csrf
            <div class="col-12"><strong class="small">{{ $product->name }}</strong><span class="tf-muted small ms-1">Unallocated: <x-quantity :value="$amount" /></span></div>
            <div class="col-sm-4"><label class="form-label small mb-1">Batch no.</label><input required name="batch_number" class="form-control form-control-sm"></div>
            <div class="col-sm-3"><label class="form-label small mb-1">Mfg. date</label><input name="manufacturing_date" type="date" class="form-control form-control-sm"></div>
            <div class="col-sm-3"><label class="form-label small mb-1">Expiry</label><input required name="expiry_date" type="date" min="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
            <input type="hidden" name="quantity" value="{{ $amount }}"><div class="col-sm-2"><button class="btn btn-sm btn-outline-primary w-100">Allocate</button></div>
        </form></div>
    @endforeach
    </div>
</div>
@endif
<form method="GET" class="tf-card p-3 mb-3 row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Search</label><input name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Product or batch number"></div>
    <div class="col-md-2"><label class="form-label">Product</label><select name="product_id" class="form-select"><option value="">All products</option>@foreach($trackedProducts as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? null) == $product->id)>{{ $product->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Unit</label><select name="unit_id" class="form-select"><option value="">All units</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(($filters['unit_id'] ?? null) == $unit->id)>{{ $unit->unit_name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Expiry status</label><select name="status" class="form-select">@foreach(['All','Valid','Expiring Soon','Expired','Depleted'] as $status)<option @selected(($filters['status'] ?? 'All') === $status)>{{ $status }}</option>@endforeach</select></div>
    <div class="col-md-1 d-flex gap-1"><button class="btn btn-tf-primary">Filter</button><a href="{{ route('business.inventory.batches.index') }}" class="btn btn-outline-primary">Clear</a></div>
</form>
<x-table class="tf-business-data-table"><thead><tr><th>Product</th><th>Batch</th><th>Received / remaining</th><th>Manufactured</th><th>Expiry</th><th>Status</th><th>GRN / purchase</th></tr></thead><tbody>
@forelse($batches as $batch)
<tr><td><strong>{{ $batch->product?->name }}</strong><small class="d-block tf-muted">{{ $batch->product?->category?->name }} · {{ $batch->product?->unitRecord?->unit_name ?? $batch->product?->unit }}</small></td><td class="text-nowrap">{{ $batch->batch_number }}</td><td><x-quantity :value="$batch->received_quantity" /> / <x-quantity :value="$batch->remaining_quantity" /></td><td>{{ $batch->manufacturing_date?->format('n/j/Y') ?? '—' }}</td><td class="text-nowrap">{{ $batch->expiry_date?->format('n/j/Y') ?? '—' }}</td><td>@php($status = $batch->expiry_status) <span class="tf-badge {{ $status === 'Expired' ? 'tf-badge-danger' : ($status === 'Expiring Soon' ? 'tf-badge-warning' : ($status === 'Valid' ? 'tf-badge-success' : 'tf-badge-secondary')) }}">{{ $status }}</span></td><td><span class="small">{{ $batch->goodsReceipt?->grn_number ?? '—' }}</span><small class="d-block tf-muted">{{ $batch->purchase?->purchase_number ?? $batch->source }}</small></td></tr>
@empty <tr><td colspan="7" class="text-center tf-muted py-4">No tracked batches match these filters.</td></tr>@endforelse
</tbody></x-table>
<div class="mt-3"><x-table-result-summary :paginator="$batches" />{{ $batches->links('pagination::bootstrap-5') }}</div>
@endsection
