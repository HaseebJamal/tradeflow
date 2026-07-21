@extends('layouts.dashboard')
@section('page-title', 'Audit Logs')
@section('page-subtitle', 'Track activity across your business workspace')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@php
    $auditDateFrom = $filters['date_from'] ?: now()->toDateString();
    $auditDateTo = $filters['date_to'] ?: now()->toDateString();
    $auditTimeFrom = $filters['time_from'] ?: now()->startOfDay()->format('H:i');
    $auditTimeTo = $filters['time_to'] ?: now()->format('H:i');
    $auditMonth = $filters['month'] ?: now()->month;
    $auditYear = $filters['year'] ?: now()->year;
@endphp
<div class="d-flex flex-wrap align-items-center justify-content-end gap-3 mb-3">
    <div class="d-flex gap-2">
        @companyCan('audit_logs.export')
            <a class="btn btn-outline-primary" href="{{ route('business.audit-logs.export.csv', request()->query()) }}"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
            <a class="btn btn-outline-primary" href="{{ route('business.audit-logs.export.pdf', request()->query()) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-filetype-pdf me-1"></i>PDF</a>
        @endcompanyCan
    </div>
</div>
<div class="tf-card p-3 mb-3"><form method="GET" class="row g-2 align-items-end" data-audit-period-filter>
    <div class="col-md-3"><label class="form-label">User</label><select name="user_id" class="form-select"><option value="">All users</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Role</label><select name="role" class="form-select"><option value="">All roles</option>@foreach($users->pluck('role')->filter()->unique()->sort() as $role)<option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Module</label><select name="module" class="form-select"><option value="">All modules</option>@foreach($modules as $module)@php($moduleOption = (object) ['module' => $module])<option value="{{ $module }}" @selected(request('module') === $module)><x-activity-label :activity="$moduleOption" field="module" /></option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Action</label><select name="action" class="form-select"><option value="">All actions</option>@foreach($actions as $action)@php($actionOption = (object) ['action' => $action, 'route' => $action])<option value="{{ $action }}" @selected(request('action') === $action)><x-activity-label :activity="$actionOption" /></option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" max="{{ now()->toDateString() }}" value="{{ $auditDateFrom }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Time From</label><input type="time" name="time_from" value="{{ $auditTimeFrom }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" max="{{ now()->toDateString() }}" value="{{ $auditDateTo }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Time To</label><input type="time" name="time_to" value="{{ $auditTimeTo }}" class="form-control"></div>
    <div class="col-md-1"><label class="form-label">Month</label><select name="month" class="form-select"><option value="">All</option>@for($month = 1; $month <= 12; $month++)<option value="{{ $month }}" @selected((int) $auditMonth === $month)>{{ $month }}</option>@endfor</select></div>
    <div class="col-md-1"><label class="form-label">Year</label><input type="number" min="2000" max="2100" step="1" name="year" value="{{ $auditYear }}" class="form-control" placeholder="{{ now()->year }}"></div>
    <div class="col-md-2"><label class="form-label">IP Address</label><input name="ip_address" value="{{ request('ip_address') }}" class="form-control" placeholder="IPv4 or IPv6"></div>
    <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary flex-fill">Filter</button><a href="{{ route('business.audit-logs.index') }}" class="btn btn-outline-secondary">Clear</a></div>
</form></div>
<x-table><thead><tr><th>Date &amp; Time</th><th>User</th><th>Role</th><th>Module</th><th>Action</th><th>IP Address</th></tr></thead><tbody>
@forelse($logs as $log)
    <tr><td><x-date-time :value="$log->occurred_at ?? $log->created_at" /></td><td>{{ $log->user_name ?: $log->user?->name ?: 'System' }}</td><td>{{ $log->role ?: $log->actor_role ?: 'system' }}</td><td><x-activity-label :activity="$log" field="module" /></td><td><x-activity-label :activity="$log" /></td><td>{{ \App\Services\AuditIpResolver::display($log->ip_address) }}</td></tr>
@empty
    <tr><td colspan="6" class="text-center tf-muted py-4">No audit activity has been recorded yet.</td></tr>
@endforelse
</tbody></x-table><div class="mt-3 audit-log-pagination">{{ $logs->links('pagination::bootstrap-5') }}</div>
@endsection
@push('scripts')
<style>
    .audit-log-pagination .pagination { margin-bottom: 0; }
    .audit-log-pagination svg { width: 1rem; height: 1rem; max-width: 1rem; max-height: 1rem; }
</style>
<script>
document.querySelectorAll('[data-audit-period-filter]').forEach((form) => {
    const dates = form.querySelectorAll('[name="date_from"], [name="date_to"], [name="time_from"], [name="time_to"]');
    const month = form.querySelector('[name="month"]');
    const year = form.querySelector('[name="year"]');
    let syncing = false;

    dates.forEach((input) => input.addEventListener('change', () => {
        if (syncing) return;
        syncing = true;
        if (month) month.value = '';
        if (year) year.value = '';
        syncing = false;
    }));
    [month, year].filter(Boolean).forEach((input) => input.addEventListener('change', () => {
        if (syncing || (!month?.value && !year?.value)) return;
        syncing = true;
        dates.forEach((date) => { date.value = ''; });
        syncing = false;
    }));
});
</script>
@endpush
