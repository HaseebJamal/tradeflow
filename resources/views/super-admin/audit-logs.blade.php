@extends('layouts.dashboard')
@section('page-title', 'Audit Logs')
@section('page-subtitle', 'Track activity across the TradeFlow platform')
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

<div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
    @if($hasAuditLogPeriod)
        <a class="btn btn-outline-primary" href="{{ route('admin.audit-logs.export.csv', request()->query()) }}"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
        <a class="btn btn-outline-primary" href="{{ route('admin.audit-logs.export.pdf', request()->query()) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-filetype-pdf me-1"></i>PDF</a>
    @else
        <button class="btn btn-outline-secondary" type="button" disabled><i class="bi bi-filetype-csv me-1"></i>CSV</button>
        <button class="btn btn-outline-secondary" type="button" disabled><i class="bi bi-filetype-pdf me-1"></i>PDF</button>
    @endif
</div>

<div class="tf-card tf-audit-filter-card p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end" data-audit-period-filter>
        <div class="col-md-3"><label class="form-label">Search</label><input name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="User, action, business, or module"></div>
        <div class="col-md-3"><label class="form-label">Company</label><select name="business_id" class="form-select"><option value="">All companies</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected(request('business_id') == $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">User</label><select name="user_id" class="form-select"><option value="">All users</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Role</label><select name="role" class="form-select"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Module</label><select name="module" class="form-select"><option value="">All modules</option>@foreach($modules as $module)@php($moduleOption = (object) ['module' => $module])<option value="{{ $module }}" @selected(request('module') === $module)><x-activity-label :activity="$moduleOption" field="module" /></option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Action</label><select name="action" class="form-select"><option value="">All actions</option>@foreach($actions as $action)@php($actionOption = (object) ['action' => $action, 'route' => $action])<option value="{{ $action }}" @selected(request('action') === $action)><x-activity-label :activity="$actionOption" /></option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ $auditDateFrom }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Time From</label><input type="time" name="time_from" value="{{ $auditTimeFrom }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ $auditDateTo }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Time To</label><input type="time" name="time_to" value="{{ $auditTimeTo }}" class="form-control"></div>
        <div class="col-md-1"><label class="form-label">Month</label><select name="month" class="form-select"><option value="">All</option>@for($month = 1; $month <= 12; $month++)<option value="{{ $month }}" @selected((int) $auditMonth === $month)>{{ $month }}</option>@endfor</select></div>
        <div class="col-md-1"><label class="form-label">Year</label><input type="number" min="2000" max="2100" step="1" name="year" value="{{ $auditYear }}" class="form-control" placeholder="{{ now()->year }}"></div>
        <div class="col-md-2"><label class="form-label">IP Address</label><input name="ip_address" value="{{ request('ip_address') }}" class="form-control" placeholder="IPv4 or IPv6"></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary flex-fill">Filter</button><a href="{{ route('admin.audit-logs') }}" class="btn btn-outline-secondary">Clear</a></div>
    </form>
</div>

@if($hasAuditLogPeriod)
<x-table class="tf-admin-data-table tf-audit-data-table">
    <thead><tr><th>Date &amp; Time</th><th>Actor</th><th>Action</th><th>Module</th><th>Target</th><th>Description</th><th>View</th></tr></thead>
    <tbody>
    @forelse($logs as $log)
        @php($auditTarget = $log->record_type ? class_basename($log->record_type).($log->record_id ? ' #'.$log->record_id : '') : ($log->business?->business_name ?: 'Platform'))
        <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($log->occurred_at ?? $log->created_at)->timezone(config('app.timezone'))->format('n/j/Y, g:i A') }}</td>
            <td><strong>{{ $log->user_name ?: $log->user?->name ?: 'System' }}</strong><small class="d-block tf-muted">{{ $log->role ?: $log->actor_role ?: 'system' }}</small></td>
            <td><x-activity-label :activity="$log" /></td>
            <td><x-activity-label :activity="$log" field="module" /></td>
            <td>{{ $auditTarget }}</td>
            <td title="{{ $log->description }}">{{ str($log->description ?: $log->action)->limit(64) }}</td>
            <td><button type="button" class="btn btn-sm btn-outline-primary tf-table-view-action" data-bs-toggle="modal" data-bs-target="#auditLogModal{{ $log->id }}">View</button></td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center tf-muted py-4">No audit activity has been recorded yet.</td></tr>
    @endforelse
    </tbody>
</x-table>
@foreach($logs as $log)
    @php($auditTarget = $log->record_type ? class_basename($log->record_type).($log->record_id ? ' #'.$log->record_id : '') : ($log->business?->business_name ?: 'Platform'))
    <div class="modal fade" id="auditLogModal{{ $log->id }}" tabindex="-1" aria-labelledby="auditLogModalTitle{{ $log->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><div><span class="tf-dashboard-eyebrow">Read-only audit record</span><h2 class="modal-title" id="auditLogModalTitle{{ $log->id }}">Audit log details</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><dl class="row mb-0 small"><dt class="col-sm-4">Date &amp; time</dt><dd class="col-sm-8"><x-date-time :value="$log->occurred_at ?? $log->created_at" /></dd><dt class="col-sm-4">Actor</dt><dd class="col-sm-8">{{ $log->user_name ?: $log->user?->name ?: 'System' }} <span class="tf-muted">({{ $log->role ?: $log->actor_role ?: 'system' }})</span></dd><dt class="col-sm-4">Action</dt><dd class="col-sm-8"><x-activity-label :activity="$log" /></dd><dt class="col-sm-4">Module</dt><dd class="col-sm-8"><x-activity-label :activity="$log" field="module" /></dd><dt class="col-sm-4">Target</dt><dd class="col-sm-8">{{ $auditTarget }}</dd><dt class="col-sm-4">Description</dt><dd class="col-sm-8 text-break">{{ $log->description ?: '—' }}</dd><dt class="col-sm-4">Route</dt><dd class="col-sm-8 text-break">{{ $log->route ?: '—' }}</dd><dt class="col-sm-4">IP address</dt><dd class="col-sm-8">{{ \App\Services\AuditIpResolver::display($log->ip_address) }}</dd></dl></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
    </div>
@endforeach
<div class="mt-3 audit-log-pagination"><x-table-result-summary :paginator="$logs" />{{ $logs->links('pagination::bootstrap-5') }}</div>
@else
    <div class="tf-card p-4 text-center tf-muted">Please select a date/time range to view audit logs.</div>
@endif
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
