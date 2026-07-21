@extends('layouts.dashboard')
@section('page-title', 'Live Activity')
@section('page-subtitle', 'Platform-wide operational activity feed')
@section('content')
<div class="tf-card p-4 mb-4">
    <form class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label">Role</label><input name="role" value="{{ request('role') }}" class="form-control" placeholder="Role"></div>
        <div class="col-md-2"><label class="form-label">Business</label><select name="business_id" class="form-select"><option value="">All</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected(request('business_id') == $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Module</label><input name="module" value="{{ request('module') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Action</label><input name="action" value="{{ request('action') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Search</label><input name="search" value="{{ request('search') }}" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
        <div class="col-md-2"><button type="button" class="btn btn-outline-secondary w-100" data-live-toggle>Live</button></div>
    </form>
</div>
<x-table>
<thead><tr><th>Time</th><th>User</th><th>Role</th><th>Business</th><th>Module</th><th>Action</th><th>IP Address</th><th>Device/Browser</th></tr></thead>
<tbody>@forelse($activities as $activity)<tr><td><x-date-time :value="$activity->occurred_at" /></td><td>{{ $activity->actor_name_snapshot ?: $activity->actor?->name }}</td><td>{{ $activity->actor_role }}</td><td>{{ $activity->business?->business_name ?? '-' }}</td><td><x-activity-label :activity="$activity" field="module" /></td><td><x-activity-label :activity="$activity" /></td><td>{{ \App\Services\AuditIpResolver::display($activity->ip_address) }}</td><td class="text-truncate" style="max-width:220px">{{ $activity->user_agent }}</td></tr>@empty<tr><td colspan="8" class="text-center tf-muted py-4">No activity recorded.</td></tr>@endforelse</tbody>
</x-table>
<div class="mt-3">{{ $activities->links() }}</div>
<script>
let tfLiveActivity = true;
document.querySelector('[data-live-toggle]')?.addEventListener('click', (event) => {
    tfLiveActivity = !tfLiveActivity;
    event.currentTarget.textContent = tfLiveActivity ? 'Live' : 'Paused';
});
setInterval(() => { if (tfLiveActivity) window.location.reload(); }, 15000);
</script>
@endsection
