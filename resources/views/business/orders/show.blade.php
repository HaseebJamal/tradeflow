@extends('layouts.dashboard')
@section('page-title', 'Order Details')
@section('page-subtitle', $order->order_number)
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@php
    $user = auth()->user();
    $access = app(\App\Services\CompanyPermissionService::class);
    $canManageOrder = $access->allowsUser($user, 'orders.update_status');
    $canEditOrder = $access->allowsUser($user, 'orders.edit');
    $editableStatus = in_array($order->status, ['New', 'Accepted', 'Packing'], true);
@endphp

<div class="tf-card p-4 mb-4">
    <div class="row g-3 align-items-start">
        <div class="col-md-3"><strong>Customer</strong><p class="tf-muted mb-0">{{ $order->customer?->display_name ?? 'Walk-in Customer' }}</p></div>
            <div class="col-md-3"><strong>Status</strong>@companyCan('sales.update_status')<form method="POST" action="{{ route('business.sales.status', $order) }}" class="mt-2">@csrf @method('PATCH')<select name="status" class="form-select form-select-sm mb-2">@foreach(['New','Accepted','Packing','Ready','Out For Delivery','Delivered','Completed','Cancelled'] as $status)<option @selected($order->status === $status)>{{ $status }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary">Update</button></form>@else<div class="tf-muted mt-2">{{ $order->status }}</div>@endcompanyCan</div>
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
                <a class="btn btn-outline-primary" href="{{ route('business.sales.edit', $order) }}"><i class="bi bi-pencil-square me-1"></i>Edit Sale</a>
            @endif
            @if($order->delivery)
                <a class="btn btn-outline-success" href="{{ route('business.deliveries.show', $order->delivery) }}"><i class="bi bi-truck me-1"></i>View Delivery</a>
            @endif
            @companyCan('sales.invoices')<a class="btn btn-tf-primary" href="{{ route('business.sales.invoices.show', $order) }}"><i class="bi bi-receipt me-1"></i>View Invoice</a>@endcompanyCan
            @companyCan('sales.invoice_export')<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('business.sales.invoices.pdf', $order) }}"><i class="bi bi-filetype-pdf me-1"></i>View PDF</a>@endcompanyCan
            @if($canManageOrder && !in_array($order->status, ['Cancelled','Void'], true))
                @companyCan('orders.update_status')<button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#cancelOrderModal"><i class="bi bi-x-circle me-1"></i>Cancel Order</button><button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#voidOrderModal"><i class="bi bi-slash-circle me-1"></i>Void Order</button><button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteOrderModal"><i class="bi bi-trash me-1"></i>Delete Order</button>@endcompanyCan
            @endif
        </div>
    </div>
</div>

<x-table><thead><tr><th>Item</th><th>Unit</th><th>Qty</th><th>Rate</th><th>Total</th></tr></thead><tbody>@forelse($order->items as $item)<tr><td>{{ $item->product_name_snapshot ?: $item->product?->name }}</td><td>{{ $item->unit ?: $item->product?->unit }}</td><td><x-quantity :value="$item->quantity" /></td><td>Rs {{ number_format($item->unit_price ?: $item->price) }}</td><td>Rs {{ number_format($item->line_total ?: $item->total) }}</td></tr>@empty<tr><td colspan="5" class="text-center tf-muted py-4">No order items.</td></tr>@endforelse</tbody></x-table>

@if(($journalEntries ?? collect())->isNotEmpty())
<div class="tf-card p-4 mt-4">
    <h2 class="h5">Journal Entries</h2>
    <x-table><thead><tr><th>Date</th><th>Voucher</th><th>Description</th><th>Status</th></tr></thead><tbody>@foreach($journalEntries as $entry)<tr><td>{{ $entry->entry_date?->format('n/j/Y') }}</td><td>{{ $entry->voucher_number }}</td><td>{{ $entry->description }}</td><td>{{ ucfirst($entry->status) }}</td></tr>@endforeach</tbody></x-table>
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
                @companyCan('sales.update_status')<form method="POST" action="{{ route('business.sales.cancel', $order) }}">@csrf @method('PATCH')<button class="btn btn-warning">Cancel Order</button></form>@endcompanyCan
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
                @companyCan('sales.update_status')<form method="POST" action="{{ route('business.sales.destroy', $order) }}">@csrf @method('DELETE')<button class="btn btn-danger">Delete Order</button></form>@endcompanyCan
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="voidOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5">Void Order</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            @companyCan('sales.update_status')<form method="POST" action="{{ route('business.sales.void', $order) }}">@csrf @method('PATCH')
                <div class="modal-body"><label class="form-label">Void Reason</label><textarea name="void_reason" class="form-control" rows="4" required></textarea></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-danger">Void Order</button></div>
            </form>@endcompanyCan
        </div>
    </div>
</div>
@endif
@endsection
