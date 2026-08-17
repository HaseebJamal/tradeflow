<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="h5 mb-1">Purchase Return: {{ $purchase->purchase_number }}</h2><p class="tf-muted mb-0">Returning goods reduces stock and reverses the related payable or refund accounting entry.</p></div>
    <a href="{{ route('business.purchase-returns.create') }}" class="btn btn-outline-secondary">Choose another purchase</a>
</div>
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first('items') ?: $errors->first('return') ?: $errors->first() }}</div>
@endif
<form method="POST" action="{{ route('business.purchases.return', $purchase) }}" class="tf-card p-4" data-purchase-return-form>
    @csrf
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-1">Return items</h2><small class="tf-muted">Supplier: {{ $purchase->supplier?->supplier_name }}</small></div><small class="tf-muted" data-purchase-return-mode>New item</small></div>
    <section class="border rounded bg-light p-3 mb-3"><div class="row g-2 align-items-end"><div class="col-lg-3"><label class="form-label">Product</label><select class="form-select" data-purchase-return-product><option value="">Select received product</option>@foreach($purchase->items as $item)@php($returned = $purchase->returns->sum(fn ($return) => $return->items->where('purchase_item_id', $item->id)->sum('quantity')))@php($freeReturned = $purchase->returns->sum(fn ($return) => $return->items->where('purchase_item_id', $item->id)->sum('free_quantity')))@php($returnable = max(0, $item->received_quantity - $returned))@php($available = min($returnable, max(0, $item->product?->stock_quantity ?? 0)))@php($freeAvailable = max(0, $item->goodsReceiptItems->sum('free_accepted_quantity') - $freeReturned))@continue($available < 1)<option value="{{ $item->id }}" data-stock="{{ $available }}" data-free="{{ $freeAvailable }}" data-unit="{{ $item->unit_snapshot ?: $item->product?->unit }}" data-price="{{ $item->unit_cost }}">{{ $item->product_name_snapshot }}</option>@endforeach</select></div><div class="col-lg-1"><label class="form-label">Available Stock</label><input class="form-control" readonly data-purchase-return-stock></div><div class="col-lg-1"><label class="form-label">Quantity</label><input type="number" min="0" step="1" value="0" class="form-control js-no-number-spinner js-no-wheel-change js-whole-number" data-purchase-return-qty></div><div class="col-lg-1"><label class="form-label">Free Qty</label><input type="number" min="0" step="1" value="0" class="form-control js-no-number-spinner js-no-wheel-change js-whole-number" data-purchase-return-free-qty></div><div class="col-lg-1"><label class="form-label">Unit Price</label><input class="form-control" readonly data-purchase-return-price></div><div class="col-lg-1"><label class="form-label">Discount</label><input class="form-control" value="Rs 0" readonly></div><div class="col-lg-1"><label class="form-label">Tax</label><input class="form-control" value="Rs 0" readonly></div><div class="col-lg-2"><label class="form-label">Line Total</label><input class="form-control" value="Rs 0" readonly data-purchase-return-total></div><div class="col-lg-1 d-grid"><button type="button" class="btn btn-tf-primary" data-purchase-return-add aria-label="Add or update return item"><i class="bi bi-check-lg"></i></button></div></div><div class="form-text">Free quantity is returned to stock with a Rs 0 supplier credit.</div><div class="invalid-feedback d-block d-none mt-2" data-purchase-return-error></div></section>
    <div class="table-responsive border rounded" style="max-height:360px"><table class="table align-middle mb-0"><thead class="sticky-top"><tr><th>#</th><th>Product</th><th>Qty</th><th>Free Qty</th><th>Unit Price</th><th>Discount</th><th>Tax</th><th>Line Total</th><th>Edit</th><th>Delete</th></tr></thead><tbody data-purchase-return-items><tr data-purchase-return-empty><td colspan="10" class="text-center tf-muted py-4">No purchase return items added yet.</td></tr></tbody></table></div>
    <div class="row g-3 mt-3"><div class="col-md-9"><label class="form-label">Return Reason</label><input name="reason" class="form-control" value="{{ old('reason') }}" required></div><div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn btn-outline-warning w-100" data-purchase-return-submit>Process Return</button></div></div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-purchase-return-form]');
    if (!form || form.dataset.initialized) return;
    form.dataset.initialized = '1';
    const body = form.querySelector('[data-purchase-return-items]'), product = form.querySelector('[data-purchase-return-product]'), stock = form.querySelector('[data-purchase-return-stock]'), qty = form.querySelector('[data-purchase-return-qty]'), freeQty = form.querySelector('[data-purchase-return-free-qty]'), price = form.querySelector('[data-purchase-return-price]'), total = form.querySelector('[data-purchase-return-total]'), error = form.querySelector('[data-purchase-return-error]'), mode = form.querySelector('[data-purchase-return-mode]'), add = form.querySelector('[data-purchase-return-add]');
    let editing = null, entryTouched = false;
    const money = value => `Rs ${Math.round(Number(value || 0)).toLocaleString()}`;
    const quantityLabel = value => String(Math.max(0, Math.round(Number(value || 0))));
    const rows = () => [...body.querySelectorAll('[data-purchase-return-row]')];
    const selected = () => product.selectedOptions[0];
    const validateEntry = (showErrors = entryTouched) => { const option = selected(), available = Math.floor(Number(option?.dataset.stock || 0)), freeAvailable = Math.floor(Number(option?.dataset.free || 0)), quantity = Number(qty.value || 0), free = Number(freeQty.value || 0); qty.toggleAttribute('max', !!option?.value); freeQty.toggleAttribute('max', !!option?.value); if (option?.value) { qty.max = String(available); freeQty.max = String(Math.min(available, freeAvailable)); } const message = !option?.value ? '' : (quantity < 1 ? 'Quantity must be at least 1.' : (quantity > available ? `Return quantity cannot exceed available stock. Only ${quantityLabel(available)} units are available.` : (free < 0 ? 'Free quantity cannot be negative.' : (free > quantity ? 'Free quantity cannot exceed the total return quantity.' : (free > freeAvailable ? `Only ${quantityLabel(freeAvailable)} free units are available to return.` : ''))))); qty.classList.toggle('is-invalid', showErrors && !!message); freeQty.classList.toggle('is-invalid', showErrors && !!message); add.disabled = !option?.value || !!message; error.textContent = message; error.classList.toggle('d-none', !showErrors || !message); return !message; };
    const sync = () => { const option = selected(), quantity = Number(qty.value || 0), free = Number(freeQty.value || 0); stock.value = option?.value ? `${quantityLabel(option.dataset.stock)} ${option.dataset.unit || ''}` : ''; price.value = option?.value ? option.dataset.price || 0 : ''; total.value = money(Math.max(0, quantity - free) * Number(price.value || 0)); validateEntry(); };
    const render = () => { rows().forEach((row, index) => { row.querySelector('[data-purchase-return-index]').textContent = index + 1; row.querySelectorAll('[data-purchase-return-field]').forEach(field => field.name = `items[${index}][${field.dataset.purchaseReturnField}]`); const quantity = Number(row.querySelector('[data-purchase-return-field="quantity"]').value || 0), free = Number(row.querySelector('[data-purchase-return-field="free_quantity"]').value || 0); row.querySelector('[data-purchase-return-line-total]').textContent = money(Math.max(0, quantity - free) * Number(row.dataset.price || 0)); }); body.querySelector('[data-purchase-return-empty]')?.classList.toggle('d-none', rows().length > 0); };
    const reset = () => { editing = null; entryTouched = false; product.value = ''; qty.value = 0; freeQty.value = 0; qty.removeAttribute('max'); freeQty.removeAttribute('max'); qty.classList.remove('is-invalid'); freeQty.classList.remove('is-invalid'); stock.value = ''; price.value = ''; total.value = 'Rs 0.00'; mode.textContent = 'New item'; error.classList.add('d-none'); add.disabled = true; window.syncTradeFlowTomSelect?.(product); setTimeout(() => window.getTradeFlowTomSelect?.(product)?.focus(), 0); };
    add.addEventListener('click', () => { entryTouched = true; const option = selected(), quantity = Number(qty.value || 0), free = Number(freeQty.value || 0), available = Number(option?.dataset.stock || 0); if (!option?.value) { error.textContent = 'Select a product.'; error.classList.remove('d-none'); return; } if (!validateEntry(true)) return; const duplicate = rows().find(row => row !== editing && row.dataset.purchaseItemId === option.value); const row = editing || duplicate || document.createElement('tr'); const formattedQuantity = quantityLabel(quantity), formattedFree = quantityLabel(free); row.dataset.purchaseReturnRow = ''; row.dataset.purchaseItemId = option.value; row.dataset.stock = available; row.dataset.unit = option.dataset.unit || ''; row.dataset.price = option.dataset.price || 0; row.innerHTML = `<td data-purchase-return-index></td><td>${option.text}<input type="hidden" data-purchase-return-field="purchase_item_id" value="${option.value}"><input type="hidden" data-purchase-return-field="quantity" value="${formattedQuantity}"><input type="hidden" data-purchase-return-field="free_quantity" value="${formattedFree}"></td><td>${formattedQuantity}</td><td>${formattedFree}</td><td>${money(option.dataset.price)}</td><td>Rs 0.00</td><td>Rs 0.00</td><td data-purchase-return-line-total></td><td><button type="button" class="btn btn-sm btn-outline-primary" data-purchase-return-edit>Edit</button></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-purchase-return-delete>Delete</button></td>`; if (!editing && !duplicate) body.appendChild(row); render(); reset(); });
    product.addEventListener('change', () => { entryTouched = false; sync(); }); [qty, freeQty].forEach(input => input.addEventListener('input', () => { entryTouched = true; sync(); }));
    body.addEventListener('click', event => { const row = event.target.closest('[data-purchase-return-row]'); if (!row) return; if (event.target.closest('[data-purchase-return-edit]')) { editing = row; product.value = row.dataset.purchaseItemId; qty.value = row.querySelector('[data-purchase-return-field="quantity"]').value; freeQty.value = row.querySelector('[data-purchase-return-field="free_quantity"]').value; mode.textContent = 'Updating item'; window.syncTradeFlowTomSelect?.(product); sync(); setTimeout(() => window.getTradeFlowTomSelect?.(product)?.focus(), 0); } if (event.target.closest('[data-purchase-return-delete]')) { if (editing === row) reset(); row.remove(); render(); } });
    const previousItems = @json(old('items', []));
    previousItems.forEach(line => {
        const option = [...product.options].find(item => item.value === String(line.purchase_item_id || ''));
        if (!option) return;
        product.value = option.value;
        qty.value = line.quantity ?? 0;
        freeQty.value = line.free_quantity ?? 0;
        sync();
        if (validateEntry()) add.click();
    });
    form.addEventListener('submit', event => {
        if (!rows().length) {
            event.preventDefault();
            error.textContent = 'Add at least one return item before processing.';
            error.classList.remove('d-none');
            window.getTradeFlowTomSelect?.(product)?.focus();
            return;
        }
        if (form.dataset.returnSubmitting === '1') {
            event.preventDefault();
            return;
        }
        form.dataset.returnSubmitting = '1';
        const submit = event.submitter || form.querySelector('[data-purchase-return-submit]');
        if (submit) {
            submit.disabled = true;
            submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Processing...';
        }
    });
    sync(); render();
});
</script>
@endpush
