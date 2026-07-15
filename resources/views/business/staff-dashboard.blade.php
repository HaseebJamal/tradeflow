@extends('layouts.dashboard')
@section('title', 'Staff Dashboard | TradeFlow')
@section('page-title', 'Staff Dashboard')
@section('page-subtitle', 'Your assigned TradeFlow workspace')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="tf-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center">
        <div>
            <h2 class="h5 mb-1">Welcome, {{ $user->name }}</h2>
            <p class="tf-muted mb-0">{{ $user->role === 'custom_staff' ? ($user->staffProfile?->custom_role_name ?: 'Custom Staff') : ucwords(str_replace('_', ' ', $user->role)) }} access for your assigned business modules.</p>
        </div>
        <span class="badge text-bg-success">Active</span>
    </div>
</div>

@if($deliveryStats)
<div class="row g-3 mb-4">
@foreach([
    ['Today Deliveries', $deliveryStats['today'] ?? 0, 'bi-calendar-day', 'bg-blue'],
    ['Pending Deliveries', $deliveryStats['pending'] ?? 0, 'bi-hourglass', 'bg-amber'],
    ['Out For Delivery', $deliveryStats['out'] ?? 0, 'bi-truck', 'bg-navy'],
    ['Delivered', $deliveryStats['delivered'] ?? 0, 'bi-check-circle', 'bg-green'],
    ['Failed', $deliveryStats['failed'] ?? 0, 'bi-x-circle', 'bg-red'],
    ['Cash To Collect', 'Rs '.number_format($deliveryStats['cash_to_collect'] ?? 0), 'bi-cash-stack', 'bg-blue'],
] as [$label, $value, $icon, $color])
    <div class="col-md-6 col-xl-2">@include('components.card', ['label' => $label, 'value' => $value, 'icon' => $icon, 'color' => $color, 'note' => ''])</div>
@endforeach
</div>

<div class="tf-card p-4 mb-4">
    <h2 class="h5">Assigned Deliveries</h2>
    <x-table><thead><tr><th>Delivery ID</th><th>Order</th><th>Customer</th><th>Address</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>@forelse($assignedDeliveries as $delivery)<tr><td>#DEL-{{ $delivery->id }}</td><td>{{ $delivery->order?->order_number }}</td><td>{{ $delivery->order?->customer?->display_name }}</td><td>{{ $delivery->address }}</td><td>Rs {{ number_format($delivery->amount, 2) }}</td><td>{{ $delivery->status }}</td><td><a href="{{ route('business.deliveries.show', $delivery) }}" class="btn btn-sm btn-outline-primary">View Details</a></td></tr>@empty<tr><td colspan="7" class="text-center tf-muted py-4">No assigned deliveries.</td></tr>@endforelse</tbody></x-table>
</div>
@endif

<div class="row g-3">
    @forelse($modules as [$key, $label, $icon, $url, $description])
        <div class="col-md-6 col-xl-4">
            <a href="{{ $url }}" class="tf-card p-4 h-100 d-block text-decoration-none text-dark tf-hover-card">
                <div class="d-flex align-items-center gap-3">
                    <span class="tf-brand-mark bg-blue"><i class="bi {{ $icon }}"></i></span>
                    <div>
                        <h3 class="h6 mb-1">{{ $label }}</h3>
                        <p class="tf-muted mb-0">{{ $description }}</p>
                    </div>
                </div>
            </a>
        </div>
    @empty
        <div class="col-12">
            <div class="tf-card p-4 text-center">
                <i class="bi bi-lock fs-2 text-warning"></i>
                <h2 class="h5 mt-3">No Modules Assigned</h2>
                <p class="tf-muted mb-0">Your business has been approved, but no operational modules have been assigned to your company yet.</p>
            </div>
        </div>
    @endforelse
</div>
@endsection
