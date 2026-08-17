@extends('layouts.dashboard')
@section('page-title', 'Purchase Suggestions')
@section('page-subtitle', 'Plan replenishment from live sellable stock and open incoming goods')
@section('content')
@php($quantity = static fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="h5 mb-1">Reorder planning</h2><p class="tf-muted mb-0">Suggestions are recommendations only. No purchase is created until you review it.</p></div>
    <div class="d-flex gap-2"><a href="{{ route('business.inventory') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>Inventory</a>@if($canConfigure)<a href="{{ route('business.inventory.purchase-suggestions.settings') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-sliders me-1"></i>Reorder Settings</a>@endif</div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="tf-card p-3 h-100"><small class="tf-muted d-block">Products below reorder</small><strong class="fs-4">{{ number_format($summary['products']) }}</strong></div></div>
    <div class="col-sm-6 col-xl-3"><div class="tf-card p-3 h-100"><small class="tf-muted d-block">Suggested units</small><strong class="fs-4">{{ $quantity($summary['units']) }}</strong></div></div>
    <div class="col-sm-6 col-xl-3"><div class="tf-card p-3 h-100"><small class="tf-muted d-block">Estimated purchase value</small><strong class="fs-4">{{ $summary['has_estimated_cost'] ? 'Rs '.number_format($summary['estimated_cost'], 2) : '—' }}</strong></div></div>
    <div class="col-sm-6 col-xl-3"><div class="tf-card p-3 h-100"><small class="tf-muted d-block">Out of stock</small><strong class="fs-4 text-danger">{{ number_format($summary['out_of_stock']) }}</strong></div></div>
</div>

<form method="GET" class="tf-card p-3 mb-3"><div class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label">Search product</label><input name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Product name or barcode"></div>
    <div class="col-md-2"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Unit</label><select name="unit_id" class="form-select"><option value="">All units</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(($filters['unit_id'] ?? null) == $unit->id)>{{ $unit->short_code ?: $unit->unit_name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? null) == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Stock status</label><select name="stock_status" class="form-select"><option value="all" @selected(($filters['stock_status'] ?? 'all') === 'all')>All suggestions</option><option value="below_reorder" @selected(($filters['stock_status'] ?? null) === 'below_reorder')>Below reorder</option><option value="out_of_stock" @selected(($filters['stock_status'] ?? null) === 'out_of_stock')>Out of stock</option></select></div>
    <div class="col-md-2"><label class="form-label">Rows</label><select name="per_page" class="form-select">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 10) == $size)>{{ $size }}</option>@endforeach</select></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-tf-primary flex-grow-1">Filter</button><a href="{{ route('business.inventory.purchase-suggestions.index') }}" class="btn btn-outline-primary">Clear</a></div>
</div></form>

<form method="GET" action="{{ route('business.purchases.from-suggestions') }}" data-suggestion-purchase-form>
    <div class="tf-card overflow-hidden">
        <div class="table-responsive"><table class="table align-middle mb-0 tf-business-data-table"><thead><tr>
            <th style="width:42px"><input type="checkbox" class="form-check-input" data-suggestions-select-all aria-label="Select all suggestions"></th><th>Product</th><th>Category</th><th>Unit</th><th class="text-end">Current</th><th class="text-end">Incoming</th><th class="text-end">Projected</th><th class="text-end">Reorder</th><th class="text-end">Target</th><th>Suggested Qty</th><th>Latest Cost</th><th>Estimated Cost</th><th>Latest Supplier</th><th>Status</th>
        </tr></thead><tbody>
        @forelse($suggestions as $row)
            @php($badge = $row->status === 'Out of Stock' ? 'text-bg-danger' : 'text-bg-warning')
            <tr>
                <td><input type="checkbox" class="form-check-input" data-suggestion-select value="{{ $row->product_id }}" aria-label="Select {{ $row->name }}" @disabled($row->suggested_quantity <= 0)></td>
                <td><strong>{{ $row->name }}</strong>@if($row->is_batch_tracked)<small class="d-block tf-muted">Valid batch stock only</small>@endif</td><td>{{ $row->category }}</td><td>{{ $row->unit }}</td>
                <td class="text-end">{{ $quantity($row->current_stock) }}</td><td class="text-end">{{ $quantity($row->open_incoming) }}</td><td class="text-end">{{ $quantity($row->projected_stock) }}</td><td class="text-end">{{ $quantity($row->reorder_level) }}</td><td class="text-end">{{ $quantity($row->target_stock) }}</td>
                <td><input type="number" min="1" step="1" class="form-control form-control-sm" style="min-width:92px" data-suggestion-quantity value="{{ $row->suggested_quantity > 0 ? (int) ceil($row->suggested_quantity) : 0 }}" disabled aria-label="Purchase quantity for {{ $row->name }}"></td>
                <td>{{ $row->latest_cost !== null ? 'Rs '.number_format($row->latest_cost, 2) : '—' }}</td><td>{{ $row->estimated_cost !== null ? 'Rs '.number_format($row->estimated_cost, 2) : '—' }}</td><td>{{ $row->supplier }}</td><td><span class="badge {{ $badge }}">{{ $row->status }}</span>@if($row->suggested_quantity <= 0)<small class="d-block tf-muted">Incoming stock covers target</small>@endif</td>
            </tr>
        @empty<tr><td colspan="14" class="text-center tf-muted py-5">No products currently need a reorder based on the configured levels.</td></tr>@endforelse
        </tbody></table></div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-top">
            <div class="tf-muted small" data-suggestion-selection-status>Select products to create a purchase draft.</div>
            @if($canCreatePurchase)<button class="btn btn-tf-primary" type="submit" data-suggestion-create disabled><i class="bi bi-cart-plus me-1"></i>Create Purchase</button>@else<span class="tf-muted small">Purchase creation is not available for your role.</span>@endif
        </div>
    </div>
</form>
<div class="mt-3"><x-table-result-summary :paginator="$suggestions" />{{ $suggestions->links('pagination::bootstrap-5') }}</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-suggestion-purchase-form]'); if (!form) return;
    const all = form.querySelector('[data-suggestions-select-all]'); const rows = () => [...form.querySelectorAll('[data-suggestion-select]')];
    const status = form.querySelector('[data-suggestion-selection-status]'); const create = form.querySelector('[data-suggestion-create]');
    const sync = () => { const selectable = rows().filter(input => !input.disabled); const selected = selectable.filter(input => input.checked); rows().forEach(input => { const qty = input.closest('tr').querySelector('[data-suggestion-quantity]'); qty.disabled = !input.checked; qty.name = input.checked && !input.disabled ? `suggestions[${input.value}]` : ''; }); if (all) all.checked = selectable.length > 0 && selected.length === selectable.length; if (status) status.textContent = selected.length ? `${selected.length} product${selected.length === 1 ? '' : 's'} selected. Quantities remain editable in the purchase form.` : 'Select products to create a purchase draft.'; if (create) create.disabled = selected.length === 0; };
    all?.addEventListener('change', () => { rows().filter(input => !input.disabled).forEach(input => input.checked = all.checked); sync(); }); rows().forEach(input => input.addEventListener('change', sync)); form.addEventListener('submit', event => { sync(); if (!rows().some(input => !input.disabled && input.checked)) event.preventDefault(); }); sync();
});
</script>
@endpush
