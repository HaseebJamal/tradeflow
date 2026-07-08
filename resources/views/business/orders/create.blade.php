@extends('layouts.dashboard')
@section('page-title', 'Create Order')
@section('page-subtitle', 'Add multiple products, select a customer, and auto-calculate totals')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('business.orders.store') }}" class="tf-card p-4" data-order-form>@csrf
    <div class="row g-3 mb-4">
        <div class="col-md-5">
            <label class="form-label">Customer</label>
            <div class="input-group">
                <select name="customer_id" class="form-select" data-order-customer-select>
                    <option value="">Select existing customer</option>
                    <option value="walk_in" @selected(old('customer_id') === 'walk_in')>Walk-in Customer</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->business_name ?: $customer->name }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickCustomerModal">
                    <i class="bi bi-person-plus me-1"></i>Add New Customer
                </button>
            </div>
            <div class="small text-success mt-2 d-none" data-quick-customer-selected></div>
        </div>
        <div class="col-md-3"><label class="form-label">Payment Type</label><select name="payment_type" class="form-select"><option @selected(old('payment_type') === 'Credit')>Credit</option><option @selected(old('payment_type') === 'Cash')>Cash</option><option @selected(old('payment_type') === 'Partial')>Partial</option></select></div>
        <div class="col-md-2"><label class="form-label">Discount %</label><input name="discount" type="number" min="0" max="100" step="0.01" class="form-control" value="{{ old('discount') }}" placeholder="Optional discount %" data-order-discount></div>
    </div>

    <input type="hidden" name="new_customer_name" value="{{ old('new_customer_name') }}" data-new-customer-name>
    <input type="hidden" name="new_customer_shop" value="{{ old('new_customer_shop') }}" data-new-customer-shop>
    <input type="hidden" name="new_customer_phone" value="{{ old('new_customer_phone') }}" data-new-customer-phone>
    <input type="hidden" name="new_customer_city" value="{{ old('new_customer_city') }}" data-new-customer-city>
    <input type="hidden" name="new_customer_address" value="{{ old('new_customer_address') }}" data-new-customer-address>
    <input type="hidden" name="new_customer_type" value="{{ old('new_customer_type', 'Retailer') }}" data-new-customer-type>
    <input type="hidden" name="new_customer_credit_limit" value="{{ old('new_customer_credit_limit') }}" data-new-customer-credit-limit>

    <x-table><thead><tr><th>Product</th><th>Price</th><th>Available</th><th>Qty</th></tr></thead><tbody>@foreach($products as $product)<tr data-order-line data-price="{{ $product->wholesale_price }}"><td>{{ $product->name }}<input type="hidden" name="products[{{ $loop->index }}][id]" value="{{ $product->id }}"></td><td>Rs {{ number_format($product->wholesale_price) }}</td><td>{{ $product->stock_quantity }} {{ $product->unit }}</td><td><input name="products[{{ $loop->index }}][quantity]" type="number" min="0" max="{{ $product->stock_quantity }}" value="{{ old('products.'.$loop->index.'.quantity', 0) }}" class="form-control" style="max-width:110px" data-order-qty></td></tr>@endforeach</tbody></x-table>
    <div class="row g-3 mt-3" data-order-preview>
        <div class="col-md-3"><div class="border rounded p-3"><small class="tf-muted">Subtotal</small><strong class="d-block" data-order-subtotal>Rs 0</strong></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><small class="tf-muted">Discount %</small><strong class="d-block" data-order-discount-label>0.00%</strong></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><small class="tf-muted">Discount Amount</small><strong class="d-block" data-order-discount-amount>Rs 0</strong></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><small class="tf-muted">Grand Total</small><strong class="d-block" data-order-grand-total>Rs 0</strong></div></div>
    </div>
    <button class="btn btn-tf-primary mt-4">Create Order</button>
</form>

<div class="modal fade" id="quickCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5">Add New Customer</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-none" data-quick-customer-error>Enter at least customer name or phone.</div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Customer Name</label><input class="form-control" data-modal-customer-name></div>
                    <div class="col-md-6"><label class="form-label">Shop Name Optional</label><input class="form-control" data-modal-customer-shop></div>
                    <div class="col-md-4"><label class="form-label">Phone Optional</label><input class="form-control" data-modal-customer-phone></div>
                    <div class="col-md-4"><label class="form-label">City Optional</label><input class="form-control" data-modal-customer-city></div>
                    <div class="col-md-4"><label class="form-label">Customer Type</label><select class="form-select" data-modal-customer-type><option>Retailer</option><option>Retail Shop</option><option>Dealer</option><option>Distributor</option><option>Wholesaler</option></select></div>
                    <div class="col-md-8"><label class="form-label">Address Optional</label><input class="form-control" data-modal-customer-address></div>
                    <div class="col-md-4"><label class="form-label">Credit Limit Optional</label><input type="number" min="0" step="0.01" class="form-control" data-modal-customer-credit-limit></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-tf-primary" data-save-quick-customer>Save Customer</button>
            </div>
        </div>
    </div>
</div>
@endsection
