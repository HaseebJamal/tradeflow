@extends('layouts.dashboard')
@section('page-title', 'Roles & Users')
@section('page-subtitle', 'Manage users, custom roles, permissions, and account access')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row g-3 mb-4">
@foreach([
    ['Total Users', $stats['total'], 'bi-people', 'bg-blue'],
    ['Active Users', $stats['active'], 'bi-person-check', 'bg-green'],
    ['Inactive Users', $stats['inactive'], 'bi-person-dash', 'bg-amber'],
    ['Custom Roles', $stats['roles'], 'bi-person-badge', 'bg-navy'],
    ['With Permissions', $stats['with_permissions'], 'bi-shield-check', 'bg-blue'],
    ['Suspended', $stats['suspended'], 'bi-person-slash', 'bg-green'],
] as [$label, $value, $icon, $color])
    <div class="col-md-6 col-xl-2">@include('components.card', ['label' => $label, 'value' => $value, 'icon' => $icon, 'color' => $color, 'note' => ''])</div>
@endforeach
</div>

@companyCan('staff.create')<div class="tf-card p-4 mb-4">
    <h2 class="h5 mb-3">Create User Account</h2>
    @include('business.staff._form', ['staffMember' => null])
</div>@endcompanyCan

<section id="staff-results">
<div class="tf-card p-4 mb-4 staff-filter-card">
    <form method="GET" action="{{ route('business.staff') }}#staff-results" class="row g-3 align-items-end">
        <div class="col-md-4"><label class="form-label">Search</label><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Name, email, phone, employee ID"></div>
        <div class="col-md-3"><label class="form-label">Custom Role</label><select name="role" class="form-select"><option value="">All Custom Roles</option>@foreach($customRoleNames as $roleName)<option value="{{ $roleName }}" @selected(request('role') === $roleName)>{{ $roleName }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">Active Staff</option>@foreach(['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended','archived'=>'Archived'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-tf-primary w-100">Filter</button></div>
    </form>
</div>

<x-table class="staff-table-wrap">
    <thead><tr><th>User</th><th>Employee ID</th><th>Role</th><th>Phone</th><th>Email</th><th>Joining Date</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($staff as $member)
        @php($hasImage = $member->profile_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($member->profile_image))
        <tr>
            <td><div class="d-flex align-items-center gap-2">@if($hasImage)<img src="{{ asset('storage/'.$member->profile_image) }}" class="navbar-avatar" alt="{{ $member->name }}">@else<span class="navbar-avatar tf-avatar-empty"><i class="bi bi-person"></i></span>@endif <strong>{{ $member->name }}</strong></div></td>
            <td>{{ $member->staffProfile?->employee_id ?? '-' }}</td>
            <td><span class="badge text-bg-primary">{{ $member->role === 'custom_staff' ? ($member->staffProfile?->custom_role_name ?: 'Custom Staff') : ($roles[$member->role] ?? $member->role) }}</span></td>
            <td>{{ $member->phone }}</td>
            <td>{{ $member->email }}</td>
            <td>{{ $member->staffProfile?->joining_date?->format('M d, Y') ?? '-' }}</td>
            <td><span class="badge {{ $member->status === 'active' ? 'text-bg-success' : ($member->status === 'suspended' ? 'text-bg-danger' : ($member->status === 'archived' ? 'text-bg-secondary' : 'text-bg-warning')) }}">{{ ucfirst($member->status) }}</span></td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-display="dynamic" aria-expanded="false">Actions</button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="{{ route('business.staff.show', $member) }}">View</a>
                        <a class="dropdown-item" href="{{ route('business.staff.edit', $member) }}">Edit</a>
                        <a class="dropdown-item" href="{{ route('business.staff.edit', $member) }}#permissions">Manage Permissions</a>
                        <a class="dropdown-item" href="{{ route('business.staff.edit', $member) }}#staff-role">Change Role</a>
                        <a class="dropdown-item" href="{{ route('business.staff.show', $member) }}#reset-password">Reset Password</a>
                        @if($member->status === 'archived')
                            <form method="POST" action="{{ route('business.staff.restore', $member) }}">@csrf @method('PATCH')<button class="dropdown-item">Restore as Inactive</button></form>
                            @if($member->can_delete)
                                <form method="POST" action="{{ route('business.staff.destroy', $member) }}" onsubmit="return confirm('Delete this archived staff account? This cannot be undone.')">@csrf @method('DELETE')<button class="dropdown-item text-danger">Delete</button></form>
                            @endif
                        @else
                            @if($member->status !== 'active')<form method="POST" action="{{ route('business.staff.status', $member) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="active"><button class="dropdown-item text-success">Activate</button></form>@endif
                            @if($member->status !== 'inactive')<form method="POST" action="{{ route('business.staff.status', $member) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="inactive"><button class="dropdown-item">Deactivate</button></form>@endif
                            @if($member->status !== 'suspended')<form method="POST" action="{{ route('business.staff.status', $member) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="suspended"><button class="dropdown-item text-danger">Suspend</button></form>@endif
                            <form method="POST" action="{{ route('business.staff.archive', $member) }}" onsubmit="return confirm('Archive this staff account? Historical records will be retained.')">@csrf @method('PATCH')<button class="dropdown-item text-danger">Archive</button></form>
                            @if($member->can_delete)
                                <form method="POST" action="{{ route('business.staff.destroy', $member) }}" onsubmit="return confirm('Delete this staff account? This cannot be undone.')">@csrf @method('DELETE')<button class="dropdown-item text-danger">Delete</button></form>
                            @endif
                        @endif
                    </div>
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="8" class="text-center tf-muted py-4">No user accounts.</td></tr>
    @endforelse
    </tbody>
</x-table>
{{ $staff->links() }}
</section>
@endsection
