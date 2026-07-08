@extends('layouts.dashboard')
@section('page-title', 'Delivery Details')
@section('page-subtitle', '#DEL-'.$delivery->id)
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@php($order = $delivery->order)
@php($customer = $order?->customer)
@php($total = $order?->grand_total ?: $order?->total ?: $delivery->amount)
@php($paidAmount = $order?->paid_amount ?? $paidAmount ?? 0)
@php($remaining = $order?->balance ?? max(0, $total - ($paidAmount ?? 0)))

<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="tf-card p-4 h-100"><h2 class="h5">Order Information</h2><div class="row g-2 small"><div class="col-6 tf-muted">Order Number</div><div class="col-6">{{ $order?->order_number }}</div><div class="col-6 tf-muted">Order Date</div><div class="col-6">{{ $order?->created_at?->format('M d, Y') }}</div><div class="col-6 tf-muted">Order Status</div><div class="col-6">{{ $order?->status }}</div><div class="col-6 tf-muted">Total Amount</div><div class="col-6">Rs {{ number_format($total) }}</div></div></div></div>
    <div class="col-lg-6"><div class="tf-card p-4 h-100"><h2 class="h5">Customer Information</h2><div class="row g-2 small"><div class="col-6 tf-muted">Customer Name</div><div class="col-6">{{ $customer?->name ?? '-' }}</div><div class="col-6 tf-muted">Phone</div><div class="col-6">{{ $customer?->phone ?? '-' }}</div><div class="col-6 tf-muted">Shop Name</div><div class="col-6">{{ $customer?->business_name ?? '-' }}</div><div class="col-6 tf-muted">Address</div><div class="col-6">{{ $customer?->address ?? $delivery->address ?? '-' }}</div><div class="col-6 tf-muted">City</div><div class="col-6">{{ $customer?->city ?? '-' }}</div></div></div></div>
</div>

<div class="tf-card p-4 mb-4">
    <h2 class="h5">Products</h2>
    <x-table><thead><tr><th>Product Name</th><th>Quantity</th><th>Price</th><th>Total</th></tr></thead><tbody>@forelse($order?->items ?? [] as $item)<tr><td>{{ $item->product?->name }}</td><td>{{ $item->quantity }}</td><td>Rs {{ number_format($item->price) }}</td><td>Rs {{ number_format($item->total) }}</td></tr>@empty<tr><td colspan="4" class="text-center tf-muted py-4">No products.</td></tr>@endforelse</tbody></x-table>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="tf-card p-4 h-100"><h2 class="h5">Payment Information</h2><div class="row g-2 small"><div class="col-6 tf-muted">Payment Type</div><div class="col-6">{{ $order?->payment_type ?? '-' }}</div><div class="col-6 tf-muted">Paid Amount</div><div class="col-6">Rs {{ number_format($paidAmount ?? 0) }}</div><div class="col-6 tf-muted">Remaining Balance</div><div class="col-6">Rs {{ number_format($remaining) }}</div><div class="col-6 tf-muted">Collection Required</div><div class="col-6">{{ $remaining > 0 ? 'Yes' : 'No' }}</div></div></div></div>
    <div class="col-lg-6"><div class="tf-card p-4 h-100"><h2 class="h5">Delivery Information</h2><div class="row g-2 small"><div class="col-6 tf-muted">Assigned Staff</div><div class="col-6">{{ $delivery->staff?->name ?? '-' }}</div><div class="col-6 tf-muted">Delivery Status</div><div class="col-6">{{ $delivery->status }}</div><div class="col-6 tf-muted">Assigned Date</div><div class="col-6">{{ $delivery->created_at?->format('M d, Y') }}</div><div class="col-6 tf-muted">Started At</div><div class="col-6">{{ $delivery->started_at?->format('M d, Y h:i A') ?? '-' }}</div><div class="col-6 tf-muted">Delivered At</div><div class="col-6">{{ $delivery->delivered_at?->format('M d, Y h:i A') ?? '-' }}</div><div class="col-6 tf-muted">Note</div><div class="col-6">{{ $delivery->note ?? '-' }}</div></div></div></div>
</div>

@if($delivery->status === 'Pending')
    <form method="POST" action="{{ route('business.deliveries.start', $delivery) }}" class="mb-4">@csrf @method('PATCH')<button class="btn btn-tf-primary"><i class="bi bi-truck me-1"></i>Start Delivery</button></form>
@endif

@if($delivery->status === 'Out For Delivery')
<div class="row g-4">
    <div class="col-lg-7">
        <div class="tf-card p-4" id="deliver">
            <h2 class="h5">Mark Delivered</h2>
            <form method="POST" action="{{ route('business.deliveries.deliver', $delivery) }}" enctype="multipart/form-data" class="row g-3">@csrf @method('PATCH')
                <div class="col-md-6"><label class="form-label">Delivery Proof Image</label><input name="proof_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Customer Signature Image Optional</label><input name="signature_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Receiver Name</label><input name="receiver_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Receiver Phone Optional</label><input name="receiver_phone" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Collected Amount</label><input name="collected_amount" type="number" min="0" step="0.01" max="{{ $remaining }}" class="form-control" value="{{ $remaining > 0 ? $remaining : '' }}"></div>
                <div class="col-md-4"><label class="form-label">Payment Method</label><select name="payment_method" class="form-select"><option value="">No Collection</option>@foreach(['Cash','Bank Transfer Manual','JazzCash Manual','Easypaisa Manual','Cheque'] as $method)<option>{{ $method }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Reference Number Optional</label><input name="payment_reference" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Payment Proof Image Optional</label><input name="payment_proof_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Delivery Note Optional</label><textarea name="note" class="form-control" rows="2"></textarea></div>
                <div class="col-12"><button class="btn btn-tf-primary">Mark Delivered</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="tf-card p-4" id="fail">
            <h2 class="h5">Mark Failed</h2>
            <form method="POST" action="{{ route('business.deliveries.fail', $delivery) }}" class="row g-3">@csrf @method('PATCH')
                <div class="col-12"><label class="form-label">Failure Reason</label><textarea name="failure_reason" class="form-control" rows="4" required></textarea></div>
                <div class="col-12"><label class="form-label">Note Optional</label><textarea name="note" class="form-control" rows="2"></textarea></div>
                <div class="col-12"><button class="btn btn-outline-danger">Mark Failed</button></div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
