@extends('layouts.dashboard')
@section('page-title', 'Inventory')
@section('page-subtitle', 'Stock table and low stock alerts')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-3 mb-4">
    @forelse($lowStockProducts ?? [] as $product)
    <div class="col-md-4"><div class="tf-card p-3 border-danger"><i class="bi bi-exclamation-triangle text-danger me-2"></i>{{ $product->name }} - <x-quantity :value="$product->stock_quantity" /> left. Alert at <x-quantity :value="$product->low_stock_alert_qty" />.</div></div>
    @empty
    <div class="col-12"><div class="tf-card p-3">No low stock alerts.</div></div>
    @endforelse
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="h5 mb-1">Inventory Control</h2><p class="tf-muted mb-0">Manage available stock and stock movement history.</p></div>
    @companyCan('products.create')<button type="button" class="btn btn-sm btn-tf-primary" data-bs-toggle="modal" data-bs-target="#inventoryProductCreateModal"><i class="bi bi-plus-lg me-1"></i>Add Product</button>@endcompanyCan
</div>
@companyCan('inventory.adjust_stock')<div class="tf-card p-4 mb-4">
    <h2 class="h5">Stock Adjustment</h2>
    <form method="POST" action="{{ route('business.inventory.adjust') }}" class="row g-3" data-inventory-product-form>@csrf
        <div class="col-md-4"><select name="product_id" class="form-select" required><option value="">Select Product</option>@foreach(($inventories ?? collect())->pluck('product')->filter() as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="type" class="form-select"><option value="added">Add Stock</option><option value="reduced">Reduce Stock</option><option value="adjustment">Set Stock Qty</option><option value="returned">Returned</option><option value="damaged">Damaged</option></select></div>
        <div class="col-md-2"><input name="quantity" type="number" min="1" step="1" value="0" class="form-control js-whole-number" placeholder="Qty"></div>
        <div class="col-md-1"><button class="btn btn-tf-primary w-100"><i class="bi bi-check-lg"></i></button></div>
    </form>
</div>@endcompanyCan
@companyCan('inventory.stock_transfer')<div class="tf-card p-4 mb-4">
    <h2 class="h5">Stock Transfer</h2>
    <form method="POST" action="{{ route('business.inventory.transfer') }}" class="row g-3" data-inventory-product-form>@csrf
        <div class="col-md-4"><select name="product_id" class="form-select" required><option value="">Select Product</option>@foreach(($inventories ?? collect())->pluck('product')->filter() as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><input name="quantity" type="number" min="1" step="1" value="0" class="form-control js-whole-number" placeholder="Qty" required></div>
        <div class="col-md-1"><button class="btn btn-outline-primary w-100"><i class="bi bi-arrow-left-right"></i></button></div>
    </form>
</div>@endcompanyCan
<x-table>
    <thead><tr><th>Product</th><th>Available</th><th>Sold</th><th>Damaged</th><th>Sales Returned</th><th>Purchase Returned</th><th>Alert Qty</th><th>Last Updated</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($inventories ?? [] as $inventory)
        <tr>
            <td>{{ $inventory->product?->name }}</td><td><x-quantity :value="$inventory->product?->stock_quantity ?? $inventory->available_stock" /></td><td><x-quantity :value="$inventory->sold_stock" /></td><td><x-quantity :value="$inventory->damaged_stock" /></td><td><x-quantity :value="$inventory->sales_returned_stock ?? 0" /></td><td><x-quantity :value="$inventory->purchase_returned_stock ?? 0" /></td><td><x-quantity :value="$inventory->product?->low_stock_alert_qty ?? $inventory->low_stock_alert" /></td><td><x-date-time :value="$inventory->updated_at" /></td>
            <td>
                @companyCan('inventory.low_stock_alerts')
                    @if($inventory->product)
                        <form method="POST" action="{{ route('business.products.low-stock-alert', $inventory->product) }}" class="d-flex gap-2">
                            @csrf
                            @method('PATCH')
                            <input name="low_stock_alert_qty" type="number" min="0" step="1" value="{{ $inventory->product->low_stock_alert_qty ?? 10 }}" class="form-control form-control-sm js-whole-number" style="max-width:90px">
                            <button class="btn btn-sm btn-outline-primary">Save</button>
                        </form>
                    @endif
                @endcompanyCan
                @if($inventory->product)
                    <div class="d-flex gap-1 mt-2">
                        @companyCan('products.edit')<a href="{{ route('business.products.edit', $inventory->product) }}" class="btn btn-sm btn-outline-secondary">Edit</a>@endcompanyCan
                        @companyCan('products.delete')<form method="POST" action="{{ route('business.products.destroy', $inventory->product) }}" onsubmit="return confirm('Delete or archive this product?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endcompanyCan
                    </div>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="9" class="text-center tf-muted py-4">No inventory records.</td></tr>
    @endforelse
    </tbody>
</x-table>
<div class="tf-card p-4 mt-4"><h2 class="h5">Stock History</h2><x-table><thead><tr><th>Date &amp; Time</th><th>Product</th><th>Movement Type</th><th>Stock Before</th><th>Quantity</th><th>Operation</th><th>Stock After</th><th>Reference</th><th>User</th></tr></thead><tbody>@forelse($movements ?? [] as $move)@php($isReturn = in_array($move->type, ['PURCHASE_RETURN', 'SALES_RETURN'], true))@php($operation = $move->type === 'PURCHASE_RETURN' ? '-' : ($move->type === 'SALES_RETURN' ? '+' : '—'))<tr><td><x-date-time :value="$move->movement_date ?? $move->created_at" /></td><td>{{ $move->product?->name ?? 'Deleted Product' }}</td><td>{{ $move->type === 'PURCHASE_RETURN' ? 'Purchase Return' : ($move->type === 'SALES_RETURN' ? 'Sales Return' : str_replace('_', ' ', $move->type)) }}</td><td><x-quantity :value="$move->previous_stock" /></td><td><x-quantity :value="abs((float) $move->quantity)" /></td><td>{{ $operation }}</td><td><x-quantity :value="$move->new_stock" /></td><td>{{ $isReturn ? $move->note : '—' }}</td><td>{{ $move->creator?->name ?? 'System' }}</td></tr>@empty<tr><td colspan="9" class="text-center tf-muted py-4">No stock history.</td></tr>@endforelse</tbody></x-table></div>
@companyCan('products.create')
<div class="modal fade" id="inventoryProductCreateModal" tabindex="-1" aria-hidden="true" aria-labelledby="inventoryProductCreateModalTitle">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="{{ route('business.products.store') }}" enctype="multipart/form-data" class="tf-inventory-product-create-form" data-inline-products-form data-product-create-async="true" data-inline-category-url="{{ route('business.categories.store') }}" data-inline-unit-url="{{ route('business.units.store') }}">
            @csrf
            <div class="modal-header"><h2 class="modal-title h5" id="inventoryProductCreateModalTitle">Add Products</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="alert alert-danger d-none" data-product-create-errors role="alert"></div>
                @php($draftProducts = [[]])
                @include('business.products._multi-create-fields', ['hideProductFormActions' => true])
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-primary" data-add-product-section>+ Add Another Product</button><button class="btn btn-tf-primary" data-save-products @disabled(($categories ?? collect())->isEmpty() || ($units ?? collect())->isEmpty())>Save Products</button></div></div>
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
