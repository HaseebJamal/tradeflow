@extends('layouts.dashboard')

@section('title', 'POS | TradeFlow')
@section('page-title', 'Point of Sale')
@section('page-subtitle', 'Counter sales, receipts, and register control')

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @if (! $register)
        <div class="tf-card p-4 mx-auto" style="max-width: 680px;">
            <div class="d-flex gap-3 align-items-center mb-3">
                <span class="tf-brand-mark bg-blue"><i class="bi bi-cash-register"></i></span>
                <div>
                    <h2 class="h5 mb-1">Open POS Register</h2>
                    <p class="tf-muted mb-0">Record opening cash before processing counter sales.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('business.pos.register.open') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label" for="opening_cash">Opening Cash</label>
                    <input id="opening_cash" name="opening_cash" type="number" min="0" step="0.01" value="0" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="opening_note">Opening Note <span class="tf-muted">Optional</span></label>
                    <input id="opening_note" name="opening_note" class="form-control">
                </div>
                <div class="col-12">
                    @companyCan('pos.open_register')
                        <button class="btn btn-tf-primary"><i class="bi bi-unlock me-1"></i>Open Register</button>
                    @endcompanyCan
                </div>
            </form>
        </div>
    @else
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-3">
            <div>
                <span class="badge text-bg-success">Register Open</span>
                <span class="tf-muted small ms-2">
                    Opened <x-date-time :value="$register->opened_at" /> &middot; Opening cash Rs {{ number_format($register->opening_cash, 2) }}
                </span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('business.pos.history') }}" class="btn btn-outline-primary">Sales History</a>
                @companyCan('pos.reports')
                    <a href="{{ route('business.pos.report') }}" class="btn btn-outline-primary">POS Report</a>
                @endcompanyCan
                @companyCan('pos.close_register')
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#closeRegisterModal">Close Register</button>
                @endcompanyCan
            </div>
        </div>

        <form method="POST" action="{{ route('business.pos.sales.store') }}" data-pos-form data-custom-price="{{ app(\App\Services\CompanyPermissionService::class)->allowsUser(auth()->user(), 'pos.custom_price') ? '1' : '0' }}">
            @csrf
            <div class="row g-4">
                <div class="col-xl-7">
                    <div class="tf-card p-4 h-100">
                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                            <h2 class="h5 mb-0">Products</h2>
                            <div class="d-flex flex-wrap gap-2">
                                <input id="pos-barcode" class="form-control" placeholder="Scan barcode" autocomplete="off">
                                <input id="pos-search" class="form-control" placeholder="Search products">
                                <select id="pos-category" class="form-select" aria-label="Filter products by category"><option value="">All categories</option>@foreach($products->pluck('category.name')->filter()->unique()->sort() as $category)<option value="{{ strtolower($category) }}">{{ $category }}</option>@endforeach</select>
                            </div>
                        </div>

                        <div class="row g-3" id="pos-products">
                            @forelse ($products as $product)
                                @php
                                    $posProduct = [
                                        'id' => $product->id,
                                        'name' => $product->name,
                                        'sku' => $product->sku,
                                        'barcode' => $product->barcode,
                                        'price' => (float) $product->retail_price,
                                        'stock' => (int) $product->stock_quantity,
                                        'unit' => $product->unit,
                                    ];
                                @endphp
                                <div class="col-sm-6 col-lg-4 pos-product" data-search="{{ strtolower($product->name.' '.$product->sku.' '.$product->barcode) }}" data-category="{{ strtolower($product->category?->name ?? '') }}">
                                    <button type="button" class="btn p-3 border text-start w-100 h-100" data-pos-product="{{ e(json_encode($posProduct)) }}">
                                        <strong class="d-block text-truncate">{{ $product->name }}</strong>
                                        <small class="tf-muted d-block">{{ $product->sku ?: 'No SKU' }}</small>
                                        <span class="d-block mt-2">Rs {{ number_format($product->retail_price, 2) }}</span>
                                        <small class="{{ $product->stock_quantity > 0 ? 'text-success' : 'text-danger' }}">
                                            {{ $product->stock_quantity }} {{ $product->unit }} available
                                        </small>
                                    </button>
                                </div>
                            @empty
                                <div class="col-12 text-center tf-muted py-5">No active products are available for POS.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="tf-card p-4 h-100">
                        <h2 class="h5 mb-3">Current Sale</h2>

                        <div class="mb-3">
                            <label class="form-label" for="pos-customer">Customer</label>
                            <select name="customer_id" id="pos-customer" class="form-select">
                                <option value="walk_in">Walk-in Customer</option>
                                <option value="new">Create New Customer</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}">
                                        {{ $customer->business_name ?: $customer->name }}{{ $customer->phone ? ' - '.$customer->phone : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="pos-new-customer" class="row g-2 mb-3 d-none">
                            <div class="col-md-6"><input name="new_customer_name" class="form-control" placeholder="Customer name"></div>
                            <div class="col-md-6"><input name="new_customer_phone" class="form-control" placeholder="Phone"></div>
                            <div class="col-md-6"><input name="new_customer_city" class="form-control" placeholder="City"></div>
                            <div class="col-md-6"><input name="new_customer_address" class="form-control" placeholder="Address"></div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2"><span class="small text-muted">Cart items</span><button type="button" class="btn btn-sm btn-outline-danger" id="pos-clear-cart">Clear cart</button></div>
                        <div id="pos-cart" class="d-grid gap-2 mb-3">
                            <p class="tf-muted text-center py-4 mb-0">Add products to start a sale.</p>
                        </div>

                        <div class="row g-2 border-top pt-3">
                            <div class="col-md-6">
                                <label class="form-label" for="pos-discount-value">Discount</label>
                                <div class="input-group">
                                    <select name="discount_type" id="pos-discount-type" class="form-select">
                                        <option value="percentage">%</option>
                                        <option value="fixed">Rs</option>
                                    </select>
                                    <input name="discount_value" id="pos-discount-value" type="number" min="0" step="0.01" value="0" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="pos-tax-rate">Tax %</label>
                                <input name="tax_rate" id="pos-tax-rate" type="number" min="0" max="100" step="0.01" value="0" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="pos-payment-mode">Payment Type</label>
                                <select name="payment_mode" id="pos-payment-mode" class="form-select">
                                    <option value="cash">Cash Sale</option>
                                    @companyCan('pos.credit_sale')
                                        <option value="credit">Credit / Khata Sale</option>
                                    @endcompanyCan
                                    @companyCan('pos.split_payment')
                                        <option value="split">Split Payment</option>
                                    @endcompanyCan
                                </select>
                            </div>
                            <div class="col-12" id="pos-payments"></div>
                            <div class="col-12 d-none" id="pos-add-payment-wrap">
                                @companyCan('pos.split_payment')
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="pos-add-payment"><i class="bi bi-plus-lg me-1"></i>Add Payment Method</button>
                                @endcompanyCan
                            </div>
                            <div class="col-12 d-flex justify-content-between align-items-end mt-3">
                                <div>
                                    <div class="tf-muted small">Subtotal <span id="pos-subtotal">Rs 0.00</span></div>
                                    <div class="tf-muted small">Discount <span id="pos-discount">Rs 0.00</span></div>
                                    <div class="tf-muted small">Tax <span id="pos-tax">Rs 0.00</span></div>
                                    <div class="tf-muted small">Paid <span id="pos-paid">Rs 0.00</span></div>
                                    <div class="tf-muted small">Balance <span id="pos-balance">Rs 0.00</span></div>
                                    <div class="tf-muted small">Change <span id="pos-change">Rs 0.00</span></div>
                                    <strong class="fs-5">Total <span id="pos-total">Rs 0.00</span></strong>
                                </div>
                                @companyCan('pos.create_sale')
                                    <button class="btn btn-tf-primary">Complete Sale</button>
                                @endcompanyCan
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="modal fade" id="closeRegisterModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('business.pos.register.close', $register) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header">
                            <h2 class="h5 modal-title">Close POS Register</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label" for="closing_cash">Closing Cash</label>
                            <input id="closing_cash" name="closing_cash" type="number" min="0" step="0.01" class="form-control" required>
                            <label class="form-label mt-3" for="closing_note">Closing Note <span class="tf-muted">Optional</span></label>
                            <textarea id="closing_note" name="closing_note" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="modal-footer"><button class="btn btn-tf-primary">Close Register</button></div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-pos-form]');
            if (!form) return;

            const cart = new Map();
            const cartEl = document.getElementById('pos-cart');
            const payments = document.getElementById('pos-payments');
            const mode = document.getElementById('pos-payment-mode');
            const customPrice = form.dataset.customPrice === '1';
            const money = value => 'Rs ' + Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const totals = () => {
                const subtotal = [...cart.values()].reduce((sum, item) => sum + item.price * item.quantity, 0);
                const discountValue = Number(document.getElementById('pos-discount-value').value || 0);
                const discount = document.getElementById('pos-discount-type').value === 'percentage'
                    ? subtotal * Math.min(100, discountValue) / 100
                    : Math.min(subtotal, discountValue);
                const tax = (subtotal - discount) * Number(document.getElementById('pos-tax-rate').value || 0) / 100;

                return { subtotal, discount, tax, total: subtotal - discount + tax };
            };

            const paymentRow = index => `
                <div class="row g-2 payment-row mt-1">
                    <div class="col-5"><select name="payments[${index}][method]" class="form-select"><option>Cash</option><option>Bank Transfer</option><option>JazzCash Manual</option><option>Easypaisa Manual</option><option>Cheque</option></select></div>
                    <div class="col-4"><input name="payments[${index}][amount]" type="number" min="0" step="0.01" class="form-control pos-payment-amount" placeholder="Amount"></div>
                    <div class="col-2"><input name="payments[${index}][reference_number]" class="form-control" placeholder="Ref"></div>
                    <div class="col-1 d-grid"><button type="button" class="btn btn-outline-danger" data-remove-payment><i class="bi bi-x-lg"></i></button></div>
                </div>`;

            const render = () => {
                const current = totals();
                document.getElementById('pos-subtotal').textContent = money(current.subtotal);
                document.getElementById('pos-discount').textContent = money(current.discount);
                document.getElementById('pos-tax').textContent = money(current.tax);
                document.getElementById('pos-total').textContent = money(current.total);

                const paid = mode.value === 'credit' ? 0 : [...payments.querySelectorAll('.pos-payment-amount')]
                    .reduce((sum, input) => sum + Number(input.value || 0), 0);
                document.getElementById('pos-paid').textContent = money(paid);
                document.getElementById('pos-balance').textContent = money(Math.max(0, current.total - paid));
                document.getElementById('pos-change').textContent = money(Math.max(0, paid - current.total));

                cartEl.innerHTML = cart.size
                    ? [...cart.values()].map((item, index) => `
                        <div class="border rounded p-2">
                            <div class="d-flex justify-content-between">
                                <div><strong>${item.name}</strong><small class="d-block tf-muted">${money(item.price)} - ${item.stock} ${item.unit} available</small></div>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-remove="${item.id}"><i class="bi bi-x-lg"></i></button>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <input type="hidden" name="items[${index}][product_id]" value="${item.id}">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-decrease="${item.id}" aria-label="Decrease quantity">-</button>
                                <input name="items[${index}][quantity]" class="form-control form-control-sm" data-qty="${item.id}" type="number" min="1" max="${item.stock}" value="${item.quantity}">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-increase="${item.id}" aria-label="Increase quantity">+</button>
                                <input name="items[${index}][price]" class="form-control form-control-sm" data-price="${item.id}" type="number" min="0" step="0.01" value="${item.price}" ${customPrice ? '' : 'readonly'}>
                                <span class="small align-self-center text-nowrap">${money(item.price * item.quantity)}</span>
                            </div>
                        </div>`).join('')
                    : '<p class="tf-muted text-center py-4 mb-0">Add products to start a sale.</p>';

                if (!payments.children.length) payments.innerHTML = paymentRow(0);

                const isCredit = mode.value === 'credit';
                payments.querySelectorAll('input, select').forEach(input => input.disabled = isCredit);
                document.getElementById('pos-add-payment-wrap').classList.toggle('d-none', mode.value !== 'split');
                if (mode.value === 'cash') {
                    const amount = payments.querySelector('.pos-payment-amount');
                    if (amount) amount.value = current.total.toFixed(2);
                    document.getElementById('pos-paid').textContent = money(current.total);
                    document.getElementById('pos-balance').textContent = money(0);
                    document.getElementById('pos-change').textContent = money(0);
                }
            };

            const addProduct = product => {
                if (!product.stock) return;
                const item = cart.get(product.id) || { ...product, quantity: 0 };
                if (item.quantity < item.stock) item.quantity++;
                cart.set(product.id, item);
                render();
            };

            document.querySelectorAll('[data-pos-product]').forEach(button => {
                button.addEventListener('click', () => addProduct(JSON.parse(button.dataset.posProduct)));
            });

            cartEl.addEventListener('input', event => {
                const id = Number(event.target.dataset.qty || event.target.dataset.price);
                if (!id || !cart.has(id)) return;

                const item = cart.get(id);
                if (event.target.dataset.qty) item.quantity = Math.max(1, Math.min(item.stock, Number(event.target.value || 1)));
                if (event.target.dataset.price) item.price = Math.max(0, Number(event.target.value || 0));
                cart.set(id, item);
                render();
            });

            cartEl.addEventListener('click', event => {
                const remove = event.target.closest('[data-remove]');
                const increase = event.target.closest('[data-increase]');
                const decrease = event.target.closest('[data-decrease]');
                const id = Number(remove?.dataset.remove || increase?.dataset.increase || decrease?.dataset.decrease);
                if (id) {
                    const item = cart.get(id);
                    if (remove) cart.delete(id);
                    if (increase && item && item.quantity < item.stock) { item.quantity++; cart.set(id, item); }
                    if (decrease && item) {
                        if (item.quantity <= 1) cart.delete(id);
                        else { item.quantity--; cart.set(id, item); }
                    }
                    render();
                }
            });

            payments.addEventListener('click', event => event.target.closest('[data-remove-payment]')?.closest('.payment-row')?.remove());
            payments.addEventListener('input', render);
            document.getElementById('pos-add-payment')?.addEventListener('click', () => {
                payments.insertAdjacentHTML('beforeend', paymentRow(payments.querySelectorAll('.payment-row').length));
            });
            document.getElementById('pos-customer').addEventListener('change', event => {
                document.getElementById('pos-new-customer').classList.toggle('d-none', event.target.value !== 'new');
            });
            const filterProducts = () => {
                const search = document.getElementById('pos-search').value.toLowerCase();
                const category = document.getElementById('pos-category').value;
                document.querySelectorAll('.pos-product').forEach(card => card.classList.toggle('d-none', !card.dataset.search.includes(search) || (category && card.dataset.category !== category)));
            };
            document.getElementById('pos-search').addEventListener('input', filterProducts);
            document.getElementById('pos-category').addEventListener('change', filterProducts);
            document.getElementById('pos-clear-cart').addEventListener('click', () => { cart.clear(); render(); });
            document.getElementById('pos-barcode').addEventListener('keydown', event => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                const value = event.target.value.trim();
                const button = [...document.querySelectorAll('[data-pos-product]')].find(element => {
                    const product = JSON.parse(element.dataset.posProduct);
                    return product.barcode === value || product.sku === value;
                });
                if (button) {
                    addProduct(JSON.parse(button.dataset.posProduct));
                    event.target.value = '';
                }
            });

            ['pos-discount-value', 'pos-discount-type', 'pos-tax-rate', 'pos-payment-mode'].forEach(id => {
                document.getElementById(id).addEventListener('input', render);
                document.getElementById(id).addEventListener('change', render);
            });
            form.addEventListener('submit', event => {
                if (!cart.size) {
                    event.preventDefault();
                    alert('Add at least one product.');
                }
            });

            render();
            document.getElementById('pos-barcode').focus();
        })();
    </script>
@endpush
