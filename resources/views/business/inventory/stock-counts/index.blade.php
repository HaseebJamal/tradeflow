@extends('layouts.dashboard')

@section('page-title', 'Stock Count')
@section('page-subtitle', 'Compare physical stock to the captured system-stock baseline')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div><h2 class="h5 mb-1">Stock Count Sessions</h2><p class="tf-muted mb-0">Draft counts do not change inventory until you finalize them.</p></div>
    <div class="d-flex gap-2"><a href="{{ route('business.inventory') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Inventory</a><button type="button" class="btn btn-tf-primary" data-bs-toggle="modal" data-bs-target="#newStockCountModal"><i class="bi bi-plus-lg me-1"></i>New Stock Count</button></div>
</div>

<form method="GET" class="tf-card p-3 mb-3 row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Search reference</label><input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="STK-000001"></div>
    <div class="col-md-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">All statuses</option>@foreach(['Draft', 'In Progress', 'Completed', 'Cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Date from</label><input class="form-control" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
    <div class="col-md-2"><label class="form-label">Date to</label><input class="form-control" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-outline-primary">Filter</button><a class="btn btn-outline-secondary" href="{{ route('business.inventory.stock-counts.index') }}">Clear</a></div>
</form>

<x-table class="tf-business-data-table">
    <thead><tr><th>Reference</th><th>Date</th><th>Products</th><th>Matched</th><th>Shortage</th><th>Excess</th><th>Status</th><th>Created By</th><th class="text-end">Actions</th></tr></thead>
    <tbody>@forelse($counts as $count)
        <tr>
            <td><strong>{{ $count->reference }}</strong></td><td><x-date-time :value="$count->counted_at" /></td><td>{{ $count->items_count }}</td>
            <td>{{ $count->matched_count }}</td><td class="{{ $count->shortage_count ? 'text-danger fw-semibold' : '' }}">{{ $count->shortage_count }}</td><td class="{{ $count->excess_count ? 'text-primary fw-semibold' : '' }}">{{ $count->excess_count }}</td>
            <td><span class="tf-badge {{ $count->status === 'Completed' ? 'tf-badge-success' : ($count->status === 'Cancelled' ? 'tf-badge-secondary' : 'tf-badge-warning') }}">{{ $count->status }}</span></td>
            <td>{{ $count->creator?->name ?? 'System' }}</td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ in_array($count->status, ['Completed', 'Cancelled'], true) ? route('business.inventory.stock-counts.show', $count) : route('business.inventory.stock-counts.edit', $count) }}">{{ in_array($count->status, ['Completed', 'Cancelled'], true) ? 'View' : 'Open' }}</a></td>
        </tr>
    @empty<tr><td colspan="9" class="text-center tf-muted py-4">No stock count sessions yet.</td></tr>@endforelse</tbody>
</x-table>
<div class="mt-3"><x-table-result-summary :paginator="$counts" />{{ $counts->links('pagination::bootstrap-5') }}</div>

<div class="modal fade" id="newStockCountModal" tabindex="-1" aria-labelledby="newStockCountModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('business.inventory.stock-counts.create') }}" class="modal-content">@csrf
    <div class="modal-header"><h3 class="modal-title h5" id="newStockCountModalTitle">New Stock Count</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><p class="tf-muted small">A new business-scoped STK reference is reserved now. Stock will not change until finalization.</p><label class="form-label">Count date &amp; time</label><input class="form-control mb-3" type="datetime-local" name="counted_at" value="{{ now()->format('Y-m-d\\TH:i') }}"><label class="form-label">Notes <span class="tf-muted">(optional)</span></label><textarea class="form-control" name="notes" rows="3"></textarea></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tf-primary">Create Count</button></div>
</form></div></div>
@endsection
