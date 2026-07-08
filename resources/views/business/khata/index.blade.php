@extends('layouts.dashboard')
@section('page-title', 'Khata')
@section('page-subtitle', 'Customer payable and business receivable ledger')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="d-flex justify-content-end mb-3"><button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer me-1"></i>Print Khata</button></div>

<form class="tf-card p-4 mb-4 row g-3">
    <div class="col-md-3"><label class="form-label">Customer</label><select name="customer_id" class="form-select"><option value="">All Customers</option>@foreach($customers ?? [] as $customer)<option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->business_name ?: $customer->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Order Number</label><input name="order_number" class="form-control" value="{{ request('order_number') }}" placeholder="ORD-1001"></div>
    <div class="col-md-3"><label class="form-label">Product / Description</label><input name="description" class="form-control" value="{{ request('description') }}" placeholder="Mobile Phone"></div>
    <div class="col-md-2"><label class="form-label">Entry Type</label><select name="entry_type" class="form-select"><option value="">All</option><option value="purchase" @selected(request('entry_type') === 'purchase')>Purchase</option><option value="payment" @selected(request('entry_type') === 'payment')>Payment</option></select></div>
    <div class="col-md-2"><label class="form-label">Date From</label><input name="date_from" type="date" class="form-control" value="{{ request('date_from', now()->format('Y-m-d')) }}"></div>
    <div class="col-md-2"><label class="form-label">Date To</label><input name="date_to" type="date" class="form-control" value="{{ request('date_to', now()->format('Y-m-d')) }}"></div>
    <div class="col-md-2"><label class="form-label">Month</label><select name="month" class="form-select"><option value="">All</option>@for($m = 1; $m <= 12; $m++)<option value="{{ $m }}" @selected((string) request('month') === (string) $m)>{{ \Illuminate\Support\Carbon::create()->month($m)->format('M') }}</option>@endfor</select></div>
    <div class="col-md-2"><label class="form-label">Year</label><input name="year" type="number" class="form-control" min="2000" max="2100" value="{{ request('year', now()->year) }}"></div>
    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Apply Filters</button></div>
    <div class="col-md-2 d-flex align-items-end"><a href="{{ route('business.khata') }}" class="btn btn-outline-secondary w-100">Clear</a></div>
</form>

<div class="row g-3 mb-4">
    @foreach([
        ['Total Receivable', $totalReceivable ?? 0, 'bi-wallet2', 'bg-blue'],
        ['Customer Payable Credit', $customerCredit ?? 0, 'bi-arrow-up-circle', 'bg-amber'],
        ['Customer Payment Debit', $customerDebit ?? 0, 'bi-arrow-down-circle', 'bg-green'],
        ['Business Receivable Debit', $businessDebit ?? 0, 'bi-building-check', 'bg-navy'],
        ['Payments Received', $paymentsReceived ?? 0, 'bi-cash-stack', 'bg-green'],
        ['Remaining Balance', $remainingBalance ?? 0, 'bi-journal-check', 'bg-blue'],
    ] as [$title, $value, $icon, $color])
        <div class="col-md-6 col-xl-4"><div class="tf-card p-4"><div class="d-flex justify-content-between align-items-start"><div><div class="tf-muted">{{ $title }}</div><div class="h3 fw-bold mb-0">Rs {{ number_format($value) }}</div></div><div class="tf-icon-tile {{ $color }} text-white"><i class="bi {{ $icon }}"></i></div></div></div></div>
    @endforeach
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="tf-card p-4 h-100">
            <h2 class="h5">Customer Payable Account</h2>
            <div class="row g-3 mt-1">
                <div class="col-4"><div class="border rounded p-3"><div class="tf-muted">Debit</div><div class="h4 mb-0">Rs {{ number_format($customerDebit ?? 0) }}</div></div></div>
                <div class="col-4"><div class="border rounded p-3"><div class="tf-muted">Credit</div><div class="h4 mb-0">Rs {{ number_format($customerCredit ?? 0) }}</div></div></div>
                <div class="col-4"><div class="border rounded p-3"><div class="tf-muted">Balance</div><div class="h4 mb-0">Rs {{ number_format($remainingBalance ?? 0) }}</div></div></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="tf-card p-4 h-100">
            <h2 class="h5">Business Cash / Receivable Account</h2>
            <div class="row g-3 mt-1">
                <div class="col-4"><div class="border rounded p-3"><div class="tf-muted">Debit</div><div class="h4 mb-0">Rs {{ number_format($businessDebit ?? 0) }}</div></div></div>
                <div class="col-4"><div class="border rounded p-3"><div class="tf-muted">Credit</div><div class="h4 mb-0">Rs {{ number_format($businessCredit ?? 0) }}</div></div></div>
                <div class="col-4"><div class="border rounded p-3"><div class="tf-muted">Balance</div><div class="h4 mb-0">Rs {{ number_format(($businessDebit ?? 0) - ($businessCredit ?? 0)) }}</div></div></div>
            </div>
        </div>
    </div>
</div>

<div class="tf-card p-4 mb-4">
    <h2 class="h5">Customer Summary</h2>
    <x-table><thead><tr><th>Customer</th><th>Total Purchases</th><th>Total Payments</th><th>Remaining Balance</th><th>Last Transaction Date</th></tr></thead><tbody>@forelse($customerSummaries ?? [] as $row)<tr><td>{{ $row['customer']->business_name ?: $row['customer']->name }}</td><td>Rs {{ number_format($row['purchases']) }}</td><td>Rs {{ number_format($row['payments']) }}</td><td>Rs {{ number_format($row['balance']) }}</td><td>{{ $row['last_transaction'] ? \Illuminate\Support\Carbon::parse($row['last_transaction'])->format('M d, Y') : '-' }}</td></tr>@empty<tr><td colspan="5" class="text-center tf-muted py-4">No customer khata activity.</td></tr>@endforelse</tbody></x-table>
</div>

<div class="tf-card p-4">
    <h2 class="h5">Full Ledger</h2>
    <x-table><thead><tr><th>Date</th><th>Customer</th><th>Order No</th><th>Product / Description</th><th>Quantity</th><th>Entry Type</th><th>Customer Debit</th><th>Customer Credit</th><th>Business Debit</th><th>Business Credit</th><th>Payment Method</th><th>Balance</th></tr></thead><tbody>@forelse($ledgers ?? [] as $ledger)@php($ledgerDate = $ledger->entry_date ?: $ledger->created_at)<tr><td>{{ $ledgerDate ? \Illuminate\Support\Carbon::parse($ledgerDate)->format('M d, Y') : '-' }}</td><td>{{ $ledger->customer?->business_name ?? $ledger->customer?->name }}</td><td>{{ $ledger->order?->order_number ?? '-' }}</td><td>{{ $ledger->description }}</td><td>{{ $ledger->order?->items?->sum('quantity') ?: '-' }}</td><td><span class="badge {{ $ledger->entry_type === 'purchase' ? 'text-bg-warning' : 'text-bg-success' }}">{{ $ledger->entry_type === 'purchase' ? 'Purchase / Payable' : 'Payment Received' }}</span></td><td>{{ $ledger->customer_debit > 0 ? 'Rs '.number_format($ledger->customer_debit) : '-' }}</td><td>{{ $ledger->customer_credit > 0 ? 'Rs '.number_format($ledger->customer_credit) : '-' }}</td><td>{{ $ledger->business_debit > 0 ? 'Rs '.number_format($ledger->business_debit) : '-' }}</td><td>{{ $ledger->business_credit > 0 ? 'Rs '.number_format($ledger->business_credit) : '-' }}</td><td>{{ $ledger->payment_method ?: '-' }}</td><td>Rs {{ number_format($ledger->balance) }}</td></tr>@empty<tr><td colspan="12" class="text-center tf-muted py-4">No ledger records.</td></tr>@endforelse</tbody></x-table>
    @if(isset($ledgers) && method_exists($ledgers, 'links'))<div class="mt-3">{{ $ledgers->links() }}</div>@endif
</div>
@endsection
