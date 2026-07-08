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

<x-table>
    <thead><tr><th>Delivery ID</th><th>Order Number</th><th>Customer</th><th>Address</th><th>Amount</th><th>Payment Status</th><th>Delivery Status</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($deliveries ?? [] as $delivery)
        @php($orderTotal = $delivery->order?->grand_total ?: $delivery->order?->total ?: $delivery->amount)
        @php($paid = $delivery->order?->paid_amount ?? $delivery->order?->payments?->sum('amount') ?? 0)
        @php($remaining = $delivery->order ? ($delivery->order->balance ?? max(0, $orderTotal - $paid)) : max(0, ($delivery->amount ?? 0) - $paid))
        @php($paymentStatus = $remaining <= 0 ? 'Paid' : ($paid > 0 ? 'Partial Rs '.number_format($remaining) : 'Pending Rs '.number_format($remaining)))
        @php($paymentBadge = $remaining <= 0 ? 'text-bg-success' : ($paid > 0 ? 'text-bg-warning' : 'text-bg-danger'))
        <tr>
            <td>#DEL-{{ $delivery->id }}</td>
            <td>{{ $delivery->order?->order_number }}</td>
            <td>{{ $delivery->order?->customer?->business_name ?? $delivery->order?->customer?->name ?? '-' }}</td>
            <td>{{ $delivery->address ?: '-' }}</td>
            <td>Rs {{ number_format($orderTotal) }}</td>
            <td><span class="badge {{ $paymentBadge }}">{{ $paymentStatus }}</span></td>
            <td><span class="badge {{ $delivery->status === 'Delivered' ? 'text-bg-success' : ($delivery->status === 'Failed' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $delivery->status }}</span></td>
            <td>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('business.deliveries.show', $delivery) }}" class="btn btn-sm btn-outline-primary">View</a>
                    @if($delivery->status === 'Pending')
                        <form method="POST" action="{{ route('business.deliveries.start', $delivery) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Start</button></form>
                    @endif
                    @if($delivery->status === 'Out For Delivery')
                        <a href="{{ route('business.deliveries.show', $delivery) }}#deliver" class="btn btn-sm btn-outline-success">Deliver</a>
                        <a href="{{ route('business.deliveries.show', $delivery) }}#fail" class="btn btn-sm btn-outline-danger">Fail</a>
                    @endif
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center tf-muted py-4">No deliveries yet.</td></tr>
    @endforelse
    </tbody>
</x-table>
@if(isset($deliveries) && method_exists($deliveries, 'links'))<div class="mt-3">{{ $deliveries->links() }}</div>@endif
@endsection
