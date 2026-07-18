<form method="POST" action="{{ route('business.purchases.store') }}" class="tf-card p-4" data-purchase-form>
    @csrf
    <input type="hidden" name="discount_amount" value="0" data-purchase-discount-total>
    <input type="hidden" name="tax_amount" value="0" data-purchase-tax-total>

    <div class="row g-3 mb-4">
        <div class="col-md-6"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select js-select2" required autofocus><option value="">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->supplier_name }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Purchase date</label><input name="purchase_date" type="datetime-local" value="{{ old('purchase_date', now()->format('Y-m-d\\TH:i')) }}" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
    </div>

    <section class="border rounded p-3 mb-3 bg-light" aria-label="Add purchase item">
        <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">Add purchase item</h2><small class="tf-muted">Discount and tax are applied per item.</small></div>
        <div class="row g-2 align-items-end">
            <div class="col-lg-2"><label class="form-label">Product</label><select class="form-select js-select2" data-purchase-entry-product><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-cost="{{ $product->purchase_cost ?: $product->wholesale_price }}" data-selling="{{ $product->retail_price ?: $product->wholesale_price }}" data-stock="{{ $product->stock_quantity }}" data-unit="{{ $product->unit }}">{{ $product->name }}</option>@endforeach</select></div>
            <div class="col-lg-1"><label class="form-label">Current Stock</label><input class="form-control" data-purchase-entry-stock readonly></div>
            <div class="col-lg-1"><label class="form-label">Qty</label><input type="number" min="1" step="1" value="1" class="form-control" data-purchase-entry-qty></div>
            <div class="col-lg-2"><label class="form-label">Purchase Price</label><input type="number" min="0" step="1" class="form-control" data-purchase-entry-cost data-whole-number></div>
            <div class="col-lg-2"><label class="form-label">Retail / Selling Price</label><input type="number" min="0" step="1" class="form-control" data-purchase-entry-selling-price data-whole-number></div>
            <div class="col-lg-1"><label class="form-label">Discount</label><input type="number" min="0" step="1" value="0" class="form-control" data-purchase-entry-discount data-whole-number></div>
            <div class="col-lg-1"><label class="form-label">Tax</label><input type="number" min="0" step="1" value="0" class="form-control" data-purchase-entry-tax data-whole-number></div>
            <div class="col-lg-1"><label class="form-label">Line Total</label><input class="form-control" data-purchase-entry-total value="Rs 0.00" readonly></div>
            <div class="col-lg-1 d-grid"><button type="button" class="btn btn-tf-primary" data-add-purchase-item aria-label="Add item"><i class="bi bi-check-lg"></i></button></div>
        </div>
        <div class="invalid-feedback d-block d-none mt-2" data-purchase-entry-error></div>
    </section>

    <div class="table-responsive border rounded"><table class="table align-middle mb-0"><thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Purchase Price</th><th>Retail / Selling Price</th><th>Discount</th><th>Tax</th><th>Line Total</th><th>Edit</th><th>Delete</th></tr></thead><tbody data-purchase-items><tr data-purchase-empty><td colspan="10" class="text-center tf-muted py-4">No purchase items added yet.</td></tr></tbody></table></div>
    <div class="d-flex justify-content-between align-items-center mt-3"><strong>Total <span data-purchase-total>Rs 0.00</span></strong><div class="d-flex gap-2"><a href="{{ route('business.purchases.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-tf-primary">Save purchase order</button></div></div>
</form>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-purchase-form]');
    if (!form || form.dataset.purchaseReady === '1') return;
    form.dataset.purchaseReady = '1';

    const body = form.querySelector('[data-purchase-items]');
    const product = form.querySelector('[data-purchase-entry-product]');
    const stock = form.querySelector('[data-purchase-entry-stock]');
    const qty = form.querySelector('[data-purchase-entry-qty]');
    const cost = form.querySelector('[data-purchase-entry-cost]');
    const sellingPrice = form.querySelector('[data-purchase-entry-selling-price]');
    const discount = form.querySelector('[data-purchase-entry-discount]');
    const tax = form.querySelector('[data-purchase-entry-tax]');
    const total = form.querySelector('[data-purchase-entry-total]');
    const error = form.querySelector('[data-purchase-entry-error]');
    const discountTotal = form.querySelector('[data-purchase-discount-total]');
    const taxTotal = form.querySelector('[data-purchase-tax-total]');
    const initialItems = @json(array_values(old('items', [])));
    let editing = null;

    const money = value => `Rs ${Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
    const whole = input => Math.max(0, parseInt(input.value || '0', 10) || 0);
    const rows = () => [...body.querySelectorAll('[data-purchase-row]')];
    const lineAmounts = () => {
        const subtotal = Math.max(0, Number(qty.value) || 0) * Math.max(0, Number(cost.value) || 0);
        const lineDiscount = Math.min(subtotal, whole(discount));
        const lineTax = whole(tax);

        return { subtotal, discount: lineDiscount, tax: lineTax, total: subtotal - lineDiscount + lineTax };
    };
    const showError = message => { error.textContent = message; error.classList.remove('d-none'); };
    const clearError = () => error.classList.add('d-none');
    const refreshProductOptions = () => {
        const selectedIds = new Set(rows().filter(row => row !== editing).map(row => row.dataset.productId));
        [...product.options].forEach(option => {
            if (!option.value) return;
            option.disabled = selectedIds.has(option.value);
        });
        const control = window.getTradeFlowTomSelect?.(product);
        control?.refreshOptions(false);
    };
    const sync = () => {
        const option = product.selectedOptions[0];
        stock.value = option?.value ? `${option.dataset.stock} ${option.dataset.unit || ''}` : '';
        if (product.value && !cost.value) cost.value = option?.dataset.cost || 0;
        if (product.value && !sellingPrice.value) sellingPrice.value = option?.dataset.selling || 0;
        total.value = money(lineAmounts().total);
    };
    const writeRow = (row, item) => {
        const amounts = {
            subtotal: Math.max(0, Number(item.quantity) || 0) * Math.max(0, Number(item.unit_cost) || 0),
            discount: Math.min(Math.max(0, Number(item.quantity) || 0) * Math.max(0, Number(item.unit_cost) || 0), Math.max(0, Number(item.discount_amount) || 0)),
            tax: Math.max(0, Number(item.tax_amount) || 0),
        };
        amounts.total = amounts.subtotal - amounts.discount + amounts.tax;
        row.dataset.purchaseRow = '';
        row.dataset.productId = item.product_id;
        row.dataset.total = amounts.total;
        row.dataset.discount = amounts.discount;
        row.dataset.tax = amounts.tax;
        row.innerHTML = `<td data-index></td><td><span>${escapeHtml(item.product_name)}</span><input type="hidden" name="items[0][product_id]" value="${item.product_id}"></td><td>${item.quantity}<input type="hidden" name="items[0][quantity]" value="${item.quantity}"></td><td>${money(item.unit_cost)}<input type="hidden" name="items[0][unit_cost]" value="${item.unit_cost}"></td><td>${money(item.selling_price)}<input type="hidden" name="items[0][selling_price]" value="${item.selling_price}"></td><td>${money(amounts.discount)}<input type="hidden" name="items[0][discount_amount]" value="${amounts.discount}"></td><td>${money(amounts.tax)}<input type="hidden" name="items[0][tax_amount]" value="${amounts.tax}"></td><td>${money(amounts.total)}</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-edit-purchase-item>Edit</button></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-delete-purchase-item>Delete</button></td>`;
    };
    const render = () => {
        let grandTotal = 0;
        let totalDiscount = 0;
        let totalTax = 0;
        rows().forEach((row, index) => {
            row.querySelector('[data-index]').textContent = index + 1;
            row.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`));
            grandTotal += Number(row.dataset.total || 0);
            totalDiscount += Number(row.dataset.discount || 0);
            totalTax += Number(row.dataset.tax || 0);
        });
        body.querySelector('[data-purchase-empty]')?.classList.toggle('d-none', rows().length > 0);
        form.querySelector('[data-purchase-total]').textContent = money(grandTotal);
        discountTotal.value = totalDiscount;
        taxTotal.value = totalTax;
        refreshProductOptions();
    };
    const reset = () => {
        editing = null;
        product.value = '';
        qty.value = 1;
        cost.value = '';
        sellingPrice.value = '';
        discount.value = 0;
        tax.value = 0;
        stock.value = '';
        total.value = 'Rs 0.00';
        clearError();
        refreshProductOptions();
        window.syncTradeFlowTomSelect?.(product);
        setTimeout(() => window.getTradeFlowTomSelect?.(product)?.focus(), 0);
    };
    const add = () => {
        const option = product.selectedOptions[0];
        const entryValuesAreWhole = [qty, cost, sellingPrice, discount, tax].every(input => /^\d+$/.test(input.value));
        if (!product.value || !entryValuesAreWhole || Number(qty.value) < 1 || Number(cost.value) < 0 || Number(sellingPrice.value) <= Number(cost.value)) {
            showError(!entryValuesAreWhole ? 'Only whole numbers are allowed.' : (Number(sellingPrice.value) <= Number(cost.value) && cost.value !== '' ? 'Selling Price must be greater than Purchase Price.' : 'Select a product and enter a quantity of at least 1.'));
            return;
        }
        const existing = rows().find(row => row.dataset.productId === product.value);
        const target = editing || existing || document.createElement('tr');
        const existingQuantity = existing && existing !== editing ? Number(existing.querySelector('[name$="[quantity]"]').value) : 0;
        writeRow(target, {
            product_id: product.value,
            product_name: option.text,
            quantity: existingQuantity + Number(qty.value),
            unit_cost: cost.value,
            selling_price: sellingPrice.value,
            discount_amount: whole(discount),
            tax_amount: whole(tax),
        });
        if (!editing && !existing) body.appendChild(target);
        render();
        reset();
    };
    product.addEventListener('change', sync);
    [qty, cost, sellingPrice, discount, tax].forEach(input => input.addEventListener('input', sync));
    form.querySelector('[data-add-purchase-item]').addEventListener('click', add);
    body.addEventListener('click', event => {
        const row = event.target.closest('[data-purchase-row]');
        if (!row) return;
        if (event.target.closest('[data-delete-purchase-item]')) {
            if (editing === row) reset();
            row.remove();
            render();
            return;
        }
        if (event.target.closest('[data-edit-purchase-item]')) {
            editing = row;
            product.value = row.dataset.productId;
            qty.value = row.querySelector('[name$="[quantity]"]').value;
            cost.value = row.querySelector('[name$="[unit_cost]"]').value;
            sellingPrice.value = row.querySelector('[name$="[selling_price]"]').value;
            discount.value = row.querySelector('[name$="[discount_amount]"]').value;
            tax.value = row.querySelector('[name$="[tax_amount]"]').value;
            refreshProductOptions();
            window.syncTradeFlowTomSelect?.(product);
            sync();
            clearError();
            setTimeout(() => window.getTradeFlowTomSelect?.(product)?.focus(), 0);
        }
    });
    form.addEventListener('submit', event => {
        if (rows().length) return;
        event.preventDefault();
        showError('Please add at least one purchase item.');
        window.getTradeFlowTomSelect?.(product)?.focus();
    });

    initialItems.forEach(item => {
        const option = [...product.options].find(candidate => String(candidate.value) === String(item.product_id));
        if (!option) return;
        const row = document.createElement('tr');
        writeRow(row, {
            product_id: item.product_id,
            product_name: option.text,
            quantity: item.quantity,
            unit_cost: item.unit_cost,
            selling_price: item.selling_price ?? 0,
            discount_amount: item.discount_amount ?? 0,
            tax_amount: item.tax_amount ?? 0,
        });
        body.appendChild(row);
    });
    sync();
    render();
});
</script>
@endpush
@endonce
