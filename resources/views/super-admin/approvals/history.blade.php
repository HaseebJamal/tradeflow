@extends('layouts.dashboard')

@section('page-title', 'Approval History')
@section('page-subtitle', 'Every recorded company decision and access change')

@section('content')
<div class="tf-card p-4 mb-4">
    <form method="GET" class="row g-3">
        <div class="col-md-3"><label class="form-label">Company</label><select name="company_id" class="form-select"><option value="">All companies</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->business_name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Owner</label><select name="owner_id" class="form-select"><option value="">All owners</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected(request('owner_id') == $owner->id)>{{ $owner->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Old Status</label><select name="old_status" class="form-select"><option value="">All</option>@foreach(['Pending','Approved','Rejected','Suspended','Archived'] as $status)<option @selected(strtolower(request('old_status', '')) === strtolower($status))>{{ $status }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">New Status</label><select name="new_status" class="form-select"><option value="">All</option>@foreach(['Pending','Approved','Rejected','Suspended','Archived'] as $status)<option @selected(strtolower(request('new_status', '')) === strtolower($status))>{{ $status }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Changed By</label><select name="changed_by" class="form-select"><option value="">All admins</option>@foreach($admins as $admin)<option value="{{ $admin->id }}" @selected(request('changed_by') == $admin->id)>{{ $admin->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Date From</label><input name="date_from" type="date" class="form-control" value="{{ $filters['date_from'] }}"></div>
        <div class="col-md-3"><label class="form-label">Date To</label><input name="date_to" type="date" class="form-control" value="{{ $filters['date_to'] }}"></div>
        <div class="col-md-4"><label class="form-label">Search Note</label><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Decision note"></div>
        <div class="col-md-2 d-flex gap-2 align-items-end"><button class="btn btn-tf-primary flex-fill">Filter</button><a href="{{ route('admin.approvals.history') }}" class="btn btn-outline-secondary">Clear</a></div>
    </form>
</div>
<x-table><thead><tr><th>Company</th><th>Owner</th><th>Old Status</th><th>New Status</th><th>Note</th><th>Changed By</th><th>Changed At</th><th>Actions</th></tr></thead><tbody>@forelse($histories as $history)@php($badge = match(strtolower($history->new_status)) { 'approved' => 'tf-badge-success', 'pending' => 'tf-badge-warning', default => 'tf-badge-danger' })<tr><td>{{ $history->company?->business_name ?: 'Deleted company' }}</td><td>{{ $history->company?->owner?->name ?: '—' }}</td><td>{{ $history->old_status ? ucfirst($history->old_status) : '—' }}</td><td><span class="tf-badge {{ $badge }}">{{ ucfirst($history->new_status) }}</span></td><td>{{ \Illuminate\Support\Str::limit($history->note ?: '—', 55) }}</td><td>{{ $history->changedBy?->name ?: 'System / Public' }}</td><td><x-date-time :value="$history->changed_at" /></td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.companies.show', $history->company_id) }}">Company</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.approvals.history.show', $history) }}">Decision</a></td></tr>@empty<tr><td colspan="8" class="text-center tf-muted py-4">No approval decisions have been recorded yet.</td></tr>@endforelse</tbody></x-table><div class="mt-3">{{ $histories->links() }}</div>
@endsection
