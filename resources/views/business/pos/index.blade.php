@extends('layouts.dashboard')
@section('page-title', 'Point of Sale')
@section('page-subtitle', 'Keyboard-first counter sales')
@section('content')
@php
    $posConfig = json_encode([
        'productsUrl' => route('business.pos.products'),
        'barcodeUrl' => route('business.pos.barcode'),
        'openRegisterUrl' => route('business.pos.register.open'),
        'saleUrl' => route('business.pos.sales.store'),
        'holdUrl' => route('business.pos.hold'),
        'historyUrl' => route('business.pos.history'),
        'csrf' => csrf_token(),
        'registerId' => $register?->id,
        'canUseCustomPrice' => $canUseCustomPrice,
        'canCreateCustomer' => $canCreateCustomer,
    ]);
@endphp
<div class="tf-pos" data-pos-root data-pos-initialized="0" data-pos-config="{{ $posConfig }}">
    <div class="tf-pos-register-bar">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <span class="tf-pos-status {{ $register ? 'is-open' : '' }}" data-pos-register-status><i class="bi bi-circle-fill"></i><span data-pos-register-label>{{ $register ? 'Register Open' : 'Register Closed' }}</span></span>
            <span>Opening cash <strong data-pos-opening-cash>Rs {{ number_format($register?->opening_cash ?? 0) }}</strong></span>
            <span>Current invoice <strong data-pos-invoice>New sale</strong></span>
        </div>
        <div class="tf-pos-actions">
            <button type="button" class="btn btn-outline-primary" data-pos-hold @disabled(! $register)><i class="bi bi-pause-circle"></i><span>Hold Sale</span></button>
            <button type="button" class="btn btn-outline-primary" data-pos-resume><i class="bi bi-play-circle"></i><span>Resume</span></button>
            <a class="btn btn-outline-primary" href="{{ route('business.pos.history') }}" data-pos-history><i class="bi bi-clock-history"></i><span>Sales History</span></a>
            <span data-pos-register-action>
                @if($register)
                    <button type="button" class="btn btn-outline-danger" data-pos-close-register><i class="bi bi-lock"></i><span>Close Register</span></button>
                @else
                    <button type="button" class="btn btn-tf-primary" data-pos-open-register><i class="bi bi-unlock"></i><span>Open Register</span></button>
                @endif
            </span>
        </div>
    </div>

    <div class="tf-pos-workspace">
        <section class="tf-pos-panel tf-pos-products-panel">
            <div class="tf-pos-panel-head"><h2>Products</h2><small data-pos-product-count>{{ $products->count() }} available</small></div>
            <div class="tf-pos-searches">
                <div class="input-group"><span class="input-group-text"><i class="bi bi-upc-scan"></i></span><input type="text" class="form-control" data-pos-barcode placeholder="Scan barcode" autocomplete="off"><button type="button" class="btn btn-outline-secondary" data-pos-barcode-submit>Scan</button></div>
                <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="search" class="form-control" data-pos-search placeholder="Search product" autocomplete="off"></div>
            </div>
            <div class="tf-pos-categories" data-pos-categories><button type="button" class="active" data-category="">All</button>@foreach($categories as $category)<button type="button" data-category="{{ $category->id }}">{{ $category->name }}</button>@endforeach</div>
            <div class="tf-pos-product-grid" data-pos-product-grid>
                @foreach($products as $product)
                    @php
                        $productConfig = json_encode([
                            'id' => $product->id,
                            'name' => $product->name,
                            'barcode' => $product->barcode,
                            'unit' => $product->unit,
                            'price' => (int) ($product->retail_price ?: $product->wholesale_price ?: 0),
                            'stock' => (int) $product->stock_quantity,
                            'image' => $product->image,
                        ]);
                    @endphp
                    <button type="button" class="tf-pos-product-card" data-product="{{ $productConfig }}">
                        <div class="tf-pos-product-image">@if($product->image)<img src="{{ asset('storage/'.$product->image) }}" alt="">@else<i class="bi bi-box-seam"></i>@endif</div>
                        <strong>{{ $product->name }}</strong><small>{{ $product->barcode }}</small><span>Rs {{ number_format($product->retail_price ?: $product->wholesale_price ?: 0) }}</span><em><x-quantity :value="$product->stock_quantity" /> {{ $product->unit }}</em>
                    </button>
                @endforeach
            </div>
        </section>

        <section class="tf-pos-panel tf-pos-cart-panel">
            <div class="tf-pos-panel-head"><div><h2>Current Cart</h2><small>Add products and update quantities.</small></div><button type="button" class="btn btn-sm btn-outline-danger" data-pos-clear><i class="bi bi-trash"></i>Clear</button></div>
            <div class="tf-pos-cart-scroll"><table class="table table-sm align-middle tf-pos-cart-table"><thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Discount</th><th>Tax</th><th>Subtotal</th><th>Actions</th></tr></thead><tbody data-pos-cart-body><tr data-pos-empty><td colspan="8" class="text-center text-muted py-5">Scan or select a product to start a sale.</td></tr></tbody></table></div>
        </section>

        <section class="tf-pos-panel tf-pos-checkout-panel">
            <div class="tf-pos-panel-head"><h2>Checkout</h2><small>Review payment and complete sale</small></div>
            <div class="tf-pos-checkout-scroll">
                <label class="form-label">Customer</label><select class="form-select" data-pos-customer data-native-select><option value="">Walk-in Customer</option>@if($canCreateCustomer)<option value="__new__">Create New Customer</option>@endif @foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' - '.$customer->phone : '' }}</option>@endforeach</select>
                @if($canCreateCustomer)
                    <div class="tf-pos-quick-customer d-none" data-pos-quick-customer>
                        <div class="row g-2 mt-1"><div class="col-12"><label class="form-label">Customer Name</label><input class="form-control" data-pos-customer-name maxlength="255" autocomplete="off"></div><div class="col-sm-6"><label class="form-label">Phone</label><div class="tf-phone-input" data-tf-phone-field><input type="tel" inputmode="numeric" pattern="[0-9]*" class="form-control" data-pos-customer-phone data-tf-phone-visible data-default-country="pk" autocomplete="tel-national"><input type="hidden" data-tf-phone-value><div class="invalid-feedback" data-tf-phone-feedback></div></div></div><div class="col-sm-6"><label class="form-label">City</label><input class="form-control" data-pos-customer-city maxlength="100" autocomplete="off"></div><div class="col-12"><label class="form-label">Address</label><input class="form-control" data-pos-customer-address maxlength="500" autocomplete="off"></div></div>
                    </div>
                @endif
                <div class="row g-2 mt-1"><div class="col-6"><label class="form-label">Discount %</label><input class="form-control js-whole-number" data-pos-discount type="number" min="0" max="100" step="1" value="0"></div><div class="col-6"><label class="form-label">Tax %</label><input class="form-control js-whole-number" data-pos-tax type="number" min="0" max="100" step="1" value="0"></div></div>
                <input type="hidden" data-pos-payment-type value="Cash">
                <div class="tf-pos-payable" aria-live="polite"><span>Payable Amount</span><strong data-total="grand">Rs 0</strong></div>
                <label class="form-label mt-2">Cash Received</label><input class="form-control tf-pos-cash-input" data-pos-cash type="number" min="0" step="0.01" inputmode="decimal" autocomplete="off" value="">
                <label class="form-label mt-2">Payment Method</label><select class="form-select" data-pos-payment-method data-native-select><option>Cash</option><option>Credit</option><option>Split</option><option>Bank Transfer</option><option>JazzCash Manual</option><option>Easypaisa Manual</option><option>Cheque</option></select>
                <label class="form-label mt-2">Reference</label><input class="form-control" data-pos-reference maxlength="255" autocomplete="off">
            </div>
            <div class="tf-pos-complete">
                <small class="d-block text-muted mb-2 {{ $register ? 'd-none' : '' }}" data-pos-register-required>Open the register to enable checkout.</small>
                <button type="button" class="btn btn-tf-primary w-100" data-pos-complete @disabled(! $register)><i class="bi bi-check2-circle me-1"></i>Complete Sale</button>
            </div>
        </section>
    </div>
    <div class="modal fade" id="posHeldModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5">Resume Held Sale</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="list-group list-group-flush" data-pos-held-list>@forelse($heldSales as $held)<button type="button" class="list-group-item list-group-item-action" data-held-id="{{ $held->id }}">{{ $held->hold_number }} <small class="text-muted float-end">{{ $held->held_at?->format('g:i A') }}</small></button>@empty<div class="p-4 text-muted">No held sales.</div>@endforelse</div></div></div></div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/pos.js') }}?v={{ filemtime(public_path('js/pos.js')) }}"></script>
@endpush
