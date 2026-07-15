<form method="POST" action="{{ route('business.purchases.store') }}" class="tf-card p-4" data-purchase-form>
    @csrf
    <div class="row g-3 mb-4">
        <div class="col-md-4"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select js-select2" required autofocus><option value="">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->supplier_name }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Supplier Invoice / Reference</label><input name="supplier_invoice_number" value="{{ old('supplier_invoice_number') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Purchase date</label><input name="purchase_date" type="datetime-local" value="{{ old('purchase_date', now()->format('Y-m-d\\TH:i')) }}" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Discount amount</label><input name="discount_amount" type="number" min="0" step="0.01" value="{{ old('discount_amount', 0) }}" class="form-control" data-non-negative></div>
        <div class="col-md-6"><label class="form-label">Tax amount</label><input name="tax_amount" type="number" min="0" step="0.01" value="{{ old('tax_amount', 0) }}" class="form-control" data-non-negative></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
    </div>

    <section class="border rounded p-3 mb-3 bg-light" aria-label="Add purchase item">
        <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Add purchase item</h2><small class="tf-muted">Add one product at a time; the item list stays compact below.</small></div>
        <div class="row g-2 align-items-end">
            <div class="col-lg-3"><label class="form-label">Product</label><select class="form-select js-select2" data-purchase-entry-product><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-cost="{{ $product->purchase_cost ?: $product->wholesale_price }}" data-stock="{{ $product->stock_quantity }}" data-unit="{{ $product->unit }}">{{ $product->name }}</option>@endforeach</select></div>
            <div class="col-lg-1"><label class="form-label">Stock</label><input class="form-control" data-purchase-entry-stock readonly></div>
            <div class="col-lg-1"><label class="form-label">Qty</label><input type="number" min="1" value="1" class="form-control" data-purchase-entry-qty></div>
            <div class="col-lg-2"><label class="form-label">Unit Price</label><input type="number" min="0" step="0.01" class="form-control" data-purchase-entry-cost data-non-negative></div>
            <div class="col-lg-1"><label class="form-label">Discount</label><input class="form-control" value="0.00" readonly></div>
            <div class="col-lg-1"><label class="form-label">Tax</label><input class="form-control" value="0.00" readonly></div>
            <div class="col-lg-2"><label class="form-label">Line Total</label><input class="form-control" data-purchase-entry-total value="Rs 0.00" readonly></div>
            <div class="col-lg-1 d-grid"><button type="button" class="btn btn-tf-primary" data-add-purchase-item aria-label="Add item"><i class="bi bi-check-lg"></i></button></div>
        </div>
        <div class="invalid-feedback d-block d-none mt-2" data-purchase-entry-error></div>
    </section>

    <div class="table-responsive border rounded"><table class="table align-middle mb-0"><thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Discount</th><th>Tax</th><th>Line Total</th><th>Edit</th><th>Delete</th></tr></thead><tbody data-purchase-items><tr data-purchase-empty><td colspan="9" class="text-center tf-muted py-4">No purchase items added yet.</td></tr></tbody></table></div>
    <div class="d-flex justify-content-between align-items-center mt-3"><strong>Total <span data-purchase-total>Rs 0.00</span></strong><div class="d-flex gap-2"><a href="{{ route('business.purchases.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-tf-primary">Save purchase order</button></div></div>
</form>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-purchase-form]');
    if (!form || form.dataset.purchaseReady === '1') return;
    form.dataset.purchaseReady = '1';
    const body = form.querySelector('[data-purchase-items]'), product = form.querySelector('[data-purchase-entry-product]'), stock = form.querySelector('[data-purchase-entry-stock]'), qty = form.querySelector('[data-purchase-entry-qty]'), cost = form.querySelector('[data-purchase-entry-cost]'), total = form.querySelector('[data-purchase-entry-total]'), error = form.querySelector('[data-purchase-entry-error]');
    let editing = null;
    const money = value => `Rs ${Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    const line = () => Math.max(0, Number(qty.value) || 0) * Math.max(0, Number(cost.value) || 0);
    const rows = () => [...body.querySelectorAll('[data-purchase-row]')];
    const sync = () => { const option = product.selectedOptions[0]; stock.value = option?.value ? `${option.dataset.stock} ${option.dataset.unit || ''}` : ''; if (product.value && !cost.value) cost.value = option?.dataset.cost || 0; total.value = money(line()); };
    const render = () => { let sum = 0; rows().forEach((row, index) => { row.querySelector('[data-index]').textContent = index + 1; row.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`)); sum += Number(row.dataset.total || 0); }); body.querySelector('[data-purchase-empty]')?.classList.toggle('d-none', rows().length > 0); form.querySelector('[data-purchase-total]').textContent = money(sum); };
    const reset = () => { editing = null; product.value = ''; qty.value = 1; cost.value = ''; stock.value = ''; total.value = 'Rs 0.00'; error.classList.add('d-none'); window.syncTradeFlowTomSelect?.(product); setTimeout(() => window.getTradeFlowTomSelect?.(product)?.focus(), 0); };
    const add = () => { const option = product.selectedOptions[0], amount = line(); if (!product.value || Number(qty.value) < 1 || Number(cost.value) < 0) { error.textContent = 'Quantity must be at least 1. Negative values are not allowed.'; error.classList.remove('d-none'); return; } const row = editing || document.createElement('tr'); row.dataset.purchaseRow = ''; row.dataset.total = amount; row.innerHTML = `<td data-index></td><td><span>${option.text}</span><input type="hidden" name="items[0][product_id]" value="${product.value}"></td><td>${qty.value}<input type="hidden" name="items[0][quantity]" value="${qty.value}"></td><td>${money(cost.value)}<input type="hidden" name="items[0][unit_cost]" value="${cost.value}"></td><td>Rs 0.00</td><td>Rs 0.00</td><td>${money(amount)}</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-edit-purchase-item>Edit</button></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-delete-purchase-item>Delete</button></td>`; if (!editing) body.append(row); render(); reset(); };
    product.addEventListener('change', sync); qty.addEventListener('input', sync); cost.addEventListener('input', sync); form.querySelector('[data-add-purchase-item]').addEventListener('click', add);
    body.addEventListener('click', event => { const row = event.target.closest('[data-purchase-row]'); if (!row) return; if (event.target.closest('[data-delete-purchase-item]')) { if (editing === row) reset(); row.remove(); render(); return; } if (event.target.closest('[data-edit-purchase-item]')) { editing = row; product.value = row.querySelector('[name$="[product_id]"]').value; qty.value = row.querySelector('[name$="[quantity]"]').value; cost.value = row.querySelector('[name$="[unit_cost]"]').value; window.syncTradeFlowTomSelect?.(product); sync(); window.getTradeFlowTomSelect?.(product)?.focus(); } });
    form.addEventListener('submit', event => { if (rows().length) return; event.preventDefault(); error.textContent = 'Add at least one purchase item before saving.'; error.classList.remove('d-none'); window.getTradeFlowTomSelect?.(product)?.focus(); });
    sync(); render();
});
</script>
@endpush
@endonce
