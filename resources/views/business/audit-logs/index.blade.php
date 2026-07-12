@extends('layouts.dashboard')
@section('page-title', 'Audit Logs')
@section('page-subtitle', 'Track activity across your business workspace')
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <div class="d-flex align-items-center gap-2"><span class="badge text-bg-danger" data-live-indicator>Live</span><span class="small tf-muted" data-live-label>Refreshing every second</span></div>
    <div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary" data-live-toggle>Pause Live Updates</button>
        @companyCan('audit_logs.export')
            <a class="btn btn-outline-primary" href="{{ route('business.audit-logs.export.csv', request()->query()) }}"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
            <a class="btn btn-outline-primary" href="{{ route('business.audit-logs.export.pdf', request()->query()) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-filetype-pdf me-1"></i>PDF</a>
        @endcompanyCan
    </div>
</div>
<div class="tf-card p-3 mb-3"><form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">User</label><select name="user_id" class="form-select"><option value="">All users</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Role</label><select name="role" class="form-select"><option value="">All roles</option>@foreach($users->pluck('role')->filter()->unique()->sort() as $role)<option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Module</label><select name="module" class="form-select"><option value="">All modules</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Action</label><select name="action" class="form-select"><option value="">All actions</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Search</label><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Description or route"></div>
    <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ request('date_from', now()->toDateString()) }}" class="form-control"></div><div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ request('date_to', now()->toDateString()) }}" class="form-control"></div><div class="col-md-2"><label class="form-label">IP Address</label><input name="ip_address" value="{{ request('ip_address') }}" class="form-control" placeholder="127.0.0.1"></div><div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary flex-fill">Filter</button><a href="{{ route('business.audit-logs.index') }}" class="btn btn-outline-secondary">Clear</a></div>
</form></div>
<x-table><thead><tr><th>Date & Time</th><th>User</th><th>Role</th><th>Module</th><th>Action</th><th>Description</th><th>IP Address</th><th class="text-end">Details</th></tr></thead><tbody data-audit-log-body>
@forelse($logs as $log)
    @php($detailPayload = base64_encode(json_encode($log->only('route', 'user_agent', 'record_type', 'record_id', 'old_values', 'new_values', 'description'))))
    <tr data-audit-log-id="{{ $log->id }}"><td><x-date-time :value="$log->occurred_at" /></td><td>{{ $log->user_name ?: $log->user?->name ?: 'System' }}</td><td>{{ $log->role ?: $log->actor_role ?: 'system' }}</td><td>{{ $log->module ?: 'General' }}</td><td>{{ $log->action }}</td><td>{{ $log->description ?: $log->action }}</td><td>{{ $log->ip_address ?: '-' }}</td><td class="text-end">
        @companyCan('audit_logs.view_details')
            <button type="button" class="btn btn-sm btn-outline-primary" data-audit-details="{{ $detailPayload }}">View</button>
        @endcompanyCan
    </td></tr>
@empty
    <tr data-audit-empty><td colspan="8" class="text-center tf-muted py-4">No audit activity has been recorded yet.</td></tr>
@endforelse
</tbody></x-table><div class="mt-3 audit-log-pagination">{{ $logs->links('pagination::bootstrap-5') }}</div>
<div class="modal fade audit-details-modal" id="auditDetailsModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="h5 modal-title">Audit Log Details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-sm-3">Description</dt><dd class="col-sm-9 text-break" data-detail-description>-</dd><dt class="col-sm-3">Route</dt><dd class="col-sm-9 text-break" data-detail-route>-</dd><dt class="col-sm-3">Related Record</dt><dd class="col-sm-9" data-detail-record>-</dd><dt class="col-sm-3">User Agent</dt><dd class="col-sm-9 text-break" data-detail-agent>-</dd></dl><h3 class="h6 mt-3">Old Values</h3><pre class="bg-light border rounded p-3 small audit-detail-values" data-detail-old>-</pre><h3 class="h6">New Values</h3><pre class="bg-light border rounded p-3 small audit-detail-values" data-detail-new>-</pre></div></div></div></div>
@endsection
@push('scripts')
<style>
    .audit-log-pagination .pagination { margin-bottom: 0; }
    .audit-log-pagination svg { width: 1rem; height: 1rem; max-width: 1rem; max-height: 1rem; }
    .audit-details-modal .modal-dialog { margin: 1rem auto; }
    .audit-details-modal .modal-content { max-height: calc(100vh - 2rem); }
    .audit-details-modal .modal-body { overscroll-behavior: contain; }
    .audit-detail-values { max-height: 14rem; overflow: auto; white-space: pre-wrap; word-break: break-word; }
</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.querySelector('[data-audit-log-body]'), modal = document.getElementById('auditDetailsModal'); if (!body || !modal) return;
    let paused = false, lastId = Math.max(0, ...[...body.querySelectorAll('[data-audit-log-id]')].map((row) => Number(row.dataset.auditLogId)));
    const indicator = document.querySelector('[data-live-indicator]'), label = document.querySelector('[data-live-label]'), toggle = document.querySelector('[data-live-toggle]');
    const esc = (value) => String(value ?? '-').replace(/[&<>'"]/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
    const show = (data) => { modal.querySelector('[data-detail-description]').textContent = data.description || '-'; modal.querySelector('[data-detail-route]').textContent = data.route || '-'; modal.querySelector('[data-detail-record]').textContent = [data.record_type, data.record_id ? '#' + data.record_id : ''].filter(Boolean).join(' ') || '-'; modal.querySelector('[data-detail-agent]').textContent = data.user_agent || '-'; modal.querySelector('[data-detail-old]').textContent = JSON.stringify(data.old_values || {}, null, 2); modal.querySelector('[data-detail-new]').textContent = JSON.stringify(data.new_values || {}, null, 2); modal.querySelector('.modal-body').scrollTop = 0; bootstrap.Modal.getOrCreateInstance(modal).show(); };
    const row = (log) => '<tr data-audit-log-id="' + log.id + '"><td>' + esc(log.occurred_at) + '</td><td>' + esc(log.user) + '</td><td>' + esc(log.role) + '</td><td>' + esc(log.module) + '</td><td>' + esc(log.action) + '</td><td>' + esc(log.description) + '</td><td>' + esc(log.ip_address) + '</td><td class="text-end">' + (log.has_details ? '<button type="button" class="btn btn-sm btn-outline-primary" data-live-detail="' + encodeURIComponent(JSON.stringify(log)) + '">View</button>' : '') + '</td></tr>';
    body.addEventListener('click', (event) => { const button = event.target.closest('[data-audit-details], [data-live-detail]'); if (!button) return; const data = button.dataset.auditDetails ? JSON.parse(atob(button.dataset.auditDetails)) : JSON.parse(decodeURIComponent(button.dataset.liveDetail)); show(data); });
    toggle.addEventListener('click', () => { paused = !paused; indicator.className = paused ? 'badge text-bg-secondary' : 'badge text-bg-danger'; indicator.textContent = paused ? 'Paused' : 'Live'; label.textContent = paused ? 'Live updates paused' : 'Refreshing every second'; toggle.textContent = paused ? 'Resume Live Updates' : 'Pause Live Updates'; });
    const poll = async () => { if (paused) return; try { const response = await fetch('{{ route('business.audit-logs.live') }}?after_id=' + lastId, {headers: {'Accept':'application/json'}}); if (!response.ok) return; const data = await response.json(); if (!data.logs.length) return; body.querySelector('[data-audit-empty]')?.remove(); data.logs.forEach((log) => { if (!body.querySelector('[data-audit-log-id="' + log.id + '"]')) body.insertAdjacentHTML('afterbegin', row(log)); }); lastId = data.last_id || lastId; } catch (_) {} };
    setInterval(poll, 1000);
});
</script>
@endpush