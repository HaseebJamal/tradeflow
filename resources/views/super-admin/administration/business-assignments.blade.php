@extends('layouts.dashboard')
@section('page-title', 'Business Assignments')
@section('page-subtitle', 'Assign businesses to admins, sub-admins, owners, and auditors')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="tf-card p-4 mb-4">
    <form method="POST" action="{{ route('admin.business-assignments.store') }}" class="row g-3">@csrf
        <div class="col-md-4"><label class="form-label">Business</label><select name="business_id" class="form-select" required>@foreach($businesses as $business)<option value="{{ $business->id }}">{{ $business->business_name }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">User</label><select name="user_id" class="form-select" required>@foreach($admins as $admin)<option value="{{ $admin->id }}">{{ $admin->name }} ({{ $admin->role }})</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Assignment Role</label><select name="assignment_role" class="form-select">@foreach(['portfolio_admin','portfolio_sub_admin','business_owner','business_manager','support_manager','read_only_auditor'] as $role)<option value="{{ $role }}">{{ str_replace('_', ' ', $role) }}</option>@endforeach</select></div>
        <div class="col-md-1 d-flex align-items-end"><button class="btn btn-tf-primary w-100">Save</button></div>
    </form>
</div>
<x-table>
<thead><tr><th>Business</th><th>Owner</th><th>User</th><th>Role</th><th>Assigned By</th><th>Assigned At</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>@forelse($assignments as $assignment)<tr><td>{{ $assignment->business?->business_name }}</td><td>{{ $assignment->business?->owner?->name ?? '-' }}</td><td>{{ $assignment->user?->name }} ({{ $assignment->user?->role }})</td><td>{{ str_replace('_', ' ', $assignment->assignment_role) }}</td><td>{{ $assignment->assigner?->name ?? '-' }}</td><td><x-date-time :value="$assignment->assigned_at" /></td><td>{{ $assignment->status }}</td><td><form method="POST" action="{{ route('admin.business-assignments.revoke', $assignment) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger">Revoke</button></form></td></tr>@empty<tr><td colspan="8" class="text-center tf-muted py-4">No active assignments.</td></tr>@endforelse</tbody>
</x-table>
<div class="mt-3">{{ $assignments->links() }}</div>
@endsection
