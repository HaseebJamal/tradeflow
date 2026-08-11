@extends('layouts.dashboard')
@section('page-title', 'User Profile')
@section('page-subtitle', 'User details, activity, and permissions')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="row g-4">
    <div class="col-lg-4">
        <div class="tf-card p-4 text-center">
            @php($hasImage = $staff->profile_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($staff->profile_image))
            @if($hasImage)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($staff->profile_image) }}" class="profile-avatar mb-3" alt="{{ $staff->name }}">
            @else
                <span class="profile-avatar tf-avatar-empty mb-3"><i class="bi bi-person"></i></span>
            @endif
            <h2 class="h5 mb-1">{{ $staff->name }}</h2>
            <p class="tf-muted mb-2">{{ $staff->role === 'custom_staff' ? ($staff->staffProfile?->custom_role_name ?: 'Custom Staff') : ($roles[$staff->role] ?? ucwords(str_replace('_', ' ', $staff->role))) }}</p>
            <span class="badge {{ $staff->status === 'active' ? 'text-bg-success' : ($staff->status === 'suspended' ? 'text-bg-danger' : ($staff->status === 'archived' ? 'text-bg-secondary' : 'text-bg-warning')) }}">{{ ucfirst($staff->status) }}</span>
            <div class="d-grid gap-2 mt-4">
                @companyCan('staff.edit')<a href="{{ route('business.staff.edit', $staff) }}" class="btn btn-tf-primary">Edit User</a>@endcompanyCan
                <a href="{{ route('business.staff') }}" class="btn btn-outline-secondary">Back to Roles &amp; Users</a>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="row g-3 mb-4">
            @foreach([
                ['Orders Created', $activity['orders_created'], 'bi-bag-check', 'bg-blue'],
                ['Deliveries Completed', $activity['deliveries_completed'], 'bi-truck', 'bg-green'],
                ['Payments Collected', 'Rs '.number_format($activity['payments_collected']), 'bi-cash-stack', 'bg-amber'],
                ['Last Login', $activity['last_login']?->format('n/j/Y, g:i A') ?? 'Never', 'bi-clock-history', 'bg-navy'],
            ] as [$label, $value, $icon, $color])
                <div class="col-md-6">@include('components.card', compact('label','value','icon','color'))</div>
            @endforeach
        </div>

        <div class="tf-card p-4 mb-4">
            <h3 class="h5">Personal Details</h3>
            <div class="row g-3">
                <div class="col-md-6"><small class="tf-muted">Phone</small><div>{{ $staff->phone ?? '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Email</small><div>{{ $staff->email }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Father Name</small><div>{{ $staff->staffProfile?->father_name ?? '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">City</small><div>{{ $staff->staffProfile?->city ?? '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Address</small><div>{{ $staff->staffProfile?->address ?? '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">CNIC</small><div>{{ $staff->staffProfile?->cnic ?? '-' }}</div></div>
            </div>
        </div>

        <div class="tf-card p-4 mb-4">
            <h3 class="h5">Job Details</h3>
            <div class="row g-3">
                <div class="col-md-6"><small class="tf-muted">Joining Date</small><div>{{ $staff->staffProfile?->joining_date?->format('n/j/Y') ?? '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Salary</small><div>{{ $staff->staffProfile?->salary ? 'Rs '.number_format($staff->staffProfile->salary) : '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Employment Type</small><div>{{ $staff->staffProfile?->employment_type ?? '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Business</small><div>{{ $staff->business?->business_name ?? '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Created By</small><div>{{ $staff->creator?->name ?? '-' }}</div></div>
                <div class="col-md-6"><small class="tf-muted">Created At</small><div><x-date-time :value="$staff->created_at" /></div></div>
                <div class="col-md-6"><small class="tf-muted">Updated At</small><div><x-date-time :value="$staff->updated_at" /></div></div>
                <div class="col-md-6"><small class="tf-muted">Last Activity</small><div><x-date-time :value="$activity['last_activity']" /></div></div>
            </div>
        </div>

        @companyCan('staff.edit')<div id="reset-password" class="tf-card p-4 mb-4">
            <h3 class="h5">Reset Password</h3>
            <p class="tf-muted small">Set a new password without displaying the existing password.</p>
            <form method="POST" action="{{ route('business.staff.reset-password', $staff) }}" class="row g-3" data-staff-password-form>
                @csrf @method('PATCH')
                <div class="col-md-5"><label for="reset-password-input" class="form-label">New Password</label><div class="input-group"><input id="reset-password-input" name="password" type="password" autocomplete="new-password" minlength="8" class="form-control" required data-staff-password><button type="button" class="btn btn-outline-secondary tf-password-toggle" aria-label="Show new password" data-tf-password-toggle="#reset-password-input" data-tf-password-icon="#reset-password-icon"><i id="reset-password-icon" class="bi bi-eye"></i></button></div></div>
                <div class="col-md-5"><label for="reset-password-confirmation" class="form-label">Confirm New Password</label><div class="input-group"><input id="reset-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" class="form-control" required data-staff-password-confirmation><button type="button" class="btn btn-outline-secondary tf-password-toggle" aria-label="Show confirm password" data-tf-password-toggle="#reset-password-confirmation" data-tf-password-icon="#reset-password-confirmation-icon"><i id="reset-password-confirmation-icon" class="bi bi-eye"></i></button></div><div class="invalid-feedback" data-staff-password-match-error>Password and confirm password do not match.</div></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100">Reset</button></div>
            </form>
        </div>@endcompanyCan

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
