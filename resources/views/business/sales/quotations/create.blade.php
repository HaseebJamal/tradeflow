@extends('layouts.dashboard')
@section('page-title', 'New Sales Quotation')
@section('page-subtitle', 'Price a future sale without changing stock or accounts')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('business.sales.quotations.store') }}" class="tf-card p-4" data-quotation-form>
    @csrf
    <div class="row g-3 mb-4">
        <div class="col-md-4"><label class="form-label">Customer</label><select name="customer_id" class="form-select js-select2"><option value="">Walk-in / prospective customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->business_name ?: $customer->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Quotation Date</label><input name="quotation_date" type="date" value="{{ old('quotation_date', now()->toDateString()) }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Valid Until</label><input name="valid_until" type="date" value="{{ old('valid_until') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option @selected(old('status', 'Draft') === 'Draft')>Draft</option><option @selected(old('status') === 'Sent')>Sent</option></select></div>
    </div>

    <section class="border rounded p-3 mb-3 bg-light" aria-label="Add quotation item">
        <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Add quotation item</h2><small class="tf-muted">Choose whether each discount and tax value is a percentage or a fixed amount.</small></div>
        <div class="row g-2 align-items-end">
            <div class="col-xl-3"><label class="form-label">Product</label><select class="form-select js-select2" data-quotation-entry-product><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ $product->wholesale_price }}">{{ $product->name }} ({{ $product->stock_quantity }} {{ $product->unit }})</option>@endforeach</select></div>
            <div class="col-xl-1"><label class="form-label">Qty</label><input type="number" min="1" step="1" value="1" class="form-control js-no-number-spinner js-no-wheel-change" data-quotation-entry-qty></div>
            <div class="col-xl-1"><label class="form-label">Unit Price</label><input type="number" min="0" step="1" class="form-control js-no-number-spinner js-no-wheel-change" data-quotation-entry-price data-whole-number></div>
            <div class="col-xl-2"><label class="form-label">Discount</label><div class="input-group"><select class="form-select" data-quotation-entry-discount-type aria-label="Discount type"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed (Rs)</option></select><input type="number" min="0" step="1" value="0" class="form-control js-no-number-spinner js-no-wheel-change" data-quotation-entry-discount-value data-whole-number aria-label="Discount value"></div></div>
            <div class="col-xl-2"><label class="form-label">Tax</label><div class="input-group"><select class="form-select" data-quotation-entry-tax-type aria-label="Tax type"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed (Rs)</option></select><input type="number" min="0" step="1" value="0" class="form-control js-no-number-spinner js-no-wheel-change" data-quotation-entry-tax-value data-whole-number aria-label="Tax value"></div></div>
            <div class="col-xl-2"><label class="form-label">Line Total</label><input class="form-control" data-quotation-entry-total value="Rs 0.00" readonly></div>
            <div class="col-xl-1 d-grid"><button type="button" class="btn btn-tf-primary" data-add-quotation-item aria-label="Add item"><i class="bi bi-check-lg"></i></button></div>
        </div>
        <div class="invalid-feedback d-block d-none mt-2" data-quotation-entry-error></div>
    </section>

    <div class="table-responsive border rounded"><table class="table align-middle mb-0"><thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Discount</th><th>Tax</th><th>Line Total</th><th>Edit</th><th>Delete</th></tr></thead><tbody data-quotation-items><tr data-quotation-empty><td colspan="9" class="text-center tf-muted py-4">No quotation items added yet.</td></tr></tbody></table></div>
    <div class="row g-3 mt-3"><div class="col-md-8"><label class="form-label">Notes</label><input name="notes" value="{{ old('notes') }}" class="form-control"></div><div class="col-md-4 d-flex justify-content-between align-items-end gap-3"><strong>Total <span data-quotation-grand-total>Rs 0.00</span></strong><div class="d-flex gap-2"><a href="{{ route('business.sales.quotations.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-tf-primary" data-save-quotation>Save quotation</button></div></div></div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-quotation-form]');
    if (!form || form.dataset.quotationReady === '1') return;
    form.dataset.quotationReady = '1';

    const body = form.querySelector('[data-quotation-items]');
    const product = form.querySelector('[data-quotation-entry-product]');
    const qty = form.querySelector('[data-quotation-entry-qty]');
    const price = form.querySelector('[data-quotation-entry-price]');
    const discountType = form.querySelector('[data-quotation-entry-discount-type]');
    const discountValue = form.querySelector('[data-quotation-entry-discount-value]');
    const taxType = form.querySelector('[data-quotation-entry-tax-type]');
    const taxValue = form.querySelector('[data-quotation-entry-tax-value]');
    const total = form.querySelector('[data-quotation-entry-total]');
    const error = form.querySelector('[data-quotation-entry-error]');
    const addButton = form.querySelector('[data-add-quotation-item]');
    const initialItems = @json(array_values(old('items', [])));
    let editing = null;

    const money = value => `Rs ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
    const whole = input => Math.max(0, parseInt(input.value || '0', 10) || 0);
    const rows = () => [...body.querySelectorAll('[data-quotation-row]')];
    const adjustmentLabel = (type, value) => type === 'percentage' ? `${value}%` : money(value);
    const lineAmounts = (item = null) => {
        const values = item || { quantity: whole(qty), unit_price: whole(price), discount_type: discountType.value, discount_value: whole(discountValue), tax_type: taxType.value, tax_value: whole(taxValue) };
        const subtotal = Math.max(0, Number(values.quantity) || 0) * Math.max(0, Number(values.unit_price) || 0);
        const discount = values.discount_type === 'percentage' ? subtotal * Math.max(0, Number(values.discount_value) || 0) / 100 : Math.max(0, Number(values.discount_value) || 0);
        const safeDiscount = Math.min(subtotal, discount);
        const taxable = subtotal - safeDiscount;
        const tax = values.tax_type === 'percentage' ? taxable * Math.max(0, Number(values.tax_value) || 0) / 100 : Math.max(0, Number(values.tax_value) || 0);
        return { subtotal, discount: safeDiscount, tax, total: taxable + tax };
    };
    const showError = message => { error.textContent = message; error.classList.remove('d-none'); };
    const clearError = () => error.classList.add('d-none');
    const refreshProductOptions = () => {
        const selectedIds = new Set(rows().filter(row => row !== editing).map(row => row.dataset.productId));
        [...product.options].forEach(option => { if (option.value) option.disabled = selectedIds.has(option.value); });
        window.getTradeFlowTomSelect?.(product)?.refreshOptions(false);
    };
    const sync = () => {
        const option = product.selectedOptions[0];
        if (product.value && !price.value) price.value = option?.dataset.price || 0;
        total.value = money(lineAmounts().total);
    };
    const writeRow = (row, item) => {
        const amounts = lineAmounts(item);
        row.dataset.quotationRow = ''; row.dataset.productId = item.product_id; row.dataset.total = amounts.total;
        row.innerHTML = `<td data-quotation-index></td><td>${escapeHtml(item.product_name)}<input type="hidden" name="items[0][product_id]" value="${item.product_id}"></td><td>${item.quantity}<input type="hidden" name="items[0][quantity]" value="${item.quantity}"></td><td>${money(item.unit_price)}<input type="hidden" name="items[0][unit_price]" value="${item.unit_price}"></td><td>${adjustmentLabel(item.discount_type, item.discount_value)}<input type="hidden" name="items[0][discount_type]" value="${item.discount_type}"><input type="hidden" name="items[0][discount_value]" value="${item.discount_value}"></td><td>${adjustmentLabel(item.tax_type, item.tax_value)}<input type="hidden" name="items[0][tax_type]" value="${item.tax_type}"><input type="hidden" name="items[0][tax_value]" value="${item.tax_value}"></td><td>${money(amounts.total)}</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-edit-quotation-item>Edit</button></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-delete-quotation-item>Delete</button></td>`;
    };
    const render = () => {
        let grandTotal = 0;
        rows().forEach((row, index) => { row.querySelector('[data-quotation-index]').textContent = index + 1; row.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`)); grandTotal += Number(row.dataset.total || 0); });
        body.querySelector('[data-quotation-empty]')?.classList.toggle('d-none', rows().length > 0);
        form.querySelector('[data-quotation-grand-total]').textContent = money(grandTotal);
        refreshProductOptions();
    };
    const reset = () => {
        editing = null; product.value = ''; qty.value = 1; price.value = ''; discountType.value = 'percentage'; discountValue.value = 0; taxType.value = 'percentage'; taxValue.value = 0; total.value = 'Rs 0.00'; clearError(); refreshProductOptions(); window.syncTradeFlowTomSelect?.(product); setTimeout(() => window.getTradeFlowTomSelect?.(product)?.focus(), 0);
    };
    const add = () => {
        const option = product.selectedOptions[0];
        const inputsAreWhole = [qty, price, discountValue, taxValue].every(input => /^\d+$/.test(input.value));
        const amounts = lineAmounts();
        if (!product.value || !inputsAreWhole || Number(qty.value) < 1 || Number(price.value) < 0 || (discountType.value === 'percentage' && whole(discountValue) > 100) || (taxType.value === 'percentage' && whole(taxValue) > 100) || (discountType.value === 'fixed' && whole(discountValue) > amounts.subtotal)) {
            let message = 'Select a product and enter a quantity of at least 1.';
            if (!inputsAreWhole) message = 'Only whole numbers are allowed.';
            else if (discountType.value === 'percentage' && whole(discountValue) > 100) message = 'Discount percentage cannot exceed 100.';
            else if (taxType.value === 'percentage' && whole(taxValue) > 100) message = 'Tax percentage cannot exceed 100.';
            else if (discountType.value === 'fixed' && whole(discountValue) > amounts.subtotal) message = 'Discount cannot exceed the item base amount.';
            showError(message); return;
        }
        const existing = rows().find(row => row.dataset.productId === product.value);
        const target = editing || existing || document.createElement('tr');
        const existingQuantity = existing && existing !== editing ? Number(existing.querySelector('[name$="[quantity]"]').value) : 0;
        writeRow(target, { product_id: product.value, product_name: option.text, quantity: existingQuantity + Number(qty.value), unit_price: price.value, discount_type: discountType.value, discount_value: whole(discountValue), tax_type: taxType.value, tax_value: whole(taxValue) });
        if (!editing && !existing) body.appendChild(target);
        render(); reset();
    };
    product.addEventListener('change', sync);
    [qty, price, discountValue, taxValue, discountType, taxType].forEach(input => input.addEventListener('input', sync));
    [discountType, taxType].forEach(input => input.addEventListener('change', sync));
    addButton.addEventListener('click', add);
    body.addEventListener('click', event => {
        const row = event.target.closest('[data-quotation-row]'); if (!row) return;
        if (event.target.closest('[data-delete-quotation-item]')) { if (editing === row) reset(); row.remove(); render(); return; }
        if (event.target.closest('[data-edit-quotation-item]')) {
            editing = row; product.value = row.dataset.productId; qty.value = row.querySelector('[name$="[quantity]"]').value; price.value = row.querySelector('[name$="[unit_price]"]').value; discountType.value = row.querySelector('[name$="[discount_type]"]').value; discountValue.value = row.querySelector('[name$="[discount_value]"]').value; taxType.value = row.querySelector('[name$="[tax_type]"]').value; taxValue.value = row.querySelector('[name$="[tax_value]"]').value;
            refreshProductOptions(); window.syncTradeFlowTomSelect?.(product); sync(); clearError(); setTimeout(() => window.getTradeFlowTomSelect?.(product)?.focus(), 0);
        }
    });
    form.addEventListener('submit', event => { if (!rows().length) { event.preventDefault(); showError('Please add at least one quotation item.'); window.getTradeFlowTomSelect?.(product)?.focus(); } });
    initialItems.forEach(item => {
        const option = [...product.options].find(candidate => String(candidate.value) === String(item.product_id)); if (!option) return;
        const row = document.createElement('tr');
        writeRow(row, { product_id: item.product_id, product_name: option.text, quantity: item.quantity, unit_price: item.unit_price, discount_type: item.discount_type ?? 'fixed', discount_value: item.discount_value ?? 0, tax_type: item.tax_type ?? 'fixed', tax_value: item.tax_value ?? 0 }); body.appendChild(row);
    });
    sync(); render();
});
</script>
@endpush
@endsection
