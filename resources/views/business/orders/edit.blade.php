@extends('layouts.dashboard')
@section('page-title', 'Edit Sale')
@section('page-subtitle', $order->order_number)
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ route('business.sales.update', $order) }}" class="tf-card p-4 mb-4" data-sale-edit-form>
    @csrf
    @method('PUT')

    <div class="row g-3 mb-4">
        <div class="col-md-5"><label class="form-label">Customer</label><select name="customer_id" class="form-select"><option value="walk_in" @selected(old('customer_id', $order->customer_id ? $order->customer_id : 'walk_in') === 'walk_in')>Walk-in Customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) old('customer_id', $order->customer_id) === (string) $customer->id)>{{ $customer->display_name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Payment Type</label><select name="payment_type" class="form-select">@foreach(['Credit', 'Cash', 'Partial'] as $paymentType)<option @selected(old('payment_type', $order->payment_type) === $paymentType)>{{ $paymentType }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Sale Discount %</label><input name="discount" type="number" min="0" max="100" step="1" class="form-control js-whole-number" value="{{ old('discount', $order->discount_percentage ?? $order->discount ?? 0) }}" data-sale-edit-discount></div>
        <div class="col-md-2"><label class="form-label">Sale Tax %</label><input name="tax_rate" type="number" min="0" max="100" step="1" class="form-control js-whole-number" value="{{ old('tax_rate', $order->tax_rate ?? 0) }}" data-sale-edit-tax></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-tf-primary w-100">Save Changes</button></div>
    </div>

    <section class="border rounded bg-light p-3 mb-3" aria-label="Add or update sale item">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><div><h2 class="h6 mb-0">Sale item</h2><small class="tf-muted">Select an item, then add it to the compact list below.</small></div><small class="tf-muted" data-sale-edit-mode>New item</small></div>
        <div class="row g-2 align-items-end">
            <div class="col-lg-3"><label class="form-label">Product</label><select class="form-select" data-sale-edit-product><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->wholesale_price }}" data-stock="{{ $product->stock_quantity }}" data-unit="{{ $product->unit }}">{{ $product->name }}</option>@endforeach</select></div>
            <div class="col-lg-1"><label class="form-label">Available Stock</label><input class="form-control" readonly data-sale-edit-stock></div>
            <div class="col-lg-1"><label class="form-label">Quantity</label><input type="number" min="1" step="1" value="0" class="form-control js-whole-number" data-sale-edit-qty></div>
            <div class="col-lg-2"><label class="form-label">Unit Price</label><input class="form-control" readonly data-sale-edit-price></div>
            <div class="col-lg-1"><label class="form-label">Item Discount %</label><input type="number" min="0" max="100" step="1" value="0" class="form-control js-whole-number" data-sale-edit-item-discount></div>
            <div class="col-lg-1"><label class="form-label">Item Tax %</label><input type="number" min="0" max="100" step="1" value="0" class="form-control js-whole-number" data-sale-edit-item-tax></div>
            <div class="col-lg-2"><label class="form-label">Line Total</label><input class="form-control" value="Rs 0.00" readonly data-sale-edit-total></div>
            <div class="col-lg-1 d-grid"><button type="button" class="btn btn-tf-primary" data-sale-edit-add aria-label="Add or update item"><i class="bi bi-check-lg"></i></button></div>
        </div>
        <div class="invalid-feedback d-block d-none mt-2" data-sale-edit-error></div>
    </section>

    <div class="table-responsive border rounded" style="max-height: 360px"><table class="table align-middle mb-0"><thead class="sticky-top"><tr><th>#</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Discount</th><th>Tax</th><th>Line Total</th><th>Edit</th><th>Delete</th></tr></thead><tbody data-sale-edit-items>
        @foreach($order->items as $item)
            @php($availableForEdit = ($item->product?->stock_quantity ?? 0) + $item->quantity)
            <tr data-sale-edit-row data-existing="1" data-item-id="{{ $item->id }}" data-product-id="{{ $item->product_id }}" data-product-name="{{ $item->product?->name }}" data-stock="{{ $availableForEdit }}" data-unit="{{ $item->product?->unit }}" data-price="{{ $item->price }}">
                <td data-sale-edit-index>{{ $loop->iteration }}</td><td>{{ $item->product?->name }}<input type="hidden" data-sale-edit-field="item_id" value="{{ $item->id }}"><input type="hidden" data-sale-edit-field="product_id" value="{{ $item->product_id }}"><input type="hidden" data-sale-edit-field="quantity" value="{{ old('items.'.$loop->index.'.quantity', $item->quantity) }}"><input type="hidden" data-sale-edit-field="discount_rate" value="{{ old('items.'.$loop->index.'.discount_rate', $item->discount_rate ?? 0) }}"><input type="hidden" data-sale-edit-field="tax_rate" value="{{ old('items.'.$loop->index.'.tax_rate', $item->tax_rate ?? 0) }}"><input type="hidden" data-sale-edit-field="remove" value="0"></td><td data-sale-edit-qty-label>{{ old('items.'.$loop->index.'.quantity', $item->quantity) }}</td><td data-sale-edit-price-label>Rs {{ number_format($item->price, 2) }}</td><td data-sale-edit-discount-label>{{ old('items.'.$loop->index.'.discount_rate', $item->discount_rate ?? 0) }}%</td><td data-sale-edit-tax-label>{{ old('items.'.$loop->index.'.tax_rate', $item->tax_rate ?? 0) }}%</td><td data-sale-edit-line-total>Rs {{ number_format($item->line_total ?? $item->total, 2) }}</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-sale-tax-edit>Edit</button></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-sale-tax-delete>Delete</button></td>
            </tr>
        @endforeach
        <tr data-sale-edit-empty class="d-none"><td colspan="9" class="text-center tf-muted py-4">No sale items remain. Add an item before saving.</td></tr>
    </tbody></table></div>
    <div class="d-none" data-sale-edit-removed></div>

    <div class="row g-3 mt-3"><div class="col-md-2"><div class="border rounded p-3"><small class="tf-muted">Subtotal</small><strong class="d-block" data-sale-edit-subtotal>Rs 0</strong></div></div><div class="col-md-2"><div class="border rounded p-3"><small class="tf-muted">Discount Amount</small><strong class="d-block" data-sale-edit-discount-amount>Rs 0</strong></div></div><div class="col-md-2"><div class="border rounded p-3"><small class="tf-muted">Tax Amount</small><strong class="d-block" data-sale-edit-tax-amount>Rs 0</strong></div></div><div class="col-md-3"><div class="border rounded p-3"><small class="tf-muted">Grand Total</small><strong class="d-block" data-sale-edit-grand-total>Rs 0</strong></div></div><div class="col-md-3"><div class="border rounded p-3"><small class="tf-muted">Paid / Balance</small><strong class="d-block">Rs {{ number_format($order->paid_amount ?? 0) }} / <span data-sale-edit-balance>Rs {{ number_format($order->balance ?? 0) }}</span></strong></div></div></div>
    <div class="d-flex justify-content-between align-items-center mt-4"><a href="{{ route('business.sales.show', $order) }}" class="btn btn-outline-secondary">Back to Details</a><button class="btn btn-tf-primary">Save Order Changes</button></div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-sale-edit-form]');
    if (!form) return;
    const body = form.querySelector('[data-sale-edit-items]'), removed = form.querySelector('[data-sale-edit-removed]'), product = form.querySelector('[data-sale-edit-product]'), stock = form.querySelector('[data-sale-edit-stock]'), qty = form.querySelector('[data-sale-edit-qty]'), price = form.querySelector('[data-sale-edit-price]'), total = form.querySelector('[data-sale-edit-total]'), error = form.querySelector('[data-sale-edit-error]'), mode = form.querySelector('[data-sale-edit-mode]');
    let editing = null;
    const money = value => `Rs ${Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    const rows = () => [...body.querySelectorAll('[data-sale-edit-row]')];
    const selected = () => product.selectedOptions[0];
    const currentPrice = () => Number(price.value || 0);
    const reservedNewQuantity = () => rows().filter(row => row !== editing && row.dataset.existing === '0' && String(row.dataset.productId) === String(selected()?.value)).reduce((sum, row) => sum + Number(row.querySelector('[data-sale-edit-field="quantity"]')?.value || 0), 0);
    const availableForEntry = () => Math.max(0, Number(editing?.dataset.existing === '1' ? editing.dataset.stock : (selected()?.dataset.stock || 0)) - reservedNewQuantity());
    const validateEntry = () => {
        const available = availableForEntry(), requested = Number(qty.value || 0);
        qty.max = String(available);
        const message = !selected()?.value ? '' : (!Number.isInteger(requested) || requested < 1 ? 'Quantity must be at least 1.' : (requested > available ? `Insufficient stock. Only ${available} units are available.` : ''));
        qty.setCustomValidity(message);
        qty.classList.toggle('is-invalid', Boolean(message));
        if (message) { error.textContent = message; error.classList.remove('d-none'); } else { error.textContent = ''; error.classList.add('d-none'); }
        return !message;
    };
    const syncEntry = () => { const option = selected(); if (!option?.value) { stock.value = ''; price.value = ''; total.value = 'Rs 0.00'; qty.removeAttribute('max'); return; } const available = availableForEntry(); stock.value = `${available} ${option.dataset.unit || ''}`; price.value = option.dataset.price || 0; total.value = money((Number(qty.value) || 0) * currentPrice()); validateEntry(); };
    const indexFields = () => { [...body.querySelectorAll('[data-sale-edit-row]'), ...removed.querySelectorAll('[data-sale-edit-removed-row]')].forEach((row, index) => row.querySelectorAll('[data-sale-edit-field]').forEach(field => field.name = `items[${index}][${field.dataset.saleEditField}]`)); };
    const render = () => { let subtotal = 0; rows().forEach((row, index) => { const line = Number(row.dataset.price || 0) * Number(row.querySelector('[data-sale-edit-field="quantity"]')?.value || 0); subtotal += line; row.querySelector('[data-sale-edit-index]').textContent = index + 1; row.querySelector('[data-sale-edit-qty-label]').textContent = row.querySelector('[data-sale-edit-field="quantity"]').value; row.querySelector('[data-sale-edit-line-total]').textContent = money(line); }); body.querySelector('[data-sale-edit-empty]')?.classList.toggle('d-none', rows().length > 0); const discount = Math.min(100, Math.max(0, Number(form.querySelector('[data-sale-edit-discount]').value || 0))); const discountAmount = subtotal * discount / 100, grand = Math.max(0, subtotal - discountAmount), paid = {{ (float) ($order->paid_amount ?? 0) }}; form.querySelector('[data-sale-edit-subtotal]').textContent = money(subtotal); form.querySelector('[data-sale-edit-discount-amount]').textContent = money(discountAmount); form.querySelector('[data-sale-edit-grand-total]').textContent = money(grand); form.querySelector('[data-sale-edit-balance]').textContent = money(Math.max(0, grand - paid)); indexFields(); };
    const reset = () => { editing = null; product.disabled = false; product.value = ''; qty.value = 0; price.value = ''; stock.value = ''; total.value = 'Rs 0.00'; mode.textContent = 'New item'; error.classList.add('d-none'); window.syncTradeFlowTomSelect?.(product); setTimeout(() => window.getTradeFlowTomSelect?.(product)?.focus(), 0); };
    const addOrUpdate = () => { const option = selected(), requestedQuantity = Number(qty.value || 0), available = availableForEntry(); if (!option?.value) { error.textContent = 'Select a product.'; error.classList.remove('d-none'); return; } if (!validateEntry()) return; const row = editing || document.createElement('tr'); const existingItem = editing?.dataset.existing === '1'; const existingStock = Number(editing?.dataset.stock || 0); const itemIdField = existingItem ? `<input type="hidden" data-sale-edit-field="item_id" value="${editing.dataset.itemId}">` : ''; row.dataset.saleEditRow = ''; row.dataset.existing = existingItem ? '1' : '0'; row.dataset.productId = option.value; row.dataset.productName = option.dataset.name || option.text; row.dataset.stock = existingItem ? existingStock : Number(option.dataset.stock || 0); row.dataset.unit = option.dataset.unit || ''; row.dataset.price = option.dataset.price || 0; row.innerHTML = `<td data-sale-edit-index></td><td>${option.text}${itemIdField}<input type="hidden" data-sale-edit-field="product_id" value="${option.value}"><input type="hidden" data-sale-edit-field="quantity" value="${requestedQuantity}"><input type="hidden" data-sale-edit-field="remove" value="0"></td><td data-sale-edit-qty-label></td><td data-sale-edit-price-label>${money(option.dataset.price)}</td><td>Rs 0.00</td><td>Rs 0.00</td><td data-sale-edit-line-total></td><td><button type="button" class="btn btn-sm btn-outline-primary" data-sale-edit-row-edit>Edit</button></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-sale-edit-row-delete>Delete</button></td>`; row.querySelector('[data-sale-edit-field="quantity"]').value = requestedQuantity; if (!editing) body.appendChild(row); render(); reset(); };
    form.querySelector('[data-sale-edit-add]').addEventListener('click', addOrUpdate);
    product.addEventListener('change', syncEntry); qty.addEventListener('input', () => { total.value = money((Number(qty.value) || 0) * currentPrice()); validateEntry(); }); form.querySelector('[data-sale-edit-discount]').addEventListener('input', render);
    body.addEventListener('click', event => { const row = event.target.closest('[data-sale-edit-row]'); if (!row) return; if (event.target.closest('[data-sale-edit-row-edit]')) { editing = row; product.value = row.dataset.productId; product.disabled = row.dataset.existing === '1'; qty.value = row.querySelector('[data-sale-edit-field="quantity"]').value; stock.value = `${row.dataset.stock} ${row.dataset.unit}`; price.value = row.dataset.price; total.value = money(Number(qty.value) * Number(row.dataset.price)); mode.textContent = row.dataset.existing === '1' ? 'Updating existing item' : 'Updating new item'; error.classList.add('d-none'); window.syncTradeFlowTomSelect?.(product); setTimeout(() => window.getTradeFlowTomSelect?.(product)?.focus(), 0); } if (event.target.closest('[data-sale-edit-row-delete]')) { if (editing === row) reset(); if (row.dataset.existing === '1') { const deleted = document.createElement('div'); deleted.dataset.saleEditRemovedRow = ''; deleted.innerHTML = `<input type="hidden" data-sale-edit-field="item_id" value="${row.dataset.itemId}"><input type="hidden" data-sale-edit-field="product_id" value="${row.dataset.productId}"><input type="hidden" data-sale-edit-field="remove" value="1">`; removed.appendChild(deleted); } row.remove(); render(); } });
    form.addEventListener('submit', event => { if (rows().length) return; event.preventDefault(); error.textContent = 'Keep at least one sale item before saving.'; error.classList.remove('d-none'); window.getTradeFlowTomSelect?.(product)?.focus(); });
    syncEntry(); render();
});
</script>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-sale-edit-form]');
    const body = form?.querySelector('[data-sale-edit-items]');
    if (!form || !body || form.dataset.saleEditTaxDiscountReady === '1') return;
    form.dataset.saleEditTaxDiscountReady = '1';

    const product = form.querySelector('[data-sale-edit-product]');
    const qty = form.querySelector('[data-sale-edit-qty]');
    const stock = form.querySelector('[data-sale-edit-stock]');
    const price = form.querySelector('[data-sale-edit-price]');
    const total = form.querySelector('[data-sale-edit-total]');
    const itemDiscount = form.querySelector('[data-sale-edit-item-discount]');
    const itemTax = form.querySelector('[data-sale-edit-item-tax]');
    const saleDiscount = form.querySelector('[data-sale-edit-discount]');
    const saleTax = form.querySelector('[data-sale-edit-tax]');
    const error = form.querySelector('[data-sale-edit-error]');
    const removed = form.querySelector('[data-sale-edit-removed]');
    let editing = null;

    const money = value => 'Rs ' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const rows = () => [...body.querySelectorAll('[data-sale-edit-row]')];
    const selected = () => product.selectedOptions[0];
    const lineAmounts = (quantity, unitPrice, discountRate, taxRate) => {
        const subtotal = quantity * unitPrice;
        const discount = subtotal * discountRate / 100;
        const tax = (subtotal - discount) * taxRate / 100;
        return { total: subtotal - discount + tax };
    };
    const rate = input => Math.max(0, Math.min(100, Number(input.value || 0)));
    const available = () => Number(editing?.dataset.existing === '1' ? editing.dataset.stock : (selected()?.dataset.stock || 0));
    const showError = message => {
        qty.setCustomValidity(message || '');
        qty.classList.toggle('is-invalid', Boolean(message));
        error.textContent = message || '';
        error.classList.toggle('d-none', !message);
    };
    const valid = () => {
        const requested = Number(qty.value || 0);
        if (!selected()?.value) return false;
        if (!Number.isInteger(requested) || requested < 1) {
            showError('Quantity must be at least 1.');
            return false;
        }
        if (requested > available()) {
            showError('Insufficient stock. Only ' + available() + ' units are available.');
            return false;
        }
        if (!Number.isInteger(Number(itemDiscount.value || 0)) || !Number.isInteger(Number(itemTax.value || 0)) || Number(itemDiscount.value || 0) > 100 || Number(itemTax.value || 0) > 100) {
            showError('Discount and tax must be whole numbers between 0 and 100.');
            return false;
        }
        showError('');
        return true;
    };
    const sync = () => {
        if (!selected()?.value) return;
        stock.value = available() + ' ' + (selected().dataset.unit || '');
        price.value = selected().dataset.price || 0;
        total.value = money(lineAmounts(Number(qty.value || 0), Number(price.value || 0), rate(itemDiscount), rate(itemTax)).total);
        valid();
    };
    const render = () => {
        let subtotal = 0;
        rows().forEach((row, index) => {
            const itemQty = Number(row.querySelector('[data-sale-edit-field="quantity"]').value || 0);
            const discountRate = Number(row.querySelector('[data-sale-edit-field="discount_rate"]').value || 0);
            const taxRate = Number(row.querySelector('[data-sale-edit-field="tax_rate"]').value || 0);
            const amounts = lineAmounts(itemQty, Number(row.dataset.price || 0), discountRate, taxRate);
            subtotal += amounts.total;
            row.querySelector('[data-sale-edit-index]').textContent = index + 1;
            row.querySelector('[data-sale-edit-qty-label]').textContent = itemQty;
            row.querySelector('[data-sale-edit-discount-label]').textContent = discountRate + '%';
            row.querySelector('[data-sale-edit-tax-label]').textContent = taxRate + '%';
            row.querySelector('[data-sale-edit-line-total]').textContent = money(amounts.total);
        });
        const discount = rate(saleDiscount);
        const tax = rate(saleTax);
        const discountAmount = subtotal * discount / 100;
        const taxAmount = (subtotal - discountAmount) * tax / 100;
        form.querySelector('[data-sale-edit-subtotal]').textContent = money(subtotal);
        form.querySelector('[data-sale-edit-discount-amount]').textContent = money(discountAmount);
        form.querySelector('[data-sale-edit-tax-amount]').textContent = money(taxAmount);
        form.querySelector('[data-sale-edit-grand-total]').textContent = money(subtotal - discountAmount + taxAmount);
        form.querySelector('[data-sale-edit-balance]').textContent = money(Math.max(0, subtotal - discountAmount + taxAmount - {{ (float) ($order->paid_amount ?? 0) }}));
    };
    const reset = () => {
        editing = null;
        product.disabled = false;
        product.value = '';
        qty.value = 0;
        itemDiscount.value = 0;
        itemTax.value = 0;
        stock.value = '';
        price.value = '';
        total.value = 'Rs 0.00';
        showError('');
        window.syncTradeFlowTomSelect?.(product);
    };
    const addOrUpdate = event => {
        event.preventDefault();
        event.stopImmediatePropagation();
        if (!selected()?.value) {
            showError('Select a product.');
            return;
        }
        if (!valid()) return;
        const option = selected();
        const row = editing || document.createElement('tr');
        const existing = editing?.dataset.existing === '1';
        row.dataset.saleEditRow = '';
        row.dataset.existing = existing ? '1' : '0';
        row.dataset.itemId = editing?.dataset.itemId || '';
        row.dataset.productId = option.value;
        row.dataset.stock = existing ? editing.dataset.stock : option.dataset.stock;
        row.dataset.unit = option.dataset.unit || '';
        row.dataset.price = option.dataset.price || 0;
        const itemId = existing ? '<input type="hidden" data-sale-edit-field="item_id" value="' + row.dataset.itemId + '">' : '';
        row.innerHTML = '<td data-sale-edit-index></td><td>' + option.text + itemId + '<input type="hidden" data-sale-edit-field="product_id" value="' + option.value + '"><input type="hidden" data-sale-edit-field="quantity" value="' + qty.value + '"><input type="hidden" data-sale-edit-field="discount_rate" value="' + rate(itemDiscount) + '"><input type="hidden" data-sale-edit-field="tax_rate" value="' + rate(itemTax) + '"><input type="hidden" data-sale-edit-field="remove" value="0"></td><td data-sale-edit-qty-label></td><td data-sale-edit-price-label>' + money(option.dataset.price) + '</td><td data-sale-edit-discount-label></td><td data-sale-edit-tax-label></td><td data-sale-edit-line-total></td><td><button type="button" class="btn btn-sm btn-outline-primary" data-sale-tax-edit>Edit</button></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-sale-tax-delete>Delete</button></td>';
        if (!editing) body.appendChild(row);
        form.querySelectorAll('[data-sale-edit-row], [data-sale-edit-removed-row]').forEach((entry, index) => entry.querySelectorAll('[data-sale-edit-field]').forEach(field => field.name = 'items[' + index + '][' + field.dataset.saleEditField + ']'));
        render();
        reset();
    };
    form.querySelector('[data-sale-edit-add]').addEventListener('click', addOrUpdate, true);
    [product, qty, itemDiscount, itemTax].forEach(input => input?.addEventListener('input', sync));
    product.addEventListener('change', sync);
    [saleDiscount, saleTax].forEach(input => input?.addEventListener('input', render));
    body.addEventListener('click', event => {
        const row = event.target.closest('[data-sale-edit-row]');
        if (!row) return;
        if (event.target.closest('[data-sale-tax-edit]')) {
            event.stopImmediatePropagation();
            editing = row;
            product.value = row.dataset.productId;
            product.disabled = row.dataset.existing === '1';
            qty.value = row.querySelector('[data-sale-edit-field="quantity"]').value;
            itemDiscount.value = row.querySelector('[data-sale-edit-field="discount_rate"]').value;
            itemTax.value = row.querySelector('[data-sale-edit-field="tax_rate"]').value;
            window.syncTradeFlowTomSelect?.(product);
            sync();
        }
        if (event.target.closest('[data-sale-tax-delete]')) {
            event.stopImmediatePropagation();
            if (row.dataset.existing === '1') {
                const deleted = document.createElement('div');
                deleted.dataset.saleEditRemovedRow = '';
                deleted.innerHTML = '<input type="hidden" data-sale-edit-field="item_id" value="' + row.dataset.itemId + '"><input type="hidden" data-sale-edit-field="product_id" value="' + row.dataset.productId + '"><input type="hidden" data-sale-edit-field="remove" value="1">';
                removed.appendChild(deleted);
            }
            if (editing === row) reset();
            row.remove();
            render();
        }
    }, true);
    render();
});
</script>
@endpush
