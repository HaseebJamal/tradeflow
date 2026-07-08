@extends('layouts.dashboard')
@section('page-title', 'Staff Profile')
@section('page-subtitle', 'Employee details, activity, and permissions')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="row g-4">
    <div class="col-lg-4">
        <div class="tf-card p-4 text-center">
            @php($hasImage = $staff->profile_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($staff->profile_image))
            @if($hasImage)
                <img src="{{ asset('storage/'.$staff->profile_image) }}" class="profile-avatar mb-3" alt="{{ $staff->name }}">
            @else
                <span class="profile-avatar tf-avatar-empty mb-3"><i class="bi bi-person"></i></span>
            @endif
            <h2 class="h5 mb-1">{{ $staff->name }}</h2>
            <p class="tf-muted mb-2">{{ ucwords(str_replace('_', ' ', $staff->role)) }}</p>
            <span class="badge {{ $staff->status === 'active' ? 'text-bg-success' : ($staff->status === 'suspended' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ ucfirst($staff->status) }}</span>
            <div class="d-grid gap-2 mt-4">
                <a href="{{ route('business.staff.edit', $staff) }}" class="btn btn-tf-primary">Edit Staff</a>
                <a href="{{ route('business.staff') }}" class="btn btn-outline-secondary">Back to Staff</a>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="row g-3 mb-4">
            @foreach([
                ['Orders Created', $activity['orders_created'], 'bi-bag-check', 'bg-blue'],
                ['Deliveries Completed', $activity['deliveries_completed'], 'bi-truck', 'bg-green'],
                ['Payments Collected', 'Rs '.number_format($activity['payments_collected']), 'bi-cash-stack', 'bg-amber'],
                ['Last Login', $activity['last_login']?->format('M d, Y h:i A') ?? 'Never', 'bi-clock-history', 'bg-navy'],
            ] as [$label, $value, $icon, $color])
                <div class="col-md-6">@include('components.card', compact('label','value','icon','color'))</div>
            @endforeach
        </div>

        <div class="tf-card p-4 mb-4">
            <h3 class="h5">Personal Details</h3>
            <div class="row g-3">
                <div class="col-md-6"><small class="tf-muted">Phone</small><div>{{ $staff->phone ?? '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Email</small><div>{{ $staff->email }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Address</small><div>{{ $staff->staffProfile?->address ?? '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">CNIC</small><div>{{ $staff->staffProfile?->cnic ?? '-' }}</div></div>
            </div>
        </div>

        <div class="tf-card p-4 mb-4">
            <h3 class="h5">Job Details</h3>
            <div class="row g-3">
                <div class="col-md-6"><small class="tf-muted">Employee ID</small><div>{{ $staff->staffProfile?->employee_id ?? '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Joining Date</small><div>{{ $staff->staffProfile?->joining_date?->format('M d, Y') ?? '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Salary</small><div>{{ $staff->staffProfile?->salary ? 'Rs '.number_format($staff->staffProfile->salary) : '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Employment Type</small><div>{{ $staff->staffProfile?->employment_type ?? '-' }}</div></div>
            </div>
        </div>

        <div class="tf-card p-4">
            <h3 class="h5">Permissions Summary</h3>
            <div class="row g-2">
                @php($staffPermissions = collect($staff->permissions ?? [])->map(fn ($value) => strtolower($value))->all())
                @forelse($staffPermissions as $permission)
                    <div class="col-md-4"><span class="badge text-bg-light border w-100 py-2">{{ ucwords(str_replace(['_', '.'], [' ', ' / '], $permission)) }}</span></div>
                @empty
                    <div class="col-12"><p class="tf-muted mb-0">No permissions assigned.</p></div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
