@extends('layouts.dashboard')
@section('page-title', 'Users')
@section('page-subtitle', 'Manage platform and business accounts from one workspace')
@section('content')
<div class="row g-3 mb-4">
    @foreach([
        ['Total accounts', $counts['total'], 'bi-people', 'bg-blue'],
        ['Active accounts', $counts['active'], 'bi-person-check', 'bg-green'],
        ['Suspended', $counts['suspended'], 'bi-person-x', 'bg-red'],
        ['Business owners', $counts['business_owners'], 'bi-buildings', 'bg-navy'],
    ] as [$label, $value, $icon, $color])
        <div class="col-sm-6 col-xl-3"><div class="tf-card tf-stat-card d-flex align-items-center justify-content-between"><div><p class="tf-muted small mb-1">{{ $label }}</p><p class="h3 mb-0">{{ $value }}</p></div><span class="tf-icon-tile {{ $color }} text-white"><i class="bi {{ $icon }}"></i></span></div></div>
    @endforeach
</div>

<div class="tf-card p-3 p-lg-4 mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div><h2 class="h5 mb-1">Account directory</h2><p class="tf-muted small mb-0">Filter accounts and control platform access securely.</p></div>
        <span class="tf-badge tf-badge-info">{{ $users->total() }} matching {{ Str::plural('account', $users->total()) }}</span>
    </div>
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-lg-4"><label for="user-search" class="form-label">Search</label><input id="user-search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name or phone" autofocus></div>
        <div class="col-sm-6 col-lg-2"><label for="user-role" class="form-label">Role</label><select id="user-role" name="role" class="form-select"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role }}" @selected(request('role') === $role)>{{ Str::headline($role) }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-2"><label for="user-business" class="form-label">Business</label><select id="user-business" name="business_id" class="form-select"><option value="">All businesses</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected((string) request('business_id') === (string) $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-2"><label for="user-status" class="form-label">Status</label><select id="user-status" name="status" class="form-select"><option value="">All statuses</option>@foreach(['active', 'suspended', 'inactive'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::headline($status) }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label">Last Sign-in From</label><input type="date" name="last_sign_in_from" value="{{ request('last_sign_in_from') }}" class="form-control"></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label">Last Sign-in To</label><input type="date" name="last_sign_in_to" value="{{ request('last_sign_in_to') }}" class="form-control"></div>
        <div class="col-sm-6 col-lg-2 d-flex gap-2"><button class="btn btn-tf-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button><a href="{{ route('admin.users') }}" class="btn btn-outline-secondary" title="Clear filters" aria-label="Clear filters"><i class="bi bi-arrow-counterclockwise"></i></a></div>
    </form>
</div>

<x-table class="tf-admin-data-table">
    <thead><tr><th>Account</th><th>Role</th><th>Business</th><th>Last sign-in</th><th>Status</th><th class="text-end">Controls</th></tr></thead>
    <tbody>
    @forelse($users as $user)
        @php($statusClass = match($user->status) { 'active' => 'success', 'suspended' => 'danger', default => 'warning' })
        <tr>
            <td><div class="d-flex align-items-center gap-2"><span class="tf-icon-tile bg-blue text-white" style="width:34px;height:34px"><i class="bi bi-person"></i></span><div><strong class="d-block">{{ $user->name }}</strong><small class="tf-muted">{{ $user->business_id ? 'Company account' : $user->email }}</small></div></div></td>
            <td>{{ Str::headline($user->role) }}</td>
            <td>@if($user->business)<a href="{{ route('admin.companies.show', $user->business) }}" class="text-decoration-none">{{ $user->business->business_name }}</a>@else<span class="tf-muted">Platform account</span>@endif</td>
            <td><small>{{ $user->last_login_at?->format('d M Y, h:i A') ?? 'Never recorded' }}</small></td>
            <td><span class="tf-badge tf-badge-{{ $statusClass }}">{{ Str::headline($user->status) }}</span></td>
            <td class="text-end"><div class="btn-group">
                @if($user->id === auth()->id())
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.profile.security') }}">Your security</a>
                @elseif(!$user->business_id)
                    <form method="POST" action="{{ route('admin.users.status', $user) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $user->status === 'active' ? 'suspended' : 'active' }}"><button class="btn btn-sm btn-outline-{{ $user->status === 'active' ? 'danger' : 'success' }}">{{ $user->status === 'active' ? 'Suspend' : 'Activate' }}</button></form>
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#reset-user-{{ $user->id }}" aria-expanded="false">Reset password</button>
                @else
                    <span class="tf-muted small">Managed through company controls</span>
                @endif
            </div></td>
        </tr>
        @if($user->id !== auth()->id() && !$user->business_id)
            <tr class="collapse" id="reset-user-{{ $user->id }}"><td colspan="6" class="bg-light"><form method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="row g-2 align-items-end p-2">@csrf @method('PATCH')<div class="col-lg-4"><label for="user-password-{{ $user->id }}" class="form-label small">New password for {{ $user->name }}</label><div class="input-group"><input id="user-password-{{ $user->id }}" type="password" name="password" class="form-control" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#user-password-{{ $user->id }}" data-tf-password-icon="#user-password-icon-{{ $user->id }}" aria-label="Show password"><i id="user-password-icon-{{ $user->id }}" class="bi bi-eye"></i></button></div></div><div class="col-lg-4"><label for="user-password-confirmation-{{ $user->id }}" class="form-label small">Confirm new password</label><div class="input-group"><input id="user-password-confirmation-{{ $user->id }}" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#user-password-confirmation-{{ $user->id }}" data-tf-password-icon="#user-password-confirmation-icon-{{ $user->id }}" aria-label="Show password confirmation"><i id="user-password-confirmation-icon-{{ $user->id }}" class="bi bi-eye"></i></button></div></div><div class="col-lg-4 d-flex gap-2"><button class="btn btn-tf-primary">Save new password</button><button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#reset-user-{{ $user->id }}">Cancel</button></div><div class="col-12"><small class="tf-muted">Use at least 8 characters, including uppercase, lowercase, number, and special character. This action is recorded in the audit log.</small></div></form></td></tr>
        @endif
    @empty
        <tr><td colspan="6" class="text-center tf-muted py-5"><i class="bi bi-people fs-3 d-block mb-2"></i>No accounts match the current filters.</td></tr>
    @endforelse
    </tbody>
</x-table>
<div class="mt-3"><x-table-result-summary :paginator="$users" />{{ $users->links() }}</div>
@endsection
