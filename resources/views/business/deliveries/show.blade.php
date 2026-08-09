@extends('layouts.dashboard')
@section('page-title', 'Delivery Details')
@section('page-subtitle', '#DEL-'.$delivery->id)
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@php($order = $delivery->sourceOrder())
@php($invoice = $delivery->sourceInvoice())
@php($customer = $delivery->customer ?? $order?->customer)
@php($total = $order?->grand_total ?: $order?->total ?: $delivery->amount)
@php($deliveryReference = $invoice?->invoice_number ?? $order?->order_number ?? ('#DEL-'.$delivery->id))
@php($paidAmount = $order?->paid_amount ?? $paidAmount ?? 0)
@php($remaining = $order?->balance ?? max(0, $total - ($paidAmount ?? 0)))
@php($permissions = app(\App\Services\CompanyPermissionService::class))
@php($canEdit = $permissions->allowsUser(auth()->user(), 'deliveries.edit'))
@php($canAssign = $permissions->allowsUser(auth()->user(), 'deliveries.assign'))
@php($canUpdateStatus = $permissions->allowsUser(auth()->user(), 'deliveries.update_status'))
@php($canUploadProof = $permissions->allowsUser(auth()->user(), 'deliveries.upload_proof'))
@php($canRecordCollection = $permissions->allowsUser(auth()->user(), 'deliveries.record_collection'))

<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="tf-card p-4 h-100"><h2 class="h5">POS Invoice Information</h2><div class="row g-2 small"><div class="col-6 tf-muted">Invoice Number</div><div class="col-6">{{ $invoice?->invoice_number ?? $order?->order_number }}</div><div class="col-6 tf-muted">Sale Date</div><div class="col-6"><x-date-time :value="$order?->order_date ?: $order?->created_at" /></div><div class="col-6 tf-muted">Sale Status</div><div class="col-6">{{ $order?->status }}</div><div class="col-6 tf-muted">Total Amount</div><div class="col-6">Rs {{ number_format($total) }}</div></div></div></div>
    <div class="col-lg-6"><div class="tf-card p-4 h-100"><h2 class="h5">Customer Information</h2><div class="row g-2 small"><div class="col-6 tf-muted">Customer Name</div><div class="col-6">{{ $customer?->name ?? '-' }}</div><div class="col-6 tf-muted">Phone</div><div class="col-6">{{ $customer?->phone ?? '-' }}</div><div class="col-6 tf-muted">Shop Name</div><div class="col-6">{{ $customer?->business_name ?? '-' }}</div><div class="col-6 tf-muted">Address</div><div class="col-6">{{ $customer?->address ?? $delivery->address ?? '-' }}</div><div class="col-6 tf-muted">City</div><div class="col-6">{{ $customer?->city ?? '-' }}</div></div></div></div>
</div>

<div class="tf-card p-4 mb-4">
    <h2 class="h5">Products</h2>
    <x-table><thead><tr><th>Product Name</th><th>Quantity</th><th>Price</th><th>Total</th></tr></thead><tbody>@forelse($order?->items ?? [] as $item)<tr><td>{{ $item->product?->name }}</td><td><x-quantity :value="$item->quantity" /></td><td>Rs {{ number_format($item->price) }}</td><td>Rs {{ number_format($item->total) }}</td></tr>@empty<tr><td colspan="4" class="text-center tf-muted py-4">No products.</td></tr>@endforelse</tbody></x-table>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="tf-card p-4 h-100"><h2 class="h5">Payment Information</h2><div class="row g-2 small"><div class="col-6 tf-muted">Payment Type</div><div class="col-6">{{ $order?->payment_type ?? '-' }}</div><div class="col-6 tf-muted">Paid Amount</div><div class="col-6">Rs {{ number_format($paidAmount ?? 0) }}</div><div class="col-6 tf-muted">Remaining Balance</div><div class="col-6">Rs {{ number_format($remaining) }}</div><div class="col-6 tf-muted">Cash To Collect</div><div class="col-6">Rs {{ number_format($remaining) }}</div></div></div></div>
    <div class="col-lg-6"><div class="tf-card p-4 h-100"><h2 class="h5">Delivery Information</h2><div class="row g-2 small"><div class="col-6 tf-muted">Assigned Staff</div><div class="col-6">{{ $delivery->staff?->name ?? '-' }}</div><div class="col-6 tf-muted">Delivery Status</div><div class="col-6">{{ $delivery->status }}</div><div class="col-6 tf-muted">Assigned Date</div><div class="col-6"><x-date-time :value="$delivery->assigned_at" /></div><div class="col-6 tf-muted">Started At</div><div class="col-6"><x-date-time :value="$delivery->started_at" /></div><div class="col-6 tf-muted">Delivered At</div><div class="col-6"><x-date-time :value="$delivery->delivered_at" /></div><div class="col-6 tf-muted">Failed At</div><div class="col-6"><x-date-time :value="$delivery->failed_at" /></div><div class="col-6 tf-muted">Note</div><div class="col-6">{{ $delivery->note ?? '-' }}</div></div></div></div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="{{ route('business.deliveries.sheet', $delivery) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer me-1"></i>Print Delivery Sheet</a>
    @if($canEdit && $delivery->status === 'Failed')
        <form method="POST" action="{{ route('business.deliveries.reopen', $delivery) }}">@csrf @method('PATCH')<button class="btn btn-outline-success">Reopen Delivery</button></form>
    @endif
    @if($canEdit && !in_array($delivery->status, ['Delivered', 'Cancelled'], true))
        <form method="POST" action="{{ route('business.deliveries.cancel', $delivery) }}">@csrf @method('PATCH')<button class="btn btn-outline-danger">Cancel Delivery</button></form>
    @endif
</div>

@if(($canEdit || $canAssign) && !in_array($delivery->status, ['Delivered', 'Cancelled'], true))
<div class="tf-card p-4 mb-4">
    <h2 class="h5">{{ $delivery->status === 'Pending' && ! $delivery->delivery_staff_id ? 'Assign Delivery' : 'Edit Delivery Details' }}</h2>
    <form method="POST" action="{{ route('business.deliveries.update', $delivery) }}" class="row g-3">@csrf @method('PATCH')
        @if($canAssign)<div class="col-md-4"><label class="form-label">Delivery Staff</label><select name="delivery_staff_id" class="form-select"><option value="">{{ $delivery->delivery_staff_id ? 'Keep current staff' : 'Select delivery staff' }}</option>@foreach($deliveryStaff ?? [] as $member)<option value="{{ $member->id }}" @selected($delivery->delivery_staff_id === $member->id)>{{ $member->name }}</option>@endforeach</select></div>@endif
        <div @class(['col-md-8' => $canAssign, 'col-12' => !$canAssign])><label class="form-label">Address</label><input name="address" value="{{ $delivery->address }}" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Note</label><textarea name="note" class="form-control">{{ $delivery->note }}</textarea></div>
        <div class="col-12"><button class="btn btn-outline-primary">{{ $delivery->status === 'Pending' && ! $delivery->delivery_staff_id ? 'Assign Delivery' : 'Save Delivery Changes' }}</button></div>
    </form>
</div>
@endif

@if(in_array($delivery->status, ['Assigned', 'Picked Up'], true) && $canUpdateStatus)
    <form method="POST" action="{{ route('business.deliveries.start', $delivery) }}" class="mb-4" data-tf-confirm-message="Start delivery for {{ $deliveryReference }}?" data-tf-confirm-title="Start delivery?" data-tf-confirm-button="Start Delivery" data-tf-confirm-color="#2563eb">@csrf @method('PATCH')<button class="btn btn-tf-primary"><i class="bi bi-truck me-1"></i>Start Delivery</button></form>
@endif

@if($delivery->status === 'Out For Delivery')
<div class="row g-4 mb-4">
    @if($canUploadProof)
    <div class="col-lg-6">
        <div class="tf-card p-4">
            <h2 class="h5">Upload Delivery Proof</h2>
            <form method="POST" action="{{ route('business.deliveries.proof', $delivery) }}" enctype="multipart/form-data" class="row g-3">@csrf @method('PATCH')
                <div class="col-md-6"><label class="form-label">Delivery Proof Image</label><input name="proof_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Customer Signature Optional</label><input name="signature_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Receiver Name</label><input name="receiver_name" value="{{ $delivery->receiver_name }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Receiver Phone Optional</label><x-phone-input name="receiver_phone" :value="old('receiver_phone', $delivery->receiver_phone)" :error="$errors->first('receiver_phone')" /></div>
                <div class="col-12"><label class="form-label">Delivery Note Optional</label><textarea name="note" class="form-control" rows="2">{{ $delivery->note }}</textarea></div>
                <div class="col-12"><button class="btn btn-outline-primary">Upload Proof</button></div>
            </form>
        </div>
    </div>
    @endif
    @if($canUpdateStatus)
    <div class="col-lg-6">
        <div class="tf-card p-4 h-100">
            <h2 class="h5">Delivery Status</h2>
            <p class="tf-muted">Upload proof before completing this delivery.</p>
            @if($canUploadProof)<form method="POST" action="{{ route('business.deliveries.deliver', $delivery) }}" class="mb-3" data-tf-confirm-message="Mark {{ $deliveryReference }} as delivered? This will complete the delivery after its proof is verified." data-tf-confirm-title="Mark delivered?" data-tf-confirm-button="Mark Delivered" data-tf-confirm-color="#2563eb">@csrf @method('PATCH')<button class="btn btn-tf-primary">Mark Delivered</button></form>@endif
            <form method="POST" action="{{ route('business.deliveries.fail', $delivery) }}" class="row g-3" data-tf-confirm-message="Mark {{ $deliveryReference }} as failed? The delivery can be reassigned later if permitted." data-tf-confirm-title="Mark delivery failed?" data-tf-confirm-button="Mark Failed" data-tf-confirm-color="#dc3545">@csrf @method('PATCH')
                <div class="col-12"><label class="form-label">Failure Reason</label><textarea name="failure_reason" class="form-control" rows="3" required></textarea></div>
                <div class="col-12"><label class="form-label">Note Optional</label><textarea name="note" class="form-control" rows="2"></textarea></div>
                <div class="col-12"><button class="btn btn-outline-danger">Mark Failed</button></div>
            </form>
        </div>
    </div>
    @endif
</div>
@endif

@if($canRecordCollection && $remaining > 0 && !in_array($delivery->status, ['Cancelled', 'Failed', 'Returned'], true))
<div class="tf-card p-4 mb-4">
    <h2 class="h5">Record Collection</h2>
    <p class="tf-muted">Cash to collect: <strong>Rs {{ number_format($remaining) }}</strong></p>
    <form method="POST" action="{{ route('business.deliveries.collection', $delivery) }}" enctype="multipart/form-data" class="row g-3">@csrf
        <div class="col-md-3"><label class="form-label">Amount Collected</label><input name="collected_amount" type="number" min="1" max="{{ $remaining }}" step="1" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Payment Method</label><select name="payment_method" class="form-select" required>@foreach(['Cash', 'Bank Transfer Manual', 'Jazz Cash', 'Easypaisa', 'Cheque'] as $method)<option>{{ $method }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Reference Optional</label><input name="payment_reference" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Payment Proof Optional</label><input name="payment_proof_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control"></div>
        <div class="col-12"><button class="btn btn-tf-primary">Record Collection</button></div>
    </form>
</div>
@endif
@endsection
