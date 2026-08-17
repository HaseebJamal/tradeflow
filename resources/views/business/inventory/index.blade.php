@extends('layouts.dashboard')
@section('page-title', 'Inventory')
@section('page-subtitle', 'Stock table and low stock alerts')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-3 mb-4">
    @forelse($lowStockProducts ?? [] as $product)
    <div class="col-md-4"><div class="tf-card p-3 border-danger"><i class="bi bi-exclamation-triangle text-danger me-2"></i>{{ $product->name }} - <x-quantity :value="$product->inventory_available" /> sellable. Alert at <x-quantity :value="$product->low_stock_alert_qty" />.</div></div>
    @empty
    <div class="col-12"><div class="tf-card p-3">No low stock alerts.</div></div>
    @endforelse
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="h5 mb-1">Inventory Control</h2><p class="tf-muted mb-0">Manage sellable stock and stock movement history.</p></div>
    <div class="d-flex flex-wrap gap-2"><a href="{{ route('business.inventory.history') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-clipboard-data me-1"></i>Stock History</a><a href="{{ route('business.inventory.purchase-suggestions.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-cart-plus me-1"></i>Purchase Suggestions</a><a href="{{ route('business.inventory.batches.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-seam me-1"></i>Batches & Expiry</a>@companyCan('inventory.adjust_stock')<a href="{{ route('business.inventory.stock-counts.index') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-clipboard-check me-1"></i>Stock Count</a>@endcompanyCan @companyCan('products.create')<button type="button" class="btn btn-sm btn-tf-primary" data-bs-toggle="modal" data-bs-target="#inventoryProductCreateModal"><i class="bi bi-plus-lg me-1"></i>Add Product</button>@endcompanyCan</div>
</div>
@companyCan('inventory.adjust_stock')<div class="tf-card tf-inventory-adjustment-card p-4 mb-4">
    <h2 class="h5">Stock Adjustment</h2>
    <form method="POST" action="{{ route('business.inventory.adjust') }}" class="row g-3" data-inventory-product-form>@csrf
        <div class="col-md-4"><label class="form-label">Product</label><select name="product_id" class="form-select" required><option value="">Select Product</option>@foreach($inventoryProducts ?? collect() as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Adjustment Type</label><select name="type" class="form-select"><option value="added">Add Stock</option><option value="reduced">Reduce Stock</option><option value="adjustment">Set Stock Qty</option><option value="returned">Returned</option><option value="damaged">Damaged</option></select></div>
        <div class="col-md-2"><label class="form-label">Quantity</label><input name="quantity" type="number" min="1" step="1" value="0" class="form-control js-whole-number" placeholder="Qty" required></div>
        <div class="col-md-2"><label class="form-label">Reason</label><input name="reason" type="text" maxlength="255" value="{{ old('reason') }}" class="form-control" placeholder="Why this adjustment?" required></div>
        <div class="col-md-2"><label class="form-label d-none d-md-block">&nbsp;</label><button class="btn btn-tf-primary w-100">Apply</button></div>
    </form>
</div>@endcompanyCan
<x-table class="tf-business-data-table">
    <thead><tr><th>Product</th><th title="Current sellable stock">Available <i class="bi bi-info-circle" aria-hidden="true"></i></th><th title="Gross completed sale quantity">Sold <i class="bi bi-info-circle" aria-hidden="true"></i></th><th title="Non-sellable damaged stock retained by the business">Damaged <i class="bi bi-info-circle" aria-hidden="true"></i></th><th title="Quantity returned by customers">Sales Returned <i class="bi bi-info-circle" aria-hidden="true"></i></th><th title="Quantity returned to suppliers">Purchase Returned <i class="bi bi-info-circle" aria-hidden="true"></i></th><th title="Expired physical batch stock, excluded from Available">Expired <i class="bi bi-info-circle" aria-hidden="true"></i></th><th>Alert Qty</th><th>Last Updated</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($inventories ?? [] as $inventory)
        @php($summary = $inventory->inventory_summary)
        <tr>
            <td>{{ $inventory->product?->name }}</td><td><x-quantity :value="$summary['available']" /></td><td><x-quantity :value="$summary['sold']" /></td><td><x-quantity :value="$summary['damaged']" /></td><td><x-quantity :value="$summary['sales_returned']" /></td><td><x-quantity :value="$summary['purchase_returned']" /></td><td><x-quantity :value="$summary['expired']" /></td><td><x-quantity :value="$summary['alert_qty']" /></td><td><x-date-time :value="$inventory->updated_at" /></td>
            <td>
                @if($inventory->product?->has_batch_tracking)
                    <a href="{{ route('business.inventory.batches.index', ['product_id' => $inventory->product->id]) }}" class="btn btn-sm btn-outline-primary mb-1">View Batches</a>
                @endif
                @companyCan('inventory.low_stock_alerts')
                    @if($inventory->product)
                        <div class="d-flex align-items-center gap-2 tf-inventory-row-actions">
                        <form method="POST" action="{{ route('business.products.low-stock-alert', $inventory->product) }}" class="d-flex gap-2">
                            @csrf
                            @method('PATCH')
                            <input name="low_stock_alert_qty" type="number" min="0" step="1" value="{{ $inventory->product->low_stock_alert_qty ?? 10 }}" class="form-control form-control-sm js-whole-number" style="max-width:90px">
                            <button class="btn btn-sm btn-outline-primary">Save</button>
                        </form>
                            @if(app(\App\Services\CompanyPermissionService::class)->allowsUser(auth()->user(), 'products.edit') || app(\App\Services\CompanyPermissionService::class)->allowsUser(auth()->user(), 'products.delete'))
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More inventory actions"><i class="bi bi-three-dots"></i></button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        @companyCan('products.edit')<a href="{{ route('business.products.edit', $inventory->product) }}" class="dropdown-item"><i class="bi bi-pencil me-2"></i>Edit product</a>@endcompanyCan
                                        @companyCan('products.delete')<div class="dropdown-divider"></div><form method="POST" action="{{ route('business.products.destroy', $inventory->product) }}" data-tf-confirm-message="Delete or archive this product?" data-tf-confirm-title="Delete product?" data-tf-confirm-button="Delete product" data-tf-confirm-color="#dc3545">@csrf @method('DELETE')<button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-2"></i>Delete product</button></form>@endcompanyCan
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                @endcompanyCan
            </td>
        </tr>
    @empty
        <tr><td colspan="10" class="text-center tf-muted py-4">No inventory records.</td></tr>
    @endforelse
    </tbody>
</x-table>
<div class="mt-3"><x-table-result-summary :paginator="$inventories" />{{ $inventories->links('pagination::bootstrap-5') }}</div>
@companyCan('products.create')
<div class="modal fade" id="inventoryProductCreateModal" tabindex="-1" aria-hidden="true" aria-labelledby="inventoryProductCreateModalTitle">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="{{ route('business.products.store') }}" enctype="multipart/form-data" class="tf-inventory-product-create-form" data-inline-products-form data-product-create-async="true" data-inline-category-url="{{ route('business.categories.store') }}" data-inline-unit-url="{{ route('business.units.store') }}">
            @csrf
            <div class="modal-header"><h2 class="modal-title h5" id="inventoryProductCreateModalTitle">Add Products</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="alert alert-danger d-none" data-product-create-errors role="alert"></div>
                @php($draftProducts = [[]])
                @include('business.products._multi-create-fields', [
                    'categories' => $categories,
                    'units' => $units,
                    'hideProductFormActions' => true,
                    'compactCatalogDropdowns' => true,
                ])
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-primary" data-add-product-section>+ Add Product</button><button type="submit" class="btn btn-tf-primary" data-save-products>Save Products</button></div></div>
        </form>
    </div></div>
</div>
@include('business.products._inline-catalog-modals')
@endcompanyCan
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-inventory-product-form]').forEach(function (form) {
        if (form.dataset.productValidationReady === '1') return;
        form.dataset.productValidationReady = '1';
        form.addEventListener('submit', function (event) {
            const product = form.querySelector('[name="product_id"]');
            if (product?.value) return;
            event.preventDefault();
            if (window.Swal) {
                window.Swal.fire({ icon: 'warning', title: 'Please select a product.', confirmButtonText: 'OK' })
                    .then(function () { product?.focus(); });
                return;
            }
            product?.setCustomValidity('Please select a product.');
            product?.reportValidity();
            product?.setCustomValidity('');
        });
    });
});
</script>
@companyCan('products.create')
<script src="{{ asset('js/product-create-form.js') }}?v={{ filemtime(public_path('js/product-create-form.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('inventoryProductCreateModal');
    if (!modal) return;
    window.initTradeFlowProductCreateForm?.(modal);
    modal.addEventListener('shown.bs.modal', function () {
        modal.querySelector('[data-product-field="product_name"]')?.focus();
    });
    window.addEventListener('tradeflow:products-created', function (event) {
        (event.detail || []).forEach(function (product) {
            document.querySelectorAll('[data-inventory-product-form] [name="product_id"]').forEach(function (select) {
                if ([...select.options].some((option) => String(option.value) === String(product.id))) return;
                const control = window.getTradeFlowTomSelect?.(select);
                if (control) {
                    control.addOption({ value: String(product.id), text: product.name });
                    control.refreshOptions(false);
                }
                else select.add(new Option(product.name, product.id));
            });
        });
    });
});
</script>
@endcompanyCan
@endpush
