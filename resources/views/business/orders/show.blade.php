@extends('layouts.dashboard')
@section('page-title', 'Order Details')
@section('page-subtitle', $order->order_number)
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@php
    $user = auth()->user();
    $permissions = collect($user->permissions ?? [])->map(fn ($value) => strtolower($value));
    $hasOrderEditPermission = $user->role === 'business_owner' || $permissions->contains('orders') || $permissions->contains('orders.edit');
    $canManageOrder = in_array($user->role, ['business_owner', 'manager'], true);
    $canEditOrder = $user->role === 'business_owner'
        || ($user->role === 'manager' && $hasOrderEditPermission)
        || ($user->role === 'sales_staff' && $order->created_by === $user->id && in_array($order->status, ['New', 'Accepted'], true) && $hasOrderEditPermission);
    $editableStatus = in_array($order->status, ['New', 'Accepted', 'Packing'], true);
    $canAssignDelivery = $canManageOrder && in_array($order->status, ['Accepted', 'Packing', 'Ready'], true) && !$order->delivery;
@endphp

<div class="tf-card p-4 mb-4">
    <div class="row g-3 align-items-start">
        <div class="col-md-3"><strong>Customer</strong><p class="tf-muted mb-0">{{ $order->customer?->business_name ?? $order->customer?->name ?? 'Walk-in Customer' }}</p></div>
        <div class="col-md-3"><strong>Status</strong><form method="POST" action="{{ route('business.orders.status', $order) }}" class="mt-2">@csrf @method('PATCH')<select name="status" class="form-select form-select-sm mb-2">@foreach(['New','Accepted','Packing','Ready','Out For Delivery','Delivered','Completed','Cancelled'] as $status)<option @selected($order->status === $status)>{{ $status }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary">Update</button></form></div>
        <div class="col-md-3">
            <strong>Totals</strong>
            <div class="small tf-muted mt-2">Subtotal: Rs {{ number_format($order->subtotal) }}</div>
            <div class="small tf-muted">Discount: {{ number_format($order->discount_percentage ?? $order->discount ?? 0, 2) }}%</div>
            <div class="small tf-muted">Discount Amount: Rs {{ number_format($order->discount_amount ?? 0) }}</div>
            <div class="small tf-muted">Paid: Rs {{ number_format($order->paid_amount ?? $order->payments->sum('amount')) }}</div>
            <div class="small tf-muted">Balance: Rs {{ number_format($order->balance ?? max(0, ($order->grand_total ?: $order->total) - $order->payments->sum('amount'))) }}</div>
            <p class="h4 mb-0">Rs {{ number_format($order->grand_total ?: $order->total) }}</p>
        </div>
    </div>
</div>

@if($order->status === 'Out For Delivery' && !$order->delivery)
    <div class="alert alert-warning">Order status is Out For Delivery but no delivery is assigned.</div>
@endif

<div class="tf-card p-4 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h2 class="h5 mb-1">Order Actions</h2>
            <div class="tf-muted small">Manage status, delivery, invoice, and safe cancellation.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if($canEditOrder && $editableStatus)
                <a class="btn btn-outline-primary" href="{{ route('business.orders.edit', $order) }}"><i class="bi bi-pencil-square me-1"></i>Edit Order</a>
            @endif
            @if($canAssignDelivery)
                <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#assignDeliveryModal"><i class="bi bi-truck me-1"></i>Assign Delivery</button>
            @elseif($order->status === 'Out For Delivery' && $order->delivery)
                <a class="btn btn-outline-success" href="{{ route('business.deliveries.show', $order->delivery) }}"><i class="bi bi-truck me-1"></i>View Delivery</a>
            @endif
            <a class="btn btn-tf-primary" href="{{ route('business.invoices.show', $order) }}"><i class="bi bi-receipt me-1"></i>View Invoice</a>
            <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('business.invoices.pdf', $order) }}"><i class="bi bi-filetype-pdf me-1"></i>View PDF</a>
            @if($canManageOrder && $order->status !== 'Cancelled')
                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#cancelOrderModal"><i class="bi bi-x-circle me-1"></i>Cancel Order</button>
                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteOrderModal"><i class="bi bi-trash me-1"></i>Delete Order</button>
            @endif
        </div>
    </div>
</div>

<x-table><thead><tr><th>Item</th><th>Qty</th><th>Rate</th><th>Total</th></tr></thead><tbody>@forelse($order->items as $item)<tr><td>{{ $item->product?->name }}</td><td>{{ $item->quantity }}</td><td>Rs {{ number_format($item->price) }}</td><td>Rs {{ number_format($item->total) }}</td></tr>@empty<tr><td colspan="4" class="text-center tf-muted py-4">No order items.</td></tr>@endforelse</tbody></x-table>

@if($canAssignDelivery)
<div class="modal fade" id="assignDeliveryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5">Assign Delivery</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('business.orders.assignDelivery', $order) }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Order Number</label><input class="form-control" value="{{ $order->order_number }}" readonly></div>
                        <div class="col-md-6"><label class="form-label">Customer</label><input class="form-control" value="{{ $order->customer?->business_name ?? $order->customer?->name ?? 'Walk-in Customer' }}" readonly></div>
                        <div class="col-md-8"><label class="form-label">Address</label><input name="address" class="form-control" value="{{ old('address', $order->customer?->address) }}" required></div>
                        <div class="col-md-4"><label class="form-label">Amount</label><input class="form-control" value="Rs {{ number_format($order->grand_total ?: $order->total) }}" readonly></div>
                        <div class="col-md-12">
                            <label class="form-label">Delivery Staff</label>
                            @if(($deliveryStaff ?? collect())->isEmpty())
                                <div class="alert alert-warning mb-0">No active delivery staff found. Please create delivery staff first.</div>
                            @else
                                <select name="delivery_staff_id" class="form-select" required>
                                    <option value="">Select delivery staff</option>
                                    @foreach($deliveryStaff as $staff)
                                        <option value="{{ $staff->id }}">{{ $staff->name }} - {{ $staff->phone }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="col-12"><label class="form-label">Note</label><textarea name="note" class="form-control" rows="3"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-tf-primary" @disabled(($deliveryStaff ?? collect())->isEmpty())>Assign Delivery</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($canManageOrder)
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5">Cancel Order</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">Cancel {{ $order->order_number }}? Product stock will be restored if it has not already been restored. Payments and khata records will be kept.</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep Order</button>
                <form method="POST" action="{{ route('business.orders.cancel', $order) }}">@csrf @method('PATCH')<button class="btn btn-warning">Cancel Order</button></form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5">Delete Order</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">Delete {{ $order->order_number }}? If this order has payments, khata, invoice, or delivery records, it will be marked Cancelled instead of hard deleted.</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <form method="POST" action="{{ route('business.orders.destroy', $order) }}">@csrf @method('DELETE')<button class="btn btn-danger">Delete Order</button></form>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
