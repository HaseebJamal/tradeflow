@extends('layouts.dashboard')

@section('page-title', 'Stock Count '.$stockCount->reference)
@section('page-subtitle', 'Capture physical quantities against the fixed system-stock snapshot')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><strong>Review this count before continuing.</strong><div class="small mt-1">{{ $errors->first() }}</div></div>@endif
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div><a href="{{ route('business.inventory.stock-counts.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Stock Counts</a></div>
    <div class="d-flex flex-wrap gap-2">
        @if($stockCount->status === 'Draft')<form method="POST" action="{{ route('business.inventory.stock-counts.start', $stockCount) }}">@csrf @method('PATCH')<button class="btn btn-outline-primary">Start Count</button></form>@endif
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelStockCountModal">Cancel Count</button>
    </div>
</div>

<div class="tf-card p-3 mb-3 d-flex flex-wrap justify-content-between gap-3">
    <div><div class="text-uppercase small text-primary fw-semibold">{{ $stockCount->status }}</div><h2 class="h5 mb-1">{{ $stockCount->reference }}</h2><div class="small tf-muted">Created by {{ $stockCount->creator?->name ?? 'System' }} · System quantities are snapshots taken when each product was added.</div></div>
    <div class="text-md-end"><div class="small tf-muted">Products counted</div><strong>{{ $stockCount->items->count() }}</strong></div>
</div>

<div class="tf-card p-3 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><div><h2 class="h6 mb-1">Add product</h2><p class="small tf-muted mb-0">Search by product name, barcode, or SKU. Duplicates are prevented.</p></div></div>
    <form method="POST" action="{{ route('business.inventory.stock-counts.items.store', $stockCount) }}" class="row g-2 align-items-end">@csrf
        <div class="col-lg-5"><label class="form-label">Product / barcode / SKU</label><select class="form-select" name="product_id" required><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-category="{{ $product->category_id }}" data-unit="{{ $product->unit }}">{{ $product->name }}@if($product->barcode) · {{ $product->barcode }}@endif @if($product->sku) · SKU {{ $product->sku }}@endif</option>@endforeach</select></div>
        <div class="col-lg-2"><label class="form-label">Category filter</label><select class="form-select" data-stock-count-category-filter><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
        <div class="col-lg-2"><label class="form-label">Unit filter</label><select class="form-select" data-stock-count-unit-filter><option value="">All units</option>@foreach($units as $unit)<option value="{{ $unit }}">{{ $unit }}</option>@endforeach</select></div>
        <div class="col-lg-2"><label class="form-label d-none d-lg-block">&nbsp;</label><button class="btn btn-outline-primary w-100"><i class="bi bi-plus-lg me-1"></i>Add</button></div>
    </form>
</div>

<form method="POST" action="{{ route('business.inventory.stock-counts.update', $stockCount) }}" data-stock-count-form>@csrf @method('PATCH')
    <div class="tf-card p-3 mb-3">
        <div class="row g-2"><div class="col-md-4"><label class="form-label">Count date &amp; time</label><input class="form-control" type="datetime-local" name="counted_at" value="{{ old('counted_at', $stockCount->counted_at?->format('Y-m-d\\TH:i')) }}" required></div><div class="col-md-8"><label class="form-label">Session notes</label><input class="form-control" name="notes" value="{{ old('notes', $stockCount->notes) }}" maxlength="2000" placeholder="Optional count notes"></div></div>
    </div>
    <div class="tf-card p-0 mb-3 overflow-hidden">
        <div class="p-3 border-bottom d-flex justify-content-between align-items-center"><div><h2 class="h6 mb-1">Count lines</h2><p class="small tf-muted mb-0">No inventory is changed while this session is saved as a draft.</p></div><span class="small tf-muted">{{ $stockCount->items->count() }} products</span></div>
        <div class="table-responsive"><table class="table align-middle mb-0 tf-stock-count-table"><thead><tr><th>Product</th><th>Category</th><th>Unit</th><th>System Qty</th><th>Physical Qty</th><th>Variance</th><th>Reason</th><th>Notes</th></tr></thead><tbody>
            @forelse($stockCount->items as $item)
                @php($variance = old('items.'.$item->id.'.physical_quantity', $item->physical_quantity) !== null && old('items.'.$item->id.'.physical_quantity', $item->physical_quantity) !== '' ? (float) old('items.'.$item->id.'.physical_quantity', $item->physical_quantity) - (float) $item->system_quantity : null)
                <tr class="{{ $item->review_required ? 'table-warning' : '' }}">
                    <td><input type="hidden" name="items[{{ $item->id }}][id]" value="{{ $item->id }}"><strong>{{ $item->product?->name ?? 'Deleted product' }}</strong>@if($item->review_required)<div class="small text-warning-emphasis mt-1">Review required: snapshot {{ rtrim(rtrim(number_format($item->system_quantity, 3, '.', ''), '0'), '.') }}, current {{ rtrim(rtrim(number_format($item->current_system_quantity, 3, '.', ''), '0'), '.') }}</div>@endif</td><td>{{ $item->product?->category?->name ?? '—' }}</td><td>{{ $item->product?->unit ?? '—' }}</td><td class="text-nowrap" data-system-qty="{{ $item->system_quantity }}">{{ rtrim(rtrim(number_format($item->system_quantity, 3, '.', ''), '0'), '.') }}</td>
                    <td><input class="form-control form-control-sm" type="number" min="0" step="0.001" inputmode="decimal" name="items[{{ $item->id }}][physical_quantity]" value="{{ old('items.'.$item->id.'.physical_quantity', $item->physical_quantity) }}" data-stock-count-physical aria-label="Physical quantity for {{ $item->product?->name }}"></td>
                    <td class="text-nowrap" data-stock-count-variance>{{ $variance === null ? '—' : rtrim(rtrim(number_format($variance, 3, '.', ''), '0'), '.') }}</td>
                    <td><select class="form-select form-select-sm" name="items[{{ $item->id }}][reason]" data-stock-count-reason><option value="">Select reason</option>@foreach($reasons as $reason)<option value="{{ $reason }}" @selected(old('items.'.$item->id.'.reason', $item->reason) === $reason)>{{ $reason }}</option>@endforeach</select></td>
                    <td><input class="form-control form-control-sm" name="items[{{ $item->id }}][notes]" value="{{ old('items.'.$item->id.'.notes', $item->notes) }}" maxlength="500" placeholder="Required for Other"></td>
                </tr>
            @empty<tr><td colspan="8" class="text-center tf-muted py-4">Add products to begin this stock count.</td></tr>@endforelse
        </tbody></table></div>
    </div>
    <div class="d-flex flex-wrap justify-content-between gap-2"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-save me-1"></i>Save Draft</button>@if($stockCount->items->isNotEmpty())<button class="btn btn-tf-primary" type="button" data-bs-toggle="modal" data-bs-target="#finalizeStockCountModal"><i class="bi bi-check2-circle me-1"></i>Finalize Stock Count</button>@endif</div>
</form>

<div class="modal fade" id="finalizeStockCountModal" tabindex="-1" aria-labelledby="finalizeStockCountModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('business.inventory.stock-counts.finalize', $stockCount) }}">@csrf
    @php($matched = $stockCount->items->filter(fn($item) => $item->physical_quantity !== null && abs((float)$item->variance) < .0005)->count()) @php($shortage = $stockCount->items->filter(fn($item) => (float)$item->variance < 0)->count()) @php($excess = $stockCount->items->filter(fn($item) => (float)$item->variance > 0)->count())
    <div class="modal-header"><h3 class="modal-title h5" id="finalizeStockCountModalTitle">Finalize {{ $stockCount->reference }}?</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><p class="tf-muted">Finalization creates auditable stock movements only for differences. It cannot be undone by editing this count.</p><div class="row text-center g-2"><div class="col-3"><div class="border rounded p-2 small">Counted<strong class="d-block">{{ $stockCount->items->count() }}</strong></div></div><div class="col-3"><div class="border rounded p-2 small">Matched<strong class="d-block">{{ $matched }}</strong></div></div><div class="col-3"><div class="border rounded p-2 small">Shortage<strong class="d-block text-danger">{{ $shortage }}</strong></div></div><div class="col-3"><div class="border rounded p-2 small">Excess<strong class="d-block text-primary">{{ $excess }}</strong></div></div></div>@if($stockCount->items->contains('review_required', true))<label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="confirm_conflicts" value="1" required><span class="form-check-label">I reviewed stock changes made during this count and want to reconcile against the current stock.</span></label>@endif</div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tf-primary">Finalize</button></div>
</form></div></div>
<div class="modal fade" id="cancelStockCountModal" tabindex="-1" aria-labelledby="cancelStockCountModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('business.inventory.stock-counts.cancel', $stockCount) }}">@csrf @method('PATCH')<div class="modal-header"><h3 class="modal-title h5" id="cancelStockCountModalTitle">Cancel stock count?</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body">This preserves {{ $stockCount->reference }} for audit history and does not change inventory.</div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Keep Count</button><button class="btn btn-outline-danger">Cancel Count</button></div></form></div></div>
@endsection

@push('scripts')
<script src="{{ asset('js/stock-count.js') }}?v={{ filemtime(public_path('js/stock-count.js')) }}"></script>
@endpush
