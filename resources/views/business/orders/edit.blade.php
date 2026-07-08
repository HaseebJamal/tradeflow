@extends('layouts.dashboard')
@section('page-title', 'Edit Order')
@section('page-subtitle', $order->order_number)
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ route('business.orders.update', $order) }}" class="tf-card p-4 mb-4" data-edit-order-form>
    @csrf
    @method('PUT')
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label">Customer</label>
            <select name="customer_id" class="form-select">
                <option value="walk_in" @selected(old('customer_id', $order->customer_id ? $order->customer_id : 'walk_in') === 'walk_in')>Walk-in Customer</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) old('customer_id', $order->customer_id) === (string) $customer->id)>{{ $customer->business_name ?: $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Payment Type</label>
            <select name="payment_type" class="form-select">
                @foreach(['Credit', 'Cash', 'Partial'] as $paymentType)
                    <option @selected(old('payment_type', $order->payment_type) === $paymentType)>{{ $paymentType }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Discount %</label>
            <input name="discount" type="number" min="0" max="100" step="0.01" class="form-control" value="{{ old('discount', $order->discount_percentage ?? $order->discount ?? 0) }}" data-edit-order-discount>
        </div>
        <div class="col-md-2 d-flex align-items-end">
            <button class="btn btn-tf-primary w-100">Save Changes</button>
        </div>
    </div>

    <x-table>
        <thead><tr><th>Product</th><th>Available Stock</th><th>Current Qty</th><th>New Qty</th><th>Rate</th><th>Line Total</th><th></th></tr></thead>
        <tbody data-edit-order-rows>
            @foreach($order->items as $item)
                @php($availableForEdit = ($item->product?->stock_quantity ?? 0) + $item->quantity)
                <tr data-edit-order-row data-price="{{ $item->price }}">
                    <td>
                        {{ $item->product?->name }}
                        <input type="hidden" name="items[{{ $loop->index }}][item_id]" value="{{ $item->id }}">
                        <input type="hidden" name="items[{{ $loop->index }}][product_id]" value="{{ $item->product_id }}">
                    </td>
                    <td>{{ $availableForEdit }} {{ $item->product?->unit }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td><input name="items[{{ $loop->index }}][quantity]" type="number" min="1" max="{{ $availableForEdit }}" class="form-control" value="{{ old('items.'.$loop->index.'.quantity', $item->quantity) }}" style="max-width:110px" data-edit-order-qty></td>
                    <td>Rs {{ number_format($item->price) }}</td>
                    <td data-edit-order-line-total>Rs {{ number_format($item->total) }}</td>
                    <td>
                        <input type="hidden" name="items[{{ $loop->index }}][remove]" value="0" data-edit-order-remove>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-order-row>Remove</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-table>

    <button type="button" class="btn btn-outline-primary mt-3" data-add-order-row><i class="bi bi-plus-lg me-1"></i>Add Product Row</button>

    <div class="row g-3 mt-3">
        <div class="col-md-3"><div class="border rounded p-3"><small class="tf-muted">Subtotal</small><strong class="d-block" data-edit-order-subtotal>Rs 0</strong></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><small class="tf-muted">Discount Amount</small><strong class="d-block" data-edit-order-discount-amount>Rs 0</strong></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><small class="tf-muted">Grand Total</small><strong class="d-block" data-edit-order-grand-total>Rs 0</strong></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><small class="tf-muted">Paid / Balance</small><strong class="d-block">Rs {{ number_format($order->paid_amount ?? 0) }} / <span data-edit-order-balance>Rs {{ number_format($order->balance ?? 0) }}</span></strong></div></div>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-4">
        <a href="{{ route('business.orders.show', $order) }}" class="btn btn-outline-secondary">Back to Details</a>
        <button class="btn btn-tf-primary">Save Order Changes</button>
    </div>
</form>

<template data-edit-order-template>
    <tr data-edit-order-row data-price="0">
        <td>
            <select class="form-select" data-new-product-select>
                <option value="">Select product</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" data-price="{{ $product->wholesale_price }}" data-stock="{{ $product->stock_quantity }}" data-unit="{{ $product->unit }}">{{ $product->name }}</option>
                @endforeach
            </select>
            <input type="hidden" data-new-product-input>
        </td>
        <td data-new-product-stock>-</td>
        <td>-</td>
        <td><input type="number" min="1" class="form-control" style="max-width:110px" data-edit-order-qty></td>
        <td data-new-product-rate>Rs 0</td>
        <td data-edit-order-line-total>Rs 0</td>
        <td>
            <input type="hidden" value="0" data-edit-order-remove>
            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-new-order-row>Remove</button>
        </td>
    </tr>
</template>
@endsection
