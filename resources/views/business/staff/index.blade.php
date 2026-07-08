@extends('layouts.dashboard')
@section('page-title', 'Staff Management')
@section('page-subtitle', 'Manage employees, roles, permissions, and account access')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row g-3 mb-4">
@foreach([
    ['Total Staff', $stats['total'], 'bi-people', 'bg-blue'],
    ['Active Staff', $stats['active'], 'bi-person-check', 'bg-green'],
    ['Inactive Staff', $stats['inactive'], 'bi-person-dash', 'bg-amber'],
    ['Managers', $stats['managers'], 'bi-person-badge', 'bg-navy'],
    ['Sales Team', $stats['sales'], 'bi-bag-check', 'bg-blue'],
    ['Delivery Team', $stats['delivery'], 'bi-truck', 'bg-green'],
] as [$label, $value, $icon, $color])
    <div class="col-md-6 col-xl-2">@include('components.card', ['label' => $label, 'value' => $value, 'icon' => $icon, 'color' => $color, 'note' => ''])</div>
@endforeach
</div>

<div class="tf-card p-4 mb-4">
    <h2 class="h5 mb-3">Create Staff Account</h2>
    @include('business.staff._form', ['staffMember' => null])
</div>

<section id="staff-results">
<div class="tf-card p-4 mb-4 staff-filter-card">
    <form method="GET" action="{{ route('business.staff.index') }}#staff-results" class="row g-3 align-items-end">
        <div class="col-md-4"><label class="form-label">Search</label><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, email, phone, employee ID"></div>
        <div class="col-md-3"><label class="form-label">Role</label><select name="role" class="form-select"><option value="">All Roles</option>@foreach($roles as $value => $label)<option value="{{ $value }}" @selected(request('role') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All Statuses</option>@foreach(['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-tf-primary w-100">Filter</button></div>
    </form>
</div>

<x-table>
    <thead><tr><th>Staff</th><th>Employee ID</th><th>Role</th><th>Phone</th><th>Email</th><th>Joining Date</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($staff as $member)
        @php($hasImage = $member->profile_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($member->profile_image))
        <tr>
            <td><div class="d-flex align-items-center gap-2">@if($hasImage)<img src="{{ asset('storage/'.$member->profile_image) }}" class="navbar-avatar" alt="{{ $member->name }}">@else<span class="navbar-avatar tf-avatar-empty"><i class="bi bi-person"></i></span>@endif <strong>{{ $member->name }}</strong></div></td>
            <td>{{ $member->staffProfile?->employee_id ?? '-' }}</td>
            <td><span class="badge text-bg-primary">{{ $roles[$member->role] ?? $member->role }}</span></td>
            <td>{{ $member->phone }}</td>
            <td>{{ $member->email }}</td>
            <td>{{ $member->staffProfile?->joining_date?->format('M d, Y') ?? '-' }}</td>
            <td><span class="badge {{ $member->status === 'active' ? 'text-bg-success' : ($member->status === 'suspended' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ ucfirst($member->status) }}</span></td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="{{ route('business.staff.show', $member) }}">View</a>
                        <a class="dropdown-item" href="{{ route('business.staff.edit', $member) }}">Edit</a>
                        <a class="dropdown-item" href="{{ route('business.staff.edit', $member) }}#permissions">Permissions</a>
                        <form method="POST" action="{{ route('business.staff.status', $member) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="inactive"><button class="dropdown-item">Deactivate</button></form>
                        <form method="POST" action="{{ route('business.staff.destroy', $member) }}" onsubmit="return confirm('Delete this staff account?')">@csrf @method('DELETE')<button class="dropdown-item text-danger">Delete</button></form>
                    </div>
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center tf-muted py-4">No staff accounts.</td></tr>
    @endforelse
    </tbody>
</x-table>
{{ $staff->links() }}
</section>
@endsection
