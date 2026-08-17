@extends('layouts.dashboard')
@section('page-title', 'Reorder Settings')
@section('page-subtitle', 'Configure the shared low-stock reorder trigger and each product target stock level')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-1">Product replenishment levels</h2><p class="tf-muted mb-0">Reorder Level is the existing Low Stock Alert source. Target Stock must be equal to or higher than it.</p></div><a href="{{ route('business.inventory.purchase-suggestions.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-left me-1"></i>Purchase Suggestions</a></div>
<form method="GET" class="tf-card p-3 mb-3"><div class="row g-2 align-items-end"><div class="col-md-5"><label class="form-label">Search product</label><input name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Product name"></div><div class="col-md-3"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>{{ $category->name }}</option>@endforeach</select></div><div class="col-md-3 d-flex gap-2"><button class="btn btn-tf-primary flex-grow-1">Filter</button><a class="btn btn-outline-primary" href="{{ route('business.inventory.purchase-suggestions.settings') }}">Clear</a></div></div></form>
<form method="POST" action="{{ route('business.inventory.purchase-suggestions.settings.update') }}">@csrf @method('PATCH')
    <div class="tf-card overflow-hidden"><div class="table-responsive"><table class="table align-middle mb-0 tf-business-data-table"><thead><tr><th>Product</th><th>Category</th><th>Unit</th><th class="text-end">Current Stock</th><th>Reorder Level</th><th>Target Stock</th></tr></thead><tbody>
        @forelse($products as $index => $product)<tr><td><strong>{{ $product->name }}</strong><input type="hidden" name="products[{{ $index }}][id]" value="{{ $product->id }}"></td><td>{{ $product->category?->name ?? '—' }}</td><td>{{ $product->unitRecord?->short_code ?: ($product->unit ?: '—') }}</td><td class="text-end"><x-quantity :value="$product->stock_quantity" /></td><td><input name="products[{{ $index }}][reorder_level]" type="number" min="0" step="1" value="{{ (int) $product->low_stock_alert_qty }}" class="form-control form-control-sm js-whole-number" required></td><td><input name="products[{{ $index }}][target_stock_level]" type="number" min="0" step="1" value="{{ (int) $product->target_stock_level }}" class="form-control form-control-sm js-whole-number" required></td></tr>
        @empty<tr><td colspan="6" class="text-center tf-muted py-5">No active products match these filters.</td></tr>@endforelse
    </tbody></table></div><div class="d-flex justify-content-end p-3 border-top"><button class="btn btn-tf-primary" @disabled($products->isEmpty())>Save Settings</button></div></div>
</form>
<div class="mt-3"><x-table-result-summary :paginator="$products" />{{ $products->links('pagination::bootstrap-5') }}</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[action="{{ route('business.inventory.purchase-suggestions.settings.update') }}"]'); if (!form) return;
    const validateRow = row => { const reorder = row.querySelector('[name$="[reorder_level]"]'); const target = row.querySelector('[name$="[target_stock_level]"]'); const valid = Number(target.value || 0) >= Number(reorder.value || 0); target.setCustomValidity(valid ? '' : 'Target stock must be greater than or equal to reorder level.'); return valid; };
    form.querySelectorAll('tbody tr').forEach(row => row.querySelectorAll('input[type="number"]').forEach(input => input.addEventListener('input', () => validateRow(row))));
    form.addEventListener('submit', event => { const valid = [...form.querySelectorAll('tbody tr')].every(validateRow); if (!valid) { event.preventDefault(); form.querySelector(':invalid')?.reportValidity(); } });
});
</script>
@endpush
