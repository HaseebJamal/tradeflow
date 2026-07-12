@extends('layouts.dashboard')
@section('page-title', 'Audit Logs')
@section('page-subtitle', 'Review platform actions and security-relevant changes')
@section('content')
<div class="alert alert-light border mb-3"><i class="bi bi-shield-check me-1"></i>Audit records are immutable to preserve an accurate compliance trail.</div>
<div class="tf-card p-3 mb-3"><form method="GET" class="row g-3 align-items-end">
    <div class="col-md-6 col-xl-4"><label class="form-label">Search</label><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Search action or description"></div>
    <div class="col-md-6 col-xl-4"><label class="form-label">Company</label><select name="business_id" class="form-select"><option value="">All companies</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected((string) request('business_id') === (string) $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
    <div class="col-md-6 col-xl-2"><label class="form-label">Module</label><select name="module" class="form-select"><option value="">All modules</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>@endforeach</select></div>
    <div class="col-md-6 col-xl-2"><label class="form-label">User</label><select name="user_id" class="form-select"><option value="">All users</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></div>
    <div class="col-md-5 col-xl-3"><label class="form-label">Date From</label><input name="date_from" type="date" class="form-control" value="{{ request('date_from', now()->startOfMonth()->toDateString()) }}"></div>
    <div class="col-md-5 col-xl-3"><label class="form-label">Date To</label><input name="date_to" type="date" class="form-control" value="{{ request('date_to', now()->toDateString()) }}"></div>
    <div class="col-md-2 col-xl-3 d-grid"><button class="btn btn-tf-primary">Apply Filters</button></div>
    <div class="col-xl-3 d-grid"><a href="{{ route('admin.audit-logs') }}" class="btn btn-outline-secondary">Clear Filters</a></div>
</form></div>
<x-table><thead><tr><th>User</th><th>Module</th><th>Action</th><th>IP Address</th><th>Created At</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->user?->name ?? '-' }}</td><td>{{ $log->module ?? '-' }}</td><td>{{ $log->description ?: $log->action }}</td><td>{{ $log->ip_address }}</td><td><x-date-time :value="$log->created_at" /></td></tr>@empty<tr><td colspan="5" class="text-center tf-muted py-4">No logs match the selected filters.</td></tr>@endforelse</tbody></x-table><div class="mt-3">{{ $logs->links() }}</div>
@endsection
