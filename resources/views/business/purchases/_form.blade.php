@php
    $purchase = $purchase ?? null;
    $isEditing = (bool) $purchase;
    $permissionService = app(\App\Services\CompanyPermissionService::class);
    $canCreateSupplier = $permissionService->allowsUser(auth()->user(), 'suppliers.create');
    $initialItems = old('items', $purchase?->items?->map(fn($item) => [
        'product_id' => $item->product_id,
        'quantity' => $item->quantity,
        'unit_cost' => $item->unit_cost,
        'discount_type' => $item->discount_type,
        'discount_value' => $item->discount_value,
        'tax_type' => $item->tax_type,
        'tax_value' => $item->tax_value,
    ])->values()->all() ?? []);
    $submissionToken = old('submission_token', $purchase?->submission_token ?? (string) \Illuminate\Support\Str::uuid());
@endphp
<form method="POST"
    action="{{ $isEditing ? route('business.purchases.update', $purchase) : route('business.purchases.store') }}"
    class="tf-card p-4" data-purchase-form
    data-purchase-quick-supplier-url="{{ route('business.purchases.suppliers.store') }}"
    data-can-create-supplier="{{ $canCreateSupplier ? '1' : '0' }}">
    @csrf
    @if($isEditing) @method('PUT') @endif
    <input type="hidden" name="submission_token" value="{{ $submissionToken }}">

    <section class="mb-4">
        <h2 class="h5 mb-3">Supplier and document details</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Supplier <span class="text-danger">*</span></label>
                <select name="supplier_id" class="form-select js-select2" required autofocus data-purchase-supplier>
                    <option value="">{{ $suppliers->isEmpty() ? 'No suppliers available' : 'Select Supplier' }}</option>
                    @if($canCreateSupplier)
                    <option value="__create__">Create New Supplier</option>@endif
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id', $purchase?->supplier_id) == $supplier->id)>
                            {{ $supplier->supplier_name }}{{ $supplier->company_name ? ' - ' . $supplier->company_name : '' }}
                    </option>@endforeach
                </select>
                @if($suppliers->isEmpty() && !$canCreateSupplier)
                    <div class="form-text text-warning">No suppliers are available. Contact an authorized user to create
                one.</div>@endif
            </div>
            <div class="col-md-3"><label class="form-label">Purchase date <span
                        class="text-danger">*</span></label><input name="purchase_date" type="datetime-local"
                    value="{{ old('purchase_date', $purchase?->purchase_date?->format('Y-m-d\\TH:i') ?? now()->format('Y-m-d\\TH:i')) }}"
                    class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Supplier invoice date</label><input
                    name="supplier_invoice_date" type="date"
                    value="{{ old('supplier_invoice_date', $purchase?->supplier_invoice_date?->toDateString() ?? now()->toDateString()) }}"
                    class="form-control" data-invoice-date></div>
            <div class="col-md-3"><label class="form-label">Payment terms</label><select name="payment_terms"
                    class="form-select"
                    data-payment-terms>@foreach(['Cash', 'Due on Receipt', 'Net 7', 'Net 15', 'Net 30', 'Custom'] as $term)
                        <option @selected(old('payment_terms', $purchase?->payment_terms ?? 'Due on Receipt') === $term)>
                            {{ $term }}
                    </option>@endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label">Due date</label><input name="due_date" type="date"
                    value="{{ old('due_date', $purchase?->due_date?->toDateString() ?? now()->toDateString()) }}"
                    class="form-control" data-due-date readonly></div>
        </div>
    </section>

    <section class="border rounded p-3 mb-3 bg-light" aria-label="Add purchase item">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">Purchase items</h2><small class="tf-muted">Costs, discount and tax are recalculated on
                the server.</small>
        </div>
        <div class="row g-2 align-items-end">
            <div class="col-xl-3"><label class="form-label">Product</label><select class="form-select js-select2"
                    data-purchase-entry-product>
                    <option value="">Select Product</option>@foreach($products as $product)
                        <option value="{{ $product->id }}" data-cost="{{ $product->purchase_cost ?? 0 }}"
                            data-stock="{{ $product->stock_quantity }}" data-unit="{{ $product->unit }}">
                            {{ $product->name }}
                    </option>@endforeach
                </select></div>
            <div class="col-xl-1"><label class="form-label">Current stock</label><input class="form-control"
                    data-purchase-entry-stock readonly></div>
            <div class="col-xl-1"><label class="form-label">Quantity</label><input type="number" min="0" step="1"
                    value="0" class="form-control js-whole-number js-no-number-spinner js-no-wheel-change" data-purchase-entry-qty>
            </div>
            <div class="col-xl-2"><label class="form-label">Purchase cost</label><input type="number" min="0" step="1"
                    class="form-control js-whole-number js-no-number-spinner js-no-wheel-change" data-purchase-entry-cost></div>
            <div class="col-xl-2"><label class="form-label">Discount</label>
                <div class="input-group tf-adjustment-group"><select class="form-select tf-adjustment-type"
                        data-purchase-entry-discount-type>
                        <option value="percentage">%</option>
                        <option value="fixed">Rs</option>
                    </select><input type="number" min="0" step="1" value="0"
                        class="form-control js-whole-number js-no-number-spinner js-no-wheel-change" data-purchase-entry-discount-value>
                </div>
            </div>
            <div class="col-xl-2"><label class="form-label">Tax</label>
                <div class="input-group tf-adjustment-group"><select class="form-select tf-adjustment-type"
                        data-purchase-entry-tax-type>
                        <option value="percentage">%</option>
                        <option value="fixed">Rs</option>
                    </select><input type="number" min="0" step="1" value="0"
                        class="form-control js-whole-number js-no-number-spinner js-no-wheel-change" data-purchase-entry-tax-value>
                </div>
            </div>
            <div class="col-xl-1 d-grid"><button type="button" class="btn btn-tf-primary" data-add-purchase-item
                    aria-label="Add item"><i class="bi bi-check-lg"></i></button></div>
        </div>
        <div class="invalid-feedback d-block d-none mt-2" data-purchase-entry-error></div>
    </section>

    <div class="table-responsive border rounded">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Purchase Cost</th>
                    <th>Discount</th>
                    <th>Tax</th>
                    <th>Line Total</th>
                    <th>Edit</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody data-purchase-items>
                <tr data-purchase-empty>
                    <td colspan="10" class="text-center tf-muted py-4">No purchase items added yet.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <section class="mt-3 text-lg-end">
        <div><span class="me-3">Subtotal <strong data-purchase-subtotal>Rs 0.00</strong></span><span
                class="me-3">Discount <strong data-purchase-discount>Rs 0.00</strong></span><span class="me-3">Tax
                <strong data-purchase-tax>Rs 0.00</strong></span><span>Grand total <strong class="fs-5"
                    data-purchase-total>Rs 0.00</strong></span></div>
    </section>

    <section class="border rounded p-3 mt-4" aria-label="Payment details">
        <h2 class="h5 mb-3">Payment details</h2>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label d-block">Payment type</label>
                <div class="btn-group w-100" role="group">
                    @foreach(['Full Credit', 'Partial Payment', 'Full Payment'] as $type)<input type="radio"
                        class="btn-check" name="payment_type" id="paymentType{{ Str::slug($type) }}" value="{{ $type }}"
                        data-payment-type @checked(old('payment_type', $purchase?->paid_amount > 0 ? ($purchase->balance > 0 ? 'Partial Payment' : 'Full Payment') : 'Full Credit') === $type)><label
                        class="btn btn-outline-primary"
                    for="paymentType{{ Str::slug($type) }}">{{ $type }}</label>@endforeach
                </div>
            </div>
            <div class="col-md-2"><label class="form-label">Grand total</label><input class="form-control"
                    data-payment-grand-total readonly></div>
            <div class="col-md-2"><label class="form-label">Amount paid now</label><input name="paid_amount"
                    type="number" min="0" step="1" value="{{ old('paid_amount', $purchase?->paid_amount ?? 0) }}"
                    class="form-control js-whole-number" data-paid-amount></div>
            <div class="col-md-2"><label class="form-label">Remaining payable</label><input class="form-control"
                    data-payment-balance readonly></div>
            <div class="col-md-2"><label class="form-label">Payment method</label>
            <select name="payment_method"
                    class="form-select" data-payment-method>
                    <option value="">Select-method</option>
                    @foreach(['Cash', 'Bank Transfer', 'JazzCash', 'Easypaisa', 'Cheque', 'Other'] as $method)
                        <option @selected(old('payment_method', $purchase?->payment_method) === $method)>{{ $method }}
                    </option>@endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label">Payment date</label><input name="payment_date" type="date"
                    value="{{ old('payment_date', $purchase?->payment_date?->toDateString() ?? now()->toDateString()) }}"
                    class="form-control"></div>
            @if($purchase?->payment_account_id)
                <input type="hidden" name="payment_account_id" value="{{ $purchase->payment_account_id }}">
            @endif
            <div class="col-md-3 d-none" data-cheque-fields><label class="form-label">Cheque number</label><input
                    name="cheque_number" value="{{ old('cheque_number', $purchase?->cheque_number) }}"
                    class="form-control"><label class="form-label mt-2">Cheque due date</label><input
                    name="cheque_due_date" type="date"
                    value="{{ old('cheque_due_date', $purchase?->cheque_due_date?->toDateString()) }}"
                    class="form-control"></div>
        </div>
    </section>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-4"><a
            href="{{ route('business.purchases.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <div><button class="btn btn-tf-primary" data-purchase-submit>Confirm Purchase</button></div>
    </div>
</form>

@if($canCreateSupplier)
    <div class="modal fade" id="purchaseSupplierModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form data-purchase-supplier-form>
                    <div class="modal-header">
                        <h2 class="modal-title fs-5">Create New Supplier</h2><button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" data-supplier-modal-errors></div>
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label">Supplier name <span
                                        class="text-danger">*</span></label><input name="supplier_name" class="form-control"
                                    required></div>
                            <div class="col-12"><label class="form-label">Company name</label><input name="company_name"
                                    class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Phone</label><x-phone-input name="phone"
                                    id="purchase-supplier-phone" /></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email"
                                    class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">City</label><input name="city"
                                    class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Opening payable balance</label><input
                                    name="opening_balance" type="number" min="0" step="any" value="0" class="form-control">
                            </div>
                            <div class="col-12"><label class="form-label">Address</label><textarea name="address"
                                    class="form-control" rows="2"></textarea></div><input type="hidden" name="status"
                                value="Active">
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancel</button><button class="btn btn-tf-primary"
                            data-create-supplier>Save Supplier</button></div>
                </form>
            </div>
        </div>
    </div>
@endif

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.querySelector('[data-purchase-form]');
                if (!form || form.dataset.purchaseReady === '1') return;
                form.dataset.purchaseReady = '1';
                const $ = selector => form.querySelector(selector);
                const body = $('[data-purchase-items]'); const supplier = $('[data-purchase-supplier]'); const product = $('[data-purchase-entry-product]');
                const stock = $('[data-purchase-entry-stock]'); const qty = $('[data-purchase-entry-qty]'); const cost = $('[data-purchase-entry-cost]');
                const discountType = $('[data-purchase-entry-discount-type]'); const discountValue = $('[data-purchase-entry-discount-value]'); const taxType = $('[data-purchase-entry-tax-type]'); const taxValue = $('[data-purchase-entry-tax-value]');
                const error = $('[data-purchase-entry-error]'); const paymentType = () => form.querySelector('[data-payment-type]:checked')?.value || 'Full Credit'; const paidAmount = $('[data-paid-amount]'); const paymentMethod = $('[data-payment-method]');
                const number = value => Math.max(0, Number.parseFloat(value || 0) || 0); const whole = value => String(Math.round(number(value))); const money = value => { const amount = number(value); return `Rs ${amount.toLocaleString(undefined, { minimumFractionDigits: Number.isInteger(amount) ? 0 : 2, maximumFractionDigits: 2 })}`; }; const quantityLabel = value => whole(value); const rows = () => [...body.querySelectorAll('[data-purchase-row]')];
                const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[char]));
                let editing = null;
                const lineAmounts = item => { const value = item || { quantity: number(qty.value), unit_cost: number(cost.value), discount_type: discountType.value, discount_value: number(discountValue.value), tax_type: taxType.value, tax_value: number(taxValue.value) }; const subtotal = value.quantity * value.unit_cost; const discount = Math.min(subtotal, value.discount_type === 'percentage' ? subtotal * value.discount_value / 100 : value.discount_value); const taxable = subtotal - discount; const tax = value.tax_type === 'percentage' ? taxable * value.tax_value / 100 : value.tax_value; return { subtotal, discount, tax, total: taxable + tax }; };
                const adjustment = (type, value) => type === 'percentage' ? `${value}%` : money(value);
                const setError = message => { error.textContent = message; error.classList.remove('d-none'); }; const clearError = () => error.classList.add('d-none');
                const updateAvailability = () => { const ready = Boolean(supplier.value) && supplier.value !== '__create__';[product, qty, cost, discountType, discountValue, taxType, taxValue, $('[data-add-purchase-item]')].forEach(input => { if (input) input.disabled = !ready; }); const control = window.getTradeFlowTomSelect?.(product); ready ? control?.enable() : control?.disable(); };
                const refreshOptions = () => { const selected = new Set(rows().filter(row => row !== editing).map(row => row.dataset.productId));[...product.options].forEach(option => { if (option.value) option.disabled = selected.has(option.value); }); window.getTradeFlowTomSelect?.(product)?.refreshOptions(false); };
                const writeRow = (row, item) => { item = { ...item, quantity: whole(item.quantity), unit_cost: whole(item.unit_cost), discount_value: whole(item.discount_value), tax_value: whole(item.tax_value) }; const amounts = lineAmounts(item); const itemQuantity = quantityLabel(item.quantity); row.dataset.purchaseRow = ''; row.dataset.productId = item.product_id; row.dataset.total = amounts.total; row.dataset.subtotal = amounts.subtotal; row.dataset.discount = amounts.discount; row.dataset.tax = amounts.tax; row.innerHTML = `<td data-index></td><td>${escapeHtml(item.product_name)}<input type="hidden" name="items[0][product_id]" value="${item.product_id}"></td><td>${itemQuantity}<input type="hidden" name="items[0][quantity]" value="${itemQuantity}"></td><td>${escapeHtml(item.unit || 'Unit')}</td><td>${money(item.unit_cost)}<input type="hidden" name="items[0][unit_cost]" value="${item.unit_cost}"></td><td>${adjustment(item.discount_type, item.discount_value)}<input type="hidden" name="items[0][discount_type]" value="${item.discount_type}"><input type="hidden" name="items[0][discount_value]" value="${item.discount_value}"></td><td>${adjustment(item.tax_type, item.tax_value)}<input type="hidden" name="items[0][tax_type]" value="${item.tax_type}"><input type="hidden" name="items[0][tax_value]" value="${item.tax_value}"></td><td>${money(amounts.total)}</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-edit-purchase-item>Edit</button></td><td><button type="button" class="btn btn-sm btn-outline-danger" data-delete-purchase-item>Delete</button></td>`; };
                const render = () => { let subtotal = 0, discount = 0, tax = 0; rows().forEach((row, index) => { row.querySelector('[data-index]').textContent = index + 1; row.querySelectorAll('[name]').forEach(input => input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`)); subtotal += number(row.dataset.subtotal); discount += number(row.dataset.discount); tax += number(row.dataset.tax); }); const grand = subtotal - discount + tax; $('[data-purchase-empty]')?.classList.toggle('d-none', rows().length > 0); $('[data-purchase-subtotal]').textContent = money(subtotal); $('[data-purchase-discount]').textContent = money(discount); $('[data-purchase-tax]').textContent = money(tax); $('[data-purchase-total]').textContent = money(grand); $('[data-payment-grand-total]').value = money(grand); if (paymentType() === 'Full Payment') paidAmount.value = whole(grand); if (paymentType() === 'Full Credit') paidAmount.value = '0'; $('[data-payment-balance]').value = money(Math.max(0, grand - number(paidAmount.value))); refreshOptions(); };
                const reset = () => { editing = null; const productControl = window.getTradeFlowTomSelect?.(product); if (productControl) { productControl.clear(true); productControl.close(); } else { product.value = ''; } qty.value = '0'; cost.value = ''; discountType.value = 'percentage'; discountValue.value = '0'; taxType.value = 'percentage'; taxValue.value = '0'; stock.value = ''; clearError(); refreshOptions(); };
                const sync = () => { const option = product.selectedOptions[0]; stock.value = option?.value ? `${quantityLabel(option.dataset.stock)} ${option.dataset.unit || ''}` : ''; if (product.value && !cost.value) cost.value = option.dataset.cost || 0; };
                const add = () => { if (!supplier.value || supplier.value === '__create__') { setError('Please select or create a supplier before adding a purchase item.'); return; } const option = product.selectedOptions[0]; const amounts = lineAmounts(); const editableValues = [qty, cost, discountValue, taxValue]; if (editableValues.some(input => !/^\d+$/.test(String(input.value).replace(/,/g, '')))) { setError('Only whole numbers are allowed.'); return; } if (!product.value || number(qty.value) < 1 || number(cost.value) < 0 || discountType.value === 'percentage' && number(discountValue.value) > 100 || taxType.value === 'percentage' && number(taxValue.value) > 100 || amounts.discount > amounts.subtotal) { setError('Select a product and enter valid quantity, cost, discount and tax values.'); return; } const existing = rows().find(row => row.dataset.productId === product.value); const target = editing || existing || document.createElement('tr'); const existingQty = existing && existing !== editing ? number(existing.querySelector('[name$="[quantity]"]').value) : 0; writeRow(target, { product_id: product.value, product_name: option.text, unit: option.dataset.unit || 'Unit', quantity: whole(existingQty + number(qty.value)), unit_cost: whole(cost.value), discount_type: discountType.value, discount_value: whole(discountValue.value), tax_type: taxType.value, tax_value: whole(taxValue.value) }); if (!editing && !existing) body.appendChild(target); render(); reset(); };
                const syncDueDate = () => { const terms = form.querySelector('[data-payment-terms]'); const date = form.querySelector('[data-invoice-date]').value || form.querySelector('[name="purchase_date"]').value.slice(0, 10); const due = form.querySelector('[data-due-date]'); const days = { 'Cash': 0, 'Due on Receipt': 0, 'Net 7': 7, 'Net 15': 15, 'Net 30': 30 }[terms.value]; due.readOnly = terms.value !== 'Custom'; if (days !== undefined && date) { const value = new Date(`${date}T00:00:00`); value.setDate(value.getDate() + days); due.value = value.toISOString().slice(0, 10); } };
                supplier.addEventListener('change', () => { if (supplier.value === '__create__') { window.getTradeFlowTomSelect?.(supplier)?.setValue(''); const modal = document.querySelector('#purchaseSupplierModal'); if (modal && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(modal).show(); return; } updateAvailability(); }); product.addEventListener('change', sync);[qty, cost, discountValue, taxValue, discountType, taxType].forEach(input => input.addEventListener('input', () => { sync(); })); paidAmount.addEventListener('input', render); form.querySelectorAll('[data-payment-type]').forEach(input => input.addEventListener('change', () => { paidAmount.readOnly = paymentType() !== 'Partial Payment'; render(); })); paymentMethod.addEventListener('change', () => form.querySelector('[data-cheque-fields]').classList.toggle('d-none', paymentMethod.value !== 'Cheque')); form.querySelector('[data-payment-terms]').addEventListener('change', syncDueDate); form.querySelector('[data-invoice-date]').addEventListener('change', syncDueDate); form.querySelector('[name="purchase_date"]').addEventListener('change', syncDueDate); form.querySelector('[data-add-purchase-item]').addEventListener('click', add);
                body.addEventListener('click', event => { const row = event.target.closest('[data-purchase-row]'); if (!row) return; if (event.target.closest('[data-delete-purchase-item]')) { if (editing === row) reset(); row.remove(); render(); return; } if (event.target.closest('[data-edit-purchase-item]')) { editing = row; product.value = row.dataset.productId; qty.value = row.querySelector('[name$="[quantity]"]').value; cost.value = row.querySelector('[name$="[unit_cost]"]').value; discountType.value = row.querySelector('[name$="[discount_type]"]').value; discountValue.value = row.querySelector('[name$="[discount_value]"]').value; taxType.value = row.querySelector('[name$="[tax_type]"]').value; taxValue.value = row.querySelector('[name$="[tax_value]"]').value; refreshOptions(); window.syncTradeFlowTomSelect?.(product); sync(); clearError(); } });
                form.addEventListener('submit', event => { if (!supplier.value || supplier.value === '__create__') { event.preventDefault(); setError('Please select a supplier before saving this purchase.'); return; } if (!rows().length) { event.preventDefault(); setError('Please add at least one purchase item.'); return; } form.querySelectorAll('[data-purchase-submit]').forEach(button => button.disabled = true); });
                @foreach($initialItems as $item)
                    { const option = [...product.options].find(candidate => String(candidate.value) === String(@json($item['product_id']))); if (option) { const row = document.createElement('tr'); writeRow(row, { product_id: @json($item['product_id']), product_name: option.text, unit: option.dataset.unit || 'Unit', quantity: @json($item['quantity']), unit_cost: @json($item['unit_cost']), discount_type: @json($item['discount_type'] ?? 'fixed'), discount_value: @json($item['discount_value'] ?? 0), tax_type: @json($item['tax_type'] ?? 'fixed'), tax_value: @json($item['tax_value'] ?? 0) }); body.appendChild(row); } }
                @endforeach
                    const supplierForm = document.querySelector('[data-purchase-supplier-form]'); if (supplierForm) { supplierForm.addEventListener('submit', async event => { event.preventDefault(); const submit = supplierForm.querySelector('[data-create-supplier]'); submit.disabled = true; const errors = supplierForm.querySelector('[data-supplier-modal-errors]'); errors.classList.add('d-none'); try { const response = await fetch(form.dataset.purchaseQuickSupplierUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value }, body: new FormData(supplierForm) }); const payload = await response.json(); if (!response.ok) throw payload; const item = payload.supplier; const label = item.company_name ? `${item.supplier_name} - ${item.company_name}` : item.supplier_name; const select = window.getTradeFlowTomSelect?.(supplier); if (select) { select.addOption({ value: item.id, text: label }); select.setValue(item.id); } else { supplier.add(new Option(label, item.id, true, true)); } updateAvailability(); window.bootstrap.Modal.getInstance(document.querySelector('#purchaseSupplierModal'))?.hide(); window.Swal?.fire({ icon: 'success', title: 'Supplier created', timer: 1400, showConfirmButton: false }); } catch (payload) { const messages = Object.values(payload.errors || { supplier: ['Unable to create supplier.'] }).flat(); errors.textContent = messages.join(' '); errors.classList.remove('d-none'); } finally { submit.disabled = false; } }); }
                sync(); syncDueDate(); render(); updateAvailability(); paymentMethod.dispatchEvent(new Event('change'));
            });
        </script>
    @endpush
@endonce
