@extends('layouts.dashboard')
@section('page-title', 'Platform Admins')
@section('page-subtitle', 'Create and manage admin portfolios')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="tf-card p-4 mb-4">
    <h2 class="h5">Create Platform Admin</h2>
    <form method="POST" action="{{ route('admin.platform-users.store') }}" class="row g-3" data-company-permission-form>@csrf
        <input type="hidden" name="role" value="platform_admin">
        <div class="col-md-3"><input name="name" class="form-control" placeholder="Name" required></div>
        <div class="col-md-3"><input name="email" type="email" class="form-control" placeholder="Email" required></div>
        <div class="col-md-2"><input name="phone" class="form-control" placeholder="Phone"></div>
        <div class="col-md-2"><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></div>
        <div class="col-md-3"><input name="password" type="password" class="form-control" placeholder="Password" required></div>
        <div class="col-md-3"><input name="password_confirmation" type="password" class="form-control" placeholder="Confirm Password" required></div>
        <div class="col-12"><label class="form-check fw-semibold mb-2" for="platform-admin-create-permission-master"><input id="platform-admin-create-permission-master" class="form-check-input" type="checkbox" data-permission-master> Select All Permissions <span class="tf-muted" data-permission-total-selected></span></label><div class="row g-2">@foreach($permissions as $permission)<label class="col-md-3 form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}" data-permission-child> {{ $permission }}</label>@endforeach</div></div>
        <div class="col-12"><button class="btn btn-tf-primary">Create Platform Admin</button></div>
    </form>
</div>
<div class="tf-card p-4 mb-4">
    <form class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label">Name</label><input name="name" value="{{ request('name') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Email</label><input name="email" value="{{ request('email') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option value="active" @selected(request('status')==='active')>Active</option><option value="suspended" @selected(request('status')==='suspended')>Suspended</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select></div>
        <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
    </form>
</div>
<x-table>
<thead><tr><th>Profile</th><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Businesses Managed</th><th>Sub-Admins</th><th>Last Login</th><th>Last Activity</th><th>Created At</th><th>Actions</th></tr></thead>
<tbody>@forelse($admins as $admin)<tr>
<td>@if($admin->profile_image)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($admin->profile_image) }}" class="navbar-avatar">@else<i class="bi bi-person-circle h4"></i>@endif</td>
<td>{{ $admin->name }}</td><td>{{ $admin->email }}</td><td>{{ $admin->phone ?: '-' }}</td><td>{{ $admin->status }}</td><td>{{ $admin->business_assignments_count }}</td><td>{{ $admin->children_count }}</td><td><x-date-time :value="$admin->last_login_at" /></td><td><x-date-time :value="$admin->last_activity_at" /></td><td><x-date-time :value="$admin->created_at" /></td>
<td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#admin-{{ $admin->id }}">Edit</button></td>
</tr><tr class="collapse" id="admin-{{ $admin->id }}"><td colspan="11"><form method="POST" action="{{ route('admin.platform-users.update', $admin) }}" class="row g-2" data-company-permission-form>@csrf @method('PUT')<div class="col-md-3"><input name="name" value="{{ $admin->name }}" class="form-control"></div><div class="col-md-2"><input name="phone" value="{{ $admin->phone }}" class="form-control"></div><div class="col-md-2"><select name="status" class="form-select"><option value="active" @selected($admin->status==='active')>Active</option><option value="inactive" @selected($admin->status==='inactive')>Inactive</option><option value="suspended" @selected($admin->status==='suspended')>Suspended</option></select></div><div class="col-md-2"><input name="password" type="password" class="form-control" placeholder="New password optional"></div><div class="col-md-2"><input name="password_confirmation" type="password" class="form-control" placeholder="Confirm"></div><div class="col-12"><label class="form-check fw-semibold mb-2" for="platform-admin-{{ $admin->id }}-permission-master"><input id="platform-admin-{{ $admin->id }}-permission-master" class="form-check-input" type="checkbox" data-permission-master> Select All Permissions <span class="tf-muted" data-permission-total-selected></span></label><div>@foreach($permissions as $permission)<label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}" data-permission-child @checked(in_array($permission, $admin->permissions ?? []))> {{ $permission }}</label>@endforeach</div></div><div class="col-12"><button class="btn btn-sm btn-tf-primary">Save</button></div></form></td></tr>
@empty<tr><td colspan="11" class="text-center tf-muted py-4">No platform admins.</td></tr>@endforelse</tbody>
</x-table>
<div class="mt-3">{{ $admins->links() }}</div>
@endsection
