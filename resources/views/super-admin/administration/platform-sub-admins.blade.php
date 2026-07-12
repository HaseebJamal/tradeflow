@extends('layouts.dashboard')
@section('page-title', 'Platform Sub-Admins')
@section('page-subtitle', 'Manage sub-admins under platform admins')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="tf-card p-4 mb-4">
    <h2 class="h5">Create Platform Sub-Admin</h2>
    <form method="POST" action="{{ route('admin.platform-users.store') }}" class="row g-3">@csrf
        <input type="hidden" name="role" value="platform_sub_admin">
        <div class="col-md-3"><input name="name" class="form-control" placeholder="Name" required></div>
        <div class="col-md-3"><input name="email" type="email" class="form-control" placeholder="Email" required></div>
        <div class="col-md-2"><input name="phone" class="form-control" placeholder="Phone"></div>
        <div class="col-md-3"><select name="parent_user_id" class="form-select" required><option value="">Parent Admin</option>@foreach($platformAdmins as $admin)<option value="{{ $admin->id }}">{{ $admin->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><select name="status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option><option value="suspended">Suspended</option></select></div>
        <div class="col-md-3"><input name="password" type="password" class="form-control" placeholder="Password" required></div>
        <div class="col-md-3"><input name="password_confirmation" type="password" class="form-control" placeholder="Confirm Password" required></div>
        <div class="col-12"><div class="row g-2">@foreach($permissions as $permission)<label class="col-md-3 form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}"> {{ $permission }}</label>@endforeach</div></div>
        <div class="col-12"><button class="btn btn-tf-primary">Create Sub-Admin</button></div>
    </form>
</div>
<x-table>
<thead><tr><th>Name</th><th>Parent Admin</th><th>Email</th><th>Status</th><th>Assigned Businesses</th><th>Permissions</th><th>Last Login</th><th>Last Activity</th><th>Created At</th><th>Actions</th></tr></thead>
<tbody>@forelse($subAdmins as $sub)<tr><td>{{ $sub->name }}</td><td>{{ $sub->parent?->name ?? '-' }}</td><td>{{ $sub->email }}</td><td>{{ $sub->status }}</td><td>{{ $sub->business_assignments_count }}</td><td>{{ count($sub->permissions ?? []) }}</td><td><x-date-time :value="$sub->last_login_at" /></td><td><x-date-time :value="$sub->last_activity_at" /></td><td><x-date-time :value="$sub->created_at" /></td><td><button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#sub-{{ $sub->id }}">Edit</button></td></tr>
<tr class="collapse" id="sub-{{ $sub->id }}"><td colspan="10"><form method="POST" action="{{ route('admin.platform-users.update', $sub) }}" class="row g-2">@csrf @method('PUT')<div class="col-md-3"><input name="name" value="{{ $sub->name }}" class="form-control"></div><div class="col-md-2"><input name="phone" value="{{ $sub->phone }}" class="form-control"></div><div class="col-md-3"><select name="parent_user_id" class="form-select">@foreach($platformAdmins as $admin)<option value="{{ $admin->id }}" @selected($sub->parent_user_id===$admin->id)>{{ $admin->name }}</option>@endforeach</select></div><div class="col-md-2"><select name="status" class="form-select"><option value="active" @selected($sub->status==='active')>Active</option><option value="inactive" @selected($sub->status==='inactive')>Inactive</option><option value="suspended" @selected($sub->status==='suspended')>Suspended</option></select></div><div class="col-md-2"><input name="password" type="password" class="form-control" placeholder="New password"></div><div class="col-md-2"><input name="password_confirmation" type="password" class="form-control" placeholder="Confirm"></div><div class="col-12">@foreach($permissions as $permission)<label class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission }}" @checked(in_array($permission, $sub->permissions ?? []))> {{ $permission }}</label>@endforeach</div><div class="col-12"><button class="btn btn-sm btn-tf-primary">Save</button></div></form></td></tr>
@empty<tr><td colspan="10" class="text-center tf-muted py-4">No sub-admins.</td></tr>@endforelse</tbody>
</x-table>
<div class="mt-3">{{ $subAdmins->links() }}</div>
@endsection
