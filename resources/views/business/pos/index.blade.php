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
        'heldSearchUrl' => route('business.pos.held-sales.search'),
        'invoiceSearchUrl' => route('business.pos.invoices.search'),
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
        <div class="tf-pos-top-inputs" aria-label="POS actions">
            <div class="tf-pos-top-input">
                <label for="posHoldSale">Hold Sale</label>
                <input id="posHoldSale" type="text" class="form-control" data-pos-hold-input placeholder="Enter / Generate Hold Number" autocomplete="off" @disabled(! $register)>
            </div>
            <div class="tf-pos-top-input">
                <label for="posResumeSale">Resume</label>
                <input id="posResumeSale" type="search" class="form-control" data-pos-resume-input placeholder="Enter Hold ID" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-controls="posResumeSuggestions">
                <div id="posResumeSuggestions" class="tf-pos-top-suggestions d-none" data-pos-resume-suggestions role="listbox" aria-label="Matching held sales"></div>
                <small class="text-danger" data-pos-resume-error aria-live="polite" hidden></small>
            </div>
            <div class="tf-pos-top-input">
                <label for="posHistorySearch">Search Sale History</label>
                <input id="posHistorySearch" type="search" class="form-control" data-pos-history-input placeholder="Enter Invoice Number" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-controls="posHistorySuggestions">
                <div id="posHistorySuggestions" class="tf-pos-top-suggestions d-none" data-pos-history-suggestions role="listbox" aria-label="Matching invoices"></div>
                <small class="text-danger" data-pos-history-error aria-live="polite" hidden></small>
            </div>
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
            <div class="tf-pos-product-grid" data-pos-product-grid role="listbox" aria-label="Products">
                @foreach($products as $product)
                    @php
                        $productConfig = json_encode([
                            'id' => $product->id,
                            'name' => $product->name,
                            'barcode' => $product->barcode,
                            'unit' => $product->unit,
                            'price' => (int) ($product->retail_price ?: $product->wholesale_price ?: 0),
                            'stock' => (int) $product->stock_quantity,
                            'image_url' => $product->image_url,
                        ]);
                    @endphp
                    <button type="button" class="tf-pos-product-card" data-product="{{ $productConfig }}" tabindex="-1" role="option" aria-selected="false">
                        <div class="tf-pos-product-image">@if($product->image_url)<img src="{{ $product->image_url }}" alt="" data-pos-product-image>@else<i class="bi bi-box-seam"></i>@endif</div>
                        <strong>{{ $product->name }}</strong><small>{{ $product->barcode }}</small><span>Rs {{ number_format($product->retail_price ?: $product->wholesale_price ?: 0) }}</span><em><x-quantity :value="$product->stock_quantity" /> {{ $product->unit }}</em>
                    </button>
                @endforeach
            </div>
        </section>

        <section class="tf-pos-panel tf-pos-cart-panel">
            <div class="tf-pos-panel-head"><div><h2>Current Cart</h2><small>Add products and update quantities.</small></div><button type="button" class="btn btn-sm btn-outline-danger" data-pos-clear><i class="bi bi-trash"></i>Clear</button></div>
            <div class="tf-pos-cart-scroll"><table class="table table-sm align-middle tf-pos-cart-table"><colgroup><col class="tf-pos-cart-col-index"><col class="tf-pos-cart-col-product"><col class="tf-pos-cart-col-quantity"><col class="tf-pos-cart-col-price"><col class="tf-pos-cart-col-total"><col class="tf-pos-cart-col-actions"></colgroup><thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Actions</th></tr></thead><tbody data-pos-cart-body><tr data-pos-empty><td colspan="6" class="text-center text-muted py-5">Scan or select a product to start a sale.</td></tr></tbody></table></div>
        </section>

        <section class="tf-pos-panel tf-pos-checkout-panel">
            <div class="tf-pos-panel-head"><h2>Checkout</h2><small>Review payment and complete sale</small></div>
            <div class="tf-pos-checkout-scroll">
                @if($canViewCustomers)
                    <label class="form-label">Customer</label><select class="form-select" data-pos-customer data-native-select><option value="">Walk-in Customer</option>@if($canCreateCustomer)<option value="__new__">Create New Customer</option>@endif @foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}{{ $customer->phone ? ' - '.$customer->phone : '' }}</option>@endforeach</select>
                @endif
                @if($canViewCustomers && $canCreateCustomer)
                    <div class="tf-pos-quick-customer d-none" data-pos-quick-customer>
                        <div class="row g-2 mt-1"><div class="col-12"><label class="form-label">Customer Name</label><input class="form-control" data-pos-customer-name maxlength="255" autocomplete="off"></div><div class="col-sm-6"><label class="form-label">Phone</label><div class="tf-phone-input" data-tf-phone-field><input type="tel" inputmode="numeric" pattern="[0-9]*" class="form-control" data-pos-customer-phone data-tf-phone-visible data-default-country="pk" autocomplete="tel-national"><input type="hidden" data-tf-phone-value><div class="invalid-feedback" data-tf-phone-feedback></div></div></div><div class="col-sm-6"><label class="form-label">City</label><input class="form-control" data-pos-customer-city maxlength="100" autocomplete="off"></div><div class="col-12"><label class="form-label">Address</label><input class="form-control" data-pos-customer-address maxlength="500" autocomplete="off"></div></div>
                    </div>
                @endif
                <div class="tf-pos-delivery-group">
                    <label class="form-label" for="posDeliveryRequired">Delivery Required?</label>
                    <select id="posDeliveryRequired" class="form-select" data-pos-delivery-required data-native-select><option value="0" selected>No</option><option value="1">Yes</option></select>
                    <small class="text-muted">Yes sends the completed sale to the Delivery queue.</small>
                </div>
                <div class="tf-pos-delivery-details d-none" data-pos-delivery-details><label class="form-label" for="posDeliveryAddress">Delivery Address</label><textarea id="posDeliveryAddress" class="form-control" data-pos-delivery-address rows="2" maxlength="1000" placeholder="Enter the delivery address"></textarea><small class="text-muted">A customer and delivery address are required for delivery.</small></div>
                <div class="row g-2 mt-1"><div class="col-6"><label class="form-label">Discount %</label><input class="form-control js-whole-number" data-pos-discount type="number" min="0" max="100" step="1" value="0"></div><div class="col-6"><label class="form-label">Tax %</label><input class="form-control js-whole-number" data-pos-tax type="number" min="0" max="100" step="1" value="0"></div></div>
                <input type="hidden" data-pos-payment-type value="Cash">
                <div class="tf-pos-payable" aria-live="polite"><span>Payable Amount</span><strong data-total="grand">Rs 0</strong></div>
                <label class="form-label mt-2" data-pos-tender-label>Cash Received</label><input class="form-control tf-pos-cash-input js-whole-number" data-pos-cash type="number" min="0" step="1" inputmode="numeric" autocomplete="off" value="">
                <div class="mt-2" data-pos-change-row><label class="form-label">Change Return</label><input class="form-control" data-pos-change type="text" value="Rs 0" readonly tabindex="-1"></div>
                <label class="form-label mt-2">Payment Method</label><select class="form-select" data-pos-payment-method data-native-select><option>Cash</option><option>Credit</option><option>Split</option><option>Bank Transfer</option><option>Jazz Cash</option><option>Easypaisa</option><option>Cheque</option></select>
                <label class="form-label mt-2">Reference</label><input class="form-control" data-pos-reference maxlength="255" autocomplete="off">
            </div>
            <div class="tf-pos-complete">
                <small class="d-block text-muted mb-2 {{ $register ? 'd-none' : '' }}" data-pos-register-required>Open the register to enable checkout.</small>
                <button type="button" class="btn btn-tf-primary w-100" data-pos-complete @disabled(! $register)><i class="bi bi-check2-circle me-1"></i>Complete Sale</button>
            </div>
        </section>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/pos.js') }}?v={{ filemtime(public_path('js/pos.js')) }}"></script>
@endpush
