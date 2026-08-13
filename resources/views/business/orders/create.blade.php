@extends('layouts.dashboard')
@section('page-title', 'Create Order')
@section('page-subtitle', 'Create a customer sale with stock-checked items')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('business.sales.store') }}" class="tf-card p-3 p-lg-4" data-order-form novalidate>@csrf
    <section class="order-details-panel border rounded p-3 mb-3"><div class="row g-3 align-items-end">@if($canViewCustomers)<div class="col-lg-5"><label class="form-label">Customer</label><div class="order-customer-control"><div><select name="customer_id" class="form-select" data-order-customer-select><option value="">Select existing customer</option><option value="walk_in" @selected(old('customer_id') === 'walk_in')>Walk-in Customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->display_name }}@if($customer->business_name) — {{ $customer->business_name }}@endif</option>@endforeach</select></div>@companyCan('customers.create')<button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickCustomerModal"><i class="bi bi-person-plus me-1"></i>Add New Customer</button>@endcompanyCan</div><div class="small text-success mt-2 d-none" data-quick-customer-selected></div></div>@endif<div class="col-sm-4 {{ $canViewCustomers ? 'col-lg-2' : 'col-lg-4' }}"><label class="form-label">Payment Type</label><select name="payment_type" class="form-select"><option @selected(old('payment_type') === 'Credit')>Credit</option><option @selected(old('payment_type') === 'Cash')>Cash</option><option @selected(old('payment_type') === 'Partial')>Partial</option></select></div><div class="col-sm-4 {{ $canViewCustomers ? 'col-lg-2' : 'col-lg-4' }}"><label class="form-label">Sale Discount %</label><input name="discount" type="number" min="0" max="100" step="1" class="form-control js-whole-number" value="{{ old('discount', 0) }}" data-order-discount></div><div class="col-sm-4 {{ $canViewCustomers ? 'col-lg-2' : 'col-lg-4' }}"><label class="form-label">Sale Tax %</label><input name="tax_rate" type="number" min="0" max="100" step="1" class="form-control js-whole-number" value="{{ old('tax_rate', 0) }}" data-order-tax></div></div></section>
    <section class="border rounded p-3 mb-3 bg-light"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h6 mb-0">Add sale item</h2><small class="tf-muted">Stock is checked before the item is added.</small></div></div><div class="row g-2 align-items-end"><div class="col-md-6 col-xl-3"><label class="form-label">Product</label><select class="form-select" data-sale-entry-product><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ $product->wholesale_price }}" data-stock="{{ $product->stock_quantity }}" data-unit="{{ $product->unit }}">{{ $product->name }}</option>@endforeach</select></div><div class="col-4 col-md-2 col-xl-1"><label class="form-label">Stock</label><input class="form-control" data-sale-entry-stock readonly></div><div class="col-4 col-md-2 col-xl-1"><label class="form-label">Qty</label><input type="number" min="1" step="1" value="0" class="form-control js-whole-number" data-sale-entry-qty></div><div class="col-4 col-md-2 col-xl-2"><label class="form-label">Unit Price</label><input class="form-control" data-sale-entry-price readonly></div><div class="col-6 col-md-3 col-xl-1"><label class="form-label">Item Discount %</label><input type="number" min="0" max="100" step="1" value="0" class="form-control js-whole-number" data-sale-entry-discount></div><div class="col-6 col-md-3 col-xl-1"><label class="form-label">Item Tax %</label><input type="number" min="0" max="100" step="1" value="0" class="form-control js-whole-number" data-sale-entry-tax></div><div class="col-8 col-md-4 col-xl-2"><label class="form-label">Line Total</label><input class="form-control" data-sale-entry-total value="Rs 0.00" readonly></div><div class="col-4 col-md-2 col-xl-1 d-grid"><button type="button" class="btn btn-tf-primary" data-add-sale-item title="Add item"><i class="bi bi-plus-lg me-1"></i><span class="d-none d-xl-inline">Add</span></button></div></div><div class="invalid-feedback d-block d-none mt-2" data-sale-entry-error></div></section>
    <div class="table-responsive border rounded"><table class="table align-middle mb-0"><thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Discount</th><th>Tax</th><th>Line Total</th><th>Edit</th><th>Delete</th></tr></thead><tbody data-sale-items><tr data-sale-empty><td colspan="9" class="text-center tf-muted py-4">No sale items added yet.</td></tr></tbody></table></div>
    <div class="row g-2 mt-3" data-order-preview><div class="col-6 col-lg-2"><div class="order-summary-card"><small class="tf-muted">Subtotal</small><strong class="d-block" data-order-subtotal>Rs 0</strong></div></div><div class="col-6 col-lg-2"><div class="order-summary-card"><small class="tf-muted">Discount %</small><strong class="d-block" data-order-discount-label>0%</strong></div></div><div class="col-6 col-lg-2"><div class="order-summary-card"><small class="tf-muted">Discount Amount</small><strong class="d-block" data-order-discount-amount>Rs 0</strong></div></div><div class="col-6 col-lg-2"><div class="order-summary-card"><small class="tf-muted">Tax Amount</small><strong class="d-block" data-order-tax-amount>Rs 0</strong></div></div><div class="col-12 col-lg-4"><div class="order-summary-card"><small class="tf-muted">Grand Total</small><strong class="d-block" data-order-grand-total>Rs 0</strong></div></div></div>
    <div class="d-flex justify-content-end mt-3"><button class="btn btn-tf-primary"><i class="bi bi-check2-circle me-1"></i>Create Order</button></div>
    <input type="hidden" name="new_customer_name" value="{{ old('new_customer_name') }}" data-new-customer-name>
    <input type="hidden" name="new_customer_shop" value="{{ old('new_customer_shop') }}" data-new-customer-shop>
    <input type="hidden" name="new_customer_city" value="{{ old('new_customer_city') }}" data-new-customer-city>
    <input type="hidden" name="new_customer_address" value="{{ old('new_customer_address') }}" data-new-customer-address>
    <input type="hidden" name="new_customer_type" value="{{ old('new_customer_type', 'Retailer') }}" data-new-customer-type>
    <input type="hidden" name="new_customer_credit_limit" value="{{ old('new_customer_credit_limit') }}" data-new-customer-credit-limit>
</form>
<div class="modal fade" id="quickCustomerModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5">Add New Customer</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="alert alert-warning d-none" data-quick-customer-error>Enter at least customer name or phone.</div><div class="row g-3"><div class="col-md-6"><label class="form-label">Customer Name</label><input class="form-control" data-modal-customer-name></div><div class="col-md-6"><label class="form-label">Shop Name</label><input class="form-control" data-modal-customer-shop></div><div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" data-modal-customer-phone></div><div class="col-md-4"><label class="form-label">City</label><input class="form-control" data-modal-customer-city></div><div class="col-md-4"><label class="form-label">Customer Type</label><select class="form-select" data-modal-customer-type><option>Retailer</option><option>Retail Shop</option><option>Dealer</option><option>Distributor</option><option>Wholesaler</option></select></div><div class="col-md-8"><label class="form-label">Address</label><input class="form-control" data-modal-customer-address></div><div class="col-md-4"><label class="form-label">Credit Limit</label><input type="number" min="0" step="0.01" class="form-control" data-modal-customer-credit-limit></div></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-tf-primary" data-save-quick-customer>Save Customer</button></div></div></div></div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-order-form]');
    const body = form?.querySelector('[data-sale-items]');
    if (!form || !body || form.dataset.orderTaxDiscountReady === '1') return;
    form.dataset.orderTaxDiscountReady = '1';

    const product = form.querySelector('[data-sale-entry-product]');
    const stock = form.querySelector('[data-sale-entry-stock]');
    const quantity = form.querySelector('[data-sale-entry-qty]');
    const price = form.querySelector('[data-sale-entry-price]');
    const itemDiscount = form.querySelector('[data-sale-entry-discount]');
    const itemTax = form.querySelector('[data-sale-entry-tax]');
    const lineTotal = form.querySelector('[data-sale-entry-total]');
    const error = form.querySelector('[data-sale-entry-error]');
    const addButton = form.querySelector('[data-add-sale-item]');
    const saleDiscount = form.querySelector('[data-order-discount]');
    const saleTax = form.querySelector('[data-order-tax]');
    let editing = null;

    const money = value => 'Rs ' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const rows = () => [...body.querySelectorAll('[data-order-line]')];
    const option = () => product.selectedOptions[0];
    const rate = input => Math.max(0, Math.min(100, Number(input.value || 0)));
    const lineAmounts = (qty, unitPrice, discountRate, taxRate) => {
        const subtotal = qty * unitPrice;
        const discount = subtotal * discountRate / 100;
        const tax = (subtotal - discount) * taxRate / 100;
        return { subtotal, discount, tax, total: subtotal - discount + tax };
    };
    const reservedQuantity = (productId, except = null) => rows()
        .filter(row => row !== except && row.dataset.productId === String(productId))
        .reduce((sum, row) => sum + Number(row.querySelector('[data-order-qty]')?.value || 0), 0);
    const available = () => Math.max(0, Number(option()?.dataset.stock || 0) - reservedQuantity(product.value, editing));
    const showError = message => {
        error.textContent = message || '';
        error.classList.toggle('d-none', !message);
        quantity.setCustomValidity(message || '');
        quantity.classList.toggle('is-invalid', Boolean(message));
    };
    const validEntry = () => {
        const requested = Number(quantity.value || 0);
        if (!option()?.value) return false;
        if (!Number.isInteger(requested) || requested < 1) {
            showError('Quantity must be at least 1.');
            return false;
        }
        if (requested > available()) {
            showError('Insufficient stock. Only ' + Number(option().dataset.stock || 0) + ' units are available.');
            return false;
        }
        if (!Number.isInteger(Number(itemDiscount.value || 0)) || !Number.isInteger(Number(itemTax.value || 0)) || Number(itemDiscount.value || 0) > 100 || Number(itemTax.value || 0) > 100) {
            showError('Discount and tax must be whole numbers between 0 and 100.');
            return false;
        }
        showError('');
        return true;
    };
    const syncEntry = () => {
        const selected = option();
        if (!selected?.value) {
            stock.value = '';
            price.value = '';
            lineTotal.value = 'Rs 0.00';
            return;
        }
        quantity.max = String(available());
        stock.value = String(available()) + ' ' + (selected.dataset.unit || '');
        price.value = selected.dataset.price || 0;
        const values = lineAmounts(Number(quantity.value || 0), Number(price.value || 0), rate(itemDiscount), rate(itemTax));
        lineTotal.value = money(values.total);
        validEntry();
    };
    const indexFields = () => rows().forEach((row, index) => row.querySelectorAll('[data-order-field]').forEach(field => {
        field.name = 'products[' + index + '][' + field.dataset.orderField + ']';
    }));
    const render = () => {
        let subtotal = 0;
        rows().forEach((row, index) => {
            const qty = Number(row.querySelector('[data-order-qty]').value || 0);
            const values = lineAmounts(qty, Number(row.dataset.price || 0), Number(row.querySelector('[data-order-discount]').value || 0), Number(row.querySelector('[data-order-tax]').value || 0));
            subtotal += values.total;
            row.querySelector('[data-index]').textContent = index + 1;
            row.querySelector('[data-order-qty-label]').textContent = qty;
            row.querySelector('[data-order-discount-label]').textContent = Number(row.querySelector('[data-order-discount]').value || 0) + '%';
            row.querySelector('[data-order-tax-label]').textContent = Number(row.querySelector('[data-order-tax]').value || 0) + '%';
            row.querySelector('[data-order-line-total]').textContent = money(values.total);
        });
        body.querySelector('[data-sale-empty]')?.classList.toggle('d-none', rows().length > 0);
        const discountRate = rate(saleDiscount);
        const taxRate = rate(saleTax);
        const discountAmount = subtotal * discountRate / 100;
        const taxAmount = (subtotal - discountAmount) * taxRate / 100;
        form.querySelector('[data-order-subtotal]').textContent = money(subtotal);
        form.querySelector('[data-order-discount-label]').textContent = discountRate + '%';
        form.querySelector('[data-order-discount-amount]').textContent = money(discountAmount);
        form.querySelector('[data-order-tax-amount]').textContent = money(taxAmount);
        form.querySelector('[data-order-grand-total]').textContent = money(subtotal - discountAmount + taxAmount);
        indexFields();
    };
    const reset = () => {
        editing = null;
        product.value = '';
        quantity.value = 0;
        itemDiscount.value = 0;
        itemTax.value = 0;
        stock.value = '';
        price.value = '';
        lineTotal.value = 'Rs 0.00';
        showError('');
        window.syncTradeFlowTomSelect?.(product);
    };
    const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
    const saveEntry = event => {
        event.preventDefault();
        event.stopImmediatePropagation();
        if (!option()?.value) {
            showError('Select a product.');
            return;
        }
        if (!validEntry()) return;
        const selected = option();
        const row = editing || document.createElement('tr');
        row.dataset.orderLine = '';
        row.dataset.productId = selected.value;
        row.dataset.price = selected.dataset.price || 0;
        row.innerHTML = '<td data-index></td><td>' + escapeHtml(selected.text) + '<input type="hidden" data-order-field="id" value="' + selected.value + '"></td><td data-order-qty-label></td><td>Rs ' + Number(selected.dataset.price || 0).toLocaleString() + '<input type="hidden" data-order-field="quantity" data-order-qty value="' + quantity.value + '"></td><td data-order-discount-label><input type="hidden" data-order-field="discount_rate" data-order-discount value="' + rate(itemDiscount) + '"></td><td data-order-tax-label><input type="hidden" data-order-field="tax_rate" data-order-tax value="' + rate(itemTax) + '"></td><td data-order-line-total></td><td><button type="button" class="btn btn-sm btn-outline-primary" data-order-enhanced-edit>Edit</button></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-order-enhanced-delete>Delete</button></td>';
        if (!editing) body.appendChild(row);
        render();
        reset();
    };

    addButton.addEventListener('click', saveEntry, true);
    [product, quantity, itemDiscount, itemTax].forEach(input => input?.addEventListener('input', syncEntry));
    product.addEventListener('change', syncEntry);
    [saleDiscount, saleTax].forEach(input => input?.addEventListener('input', render));
    body.addEventListener('click', event => {
        const row = event.target.closest('[data-order-line]');
        if (!row) return;
        if (event.target.closest('[data-order-enhanced-delete]')) {
            event.stopImmediatePropagation();
            if (editing === row) reset();
            row.remove();
            render();
        }
        if (event.target.closest('[data-order-enhanced-edit]')) {
            event.stopImmediatePropagation();
            editing = row;
            product.value = row.dataset.productId;
            quantity.value = row.querySelector('[data-order-qty]').value;
            itemDiscount.value = row.querySelector('[data-order-discount]').value;
            itemTax.value = row.querySelector('[data-order-tax]').value;
            window.syncTradeFlowTomSelect?.(product);
            syncEntry();
        }
    }, true);
    const previousProducts = @json(old('products', []));
    previousProducts.forEach(line => {
        const previousOption = [...product.options].find(item => item.value === String(line.id || ''));
        if (!previousOption) return;

        product.value = previousOption.value;
        quantity.value = line.quantity || 1;
        itemDiscount.value = line.discount_rate || 0;
        itemTax.value = line.tax_rate || 0;
        syncEntry();
        saveEntry(new Event('submit', { cancelable: true }));
    });

    form.addEventListener('submit', event => {
        if (!rows().length) {
            event.preventDefault();
            showError('Please add at least one sale item.');
            window.getTradeFlowTomSelect?.(product)?.focus();
            return;
        }

        const isValid = rows().every(row => {
            const item = [...product.options].find(option => option.value === row.dataset.productId);
            const lineQuantity = Number(row.querySelector('[data-order-qty]')?.value || 0);

            return item && Number.isInteger(lineQuantity) && lineQuantity > 0 && lineQuantity <= Number(item.dataset.stock || 0);
        });
        if (!isValid) {
            event.preventDefault();
            showError('One or more sale items no longer have enough stock.');
            return;
        }
        if (form.dataset.submitting === '1') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = '1';
        const submit = event.submitter || form.querySelector('button[type="submit"], button:not([type])');
        if (submit) {
            submit.disabled = true;
            submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Creating...';
        }
    });
    syncEntry();
    render();
});
</script>
@endpush
