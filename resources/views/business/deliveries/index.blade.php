@extends('layouts.dashboard')
@section('page-title', 'Deliveries')
@section('page-subtitle', 'Assigned delivery workflow')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row g-3 mb-4">
@foreach([
    ['Today Deliveries', $stats['today'] ?? 0, 'bi-calendar-day', 'bg-blue'],
    ['Pending Deliveries', $stats['pending'] ?? 0, 'bi-hourglass', 'bg-amber'],
    ['Out For Delivery', $stats['out'] ?? 0, 'bi-truck', 'bg-navy'],
    ['Delivered', $stats['delivered'] ?? 0, 'bi-check-circle', 'bg-green'],
    ['Failed', $stats['failed'] ?? 0, 'bi-x-circle', 'bg-red'],
    ['Cash To Collect', 'Rs '.number_format($stats['cash_to_collect'] ?? 0), 'bi-cash-stack', 'bg-blue'],
] as [$label, $value, $icon, $color])
    <div class="col-md-6 col-xl-2">@include('components.card', ['label' => $label, 'value' => $value, 'icon' => $icon, 'color' => $color, 'note' => ''])</div>
@endforeach
</div>

<form class="tf-card p-4 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label">Invoice Number</label><input name="order_number" value="{{ request('order_number') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Customer</label><select name="customer_id" class="form-select"><option value="">All</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->display_name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Delivery Staff</label><select name="delivery_staff_id" class="form-select"><option value="">All</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected(request('delivery_staff_id') == $member->id)>{{ $member->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option>@foreach(['Pending','Assigned','Picked Up','Out For Delivery','Delivered','Failed','Returned','Cancelled'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Payment Status</label><select name="payment_status" class="form-select"><option value="">All</option>@foreach(['Pending','Partial','Paid'] as $status)<option @selected(request('payment_status')===$status)>{{ $status }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Date Type</label><select name="date_type" class="form-select">@foreach(['created_at'=>'Created Date','assigned_at'=>'Assigned Date','started_at'=>'Started Date','delivered_at'=>'Delivered Date','failed_at'=>'Failed Date'] as $value => $label)<option value="{{ $value }}" @selected(request('date_type', $dateType) === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ request('date_from', $dateFrom) }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ request('date_to', $dateTo) }}" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
        <div class="col-md-2"><a href="{{ route('business.deliveries', ['clear' => 1]) }}" class="btn btn-outline-secondary w-100">Clear Filters</a></div>
    </div>
</form>

<x-table class="tf-business-data-table">
    <thead><tr><th>Delivery ID</th><th>Invoice Number</th><th>Customer</th><th>Delivery Staff</th><th>Status</th><th>Payment Status</th><th>Amount</th><th>Assigned At</th><th>Started At</th><th>Delivered At</th><th>Failed At</th><th>Created At</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($deliveries ?? [] as $delivery)
        @php($sale = $delivery->sourceOrder())
        @php($invoice = $delivery->sourceInvoice())
        @php($orderTotal = $sale?->grand_total ?: $sale?->total ?: $delivery->amount)
        @php($paid = $sale?->paid_amount ?? $sale?->payments?->sum('amount') ?? 0)
        @php($remaining = $sale ? ($sale->balance ?? max(0, $orderTotal - $paid)) : max(0, ($delivery->amount ?? 0) - $paid))
        @php($paymentStatus = $remaining <= 0 ? 'Paid' : ($paid > 0 ? 'Partial Rs '.number_format($remaining) : 'Pending Rs '.number_format($remaining)))
        @php($paymentBadge = $remaining <= 0 ? 'text-bg-success' : ($paid > 0 ? 'text-bg-warning' : 'text-bg-danger'))
        <tr>
            <td>#DEL-{{ $delivery->id }}</td>
            <td>{{ $invoice?->invoice_number ?? $sale?->order_number }}</td>
            <td>{{ $delivery->customer?->display_name ?? $sale?->customer?->display_name ?? '-' }}</td>
            <td>{{ $delivery->staff?->name ?? '-' }}</td>
            <td><span class="badge {{ $delivery->status === 'Delivered' ? 'text-bg-success' : ($delivery->status === 'Failed' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $delivery->status }}</span></td>
            <td><span class="badge {{ $paymentBadge }}">{{ $paymentStatus }}</span></td>
            <td>Rs {{ number_format($orderTotal) }}</td>
            <td><x-date-time :value="$delivery->assigned_at" /></td>
            <td><x-date-time :value="$delivery->started_at" /></td>
            <td><x-date-time :value="$delivery->delivered_at" /></td>
            <td><x-date-time :value="$delivery->failed_at" /></td>
            <td><x-date-time :value="$delivery->created_at" /></td>
            <td>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('business.deliveries.show', $delivery) }}" class="btn btn-sm btn-outline-primary">View</a>
                    <a href="{{ route('business.deliveries.sheet', $delivery) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Sheet</a>
                    @companyCan('deliveries.update_status')
                        @if(in_array($delivery->status, ['Pending','Assigned','Picked Up'], true))
                            <form method="POST" action="{{ route('business.deliveries.start', $delivery) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Start Delivery</button></form>
                        @endif
                        @if($delivery->status === 'Out For Delivery')
                            <a href="{{ route('business.deliveries.show', $delivery) }}" class="btn btn-sm btn-outline-primary">Update Status</a>
                        @endif
                    @endcompanyCan
                    @companyCan('deliveries.edit')
                        @if($delivery->status === 'Failed')
                            <form method="POST" action="{{ route('business.deliveries.reopen', $delivery) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Reopen</button></form>
                        @endif
                    @endcompanyCan
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="13" class="text-center tf-muted py-4">No deliveries yet.</td></tr>
    @endforelse
    </tbody>
</x-table>
@if(isset($deliveries) && method_exists($deliveries, 'links'))<div class="mt-3"><x-table-result-summary :paginator="$deliveries" />{{ $deliveries->links('pagination::bootstrap-5') }}</div>@endif
@endsection
