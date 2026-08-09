@extends('layouts.dashboard')
@section('page-title', 'Customer Payments')
@section('page-subtitle', 'Record sales collections and review customer payment history')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@companyCan('sales.payments')
<section class="tf-card p-3 p-lg-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-1">Record Customer Payment</h2><p class="tf-muted small mb-0">Payments update the customer balance, sales record, Khata, and accounting automatically.</p></div></div>
    <form method="POST" action="{{ route('business.sales.payments.store') }}" enctype="multipart/form-data" class="row g-3 align-items-end" data-payment-form>@csrf
        <div class="col-lg-4"><label class="form-label" for="payment-customer">Customer <span class="text-danger">*</span></label><select id="payment-customer" name="customer_id" class="form-select" required><option value="">Select customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->display_name }}@if($customer->business_name) — {{ $customer->business_name }}@endif</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label" for="payment-method">Method <span class="text-danger">*</span></label><select id="payment-method" name="method" class="form-select" required>@foreach(['Cash','Bank Transfer','Jazz Cash','Easypaisa','Cheque'] as $method)<option value="{{ $method }}" @selected(old('method', 'Cash') === $method)>{{ $method }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label" for="payment-amount">Amount <span class="text-danger">*</span></label><input id="payment-amount" name="amount" value="{{ old('amount') }}" inputmode="decimal" autocomplete="off" data-money-input data-non-negative class="form-control" placeholder="0.00" required></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label" for="payment-date">Payment Date <span class="text-danger">*</span></label><input id="payment-date" name="payment_date" type="date" class="form-control" value="{{ old('payment_date', now(config('app.timezone'))->toDateString()) }}" required></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label" for="payment-status">Status <span class="text-danger">*</span></label><select id="payment-status" name="status" class="form-select" required>@foreach(['Paid','Partial','Pending'] as $status)<option value="{{ $status }}" @selected(old('status', 'Paid') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div class="col-lg-4"><label class="form-label" for="payment-order">Sale / Order</label><select id="payment-order" name="order_id" class="form-select"><option value="">Apply to customer balance</option>@foreach($orders as $order)<option value="{{ $order->id }}" @selected(old('order_id') == $order->id)>{{ $order->order_number }} — {{ $order->customer?->display_name ?? 'Walk-in Customer' }} (Rs {{ number_format($order->balance ?? 0, 2) }})</option>@endforeach</select></div>
        <div class="col-lg-4"><label class="form-label" for="payment-reference">Reference Number</label><input id="payment-reference" name="reference_number" value="{{ old('reference_number') }}" class="form-control" maxlength="255" placeholder="Bank, cheque, or transaction reference"></div>
        <div class="col-lg-4"><label class="form-label" for="payment-proof">Payment Proof</label><input id="payment-proof" name="proof_image" type="file" accept=".jpg,.jpeg,.png,.webp" class="form-control"><div class="form-text">JPG, PNG, or WebP up to 2 MB.</div></div>
        <div class="col-12 d-flex justify-content-end"><button class="btn btn-sm btn-tf-primary" data-payment-submit><i class="bi bi-check2-circle me-1"></i>Save Payment</button></div>
    </form>
</section>
@endcompanyCan

<form class="tf-card p-3 mb-3 row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Customer</label><select name="customer_id" class="form-select"><option value="">All customers</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->display_name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Method</label><select name="method" class="form-select"><option value="">All methods</option>@foreach(['Cash','Bank Transfer','Jazz Cash','Easypaisa','Cheque'] as $method)<option value="{{ $method }}" @selected(request('method') === $method)>{{ $method }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['Paid','Partial','Pending'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ request('date_from', now(config('app.timezone'))->toDateString()) }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ request('date_to', now(config('app.timezone'))->toDateString()) }}" class="form-control"></div>
    <div class="col-md-1 d-flex gap-2"><button class="btn btn-outline-primary flex-fill">Filter</button><a href="{{ route('business.sales.payments.index') }}" class="btn btn-outline-secondary" aria-label="Clear filters"><i class="bi bi-arrow-counterclockwise"></i></a></div>
</form>

<section class="tf-card p-0 overflow-hidden"><x-table><thead><tr><th>Payment</th><th>Customer</th><th>Sale / Order</th><th>Method</th><th>Reference</th><th>Amount</th><th>Status</th><th>Date</th><th>Proof</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td><strong>#PAY-{{ $payment->id }}</strong></td><td>{{ $payment->customer?->display_name ?? '—' }}</td><td>{{ $payment->order?->order_number ?? 'Customer balance' }}</td><td>{{ $payment->method }}</td><td>{{ $payment->reference_number ?: '—' }}</td><td>Rs {{ number_format($payment->amount, 2) }}</td><td><span class="tf-badge {{ $payment->status === 'Paid' ? 'tf-badge-success' : ($payment->status === 'Partial' ? 'tf-badge-warning' : 'tf-badge-info') }}">{{ $payment->status }}</span></td><td><x-date-time :value="$payment->payment_date ?: $payment->created_at" /></td><td>@if($payment->proof_image)<a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($payment->proof_image) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">View</a>@else—@endif</td></tr>@empty<tr><td colspan="9" class="text-center tf-muted py-5">No customer payments found.</td></tr>@endforelse</tbody></x-table></section>
<div class="mt-3">{{ $payments->links() }}</div>
@endsection
