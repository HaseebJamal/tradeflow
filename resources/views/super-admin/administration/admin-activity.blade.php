@extends('layouts.dashboard')
@section('page-title', 'Admin Activity')
@section('page-subtitle', 'Recent Super Admin, Platform Admin, and Sub-Admin activity')
@section('content')
<x-table>
<thead><tr><th>Time</th><th>User</th><th>Role</th><th>Module</th><th>Action</th><th>Description</th><th>IP Address</th></tr></thead>
<tbody>@forelse($activities as $activity)<tr><td><x-date-time :value="$activity->occurred_at" /></td><td>{{ $activity->actor_name_snapshot ?: $activity->actor?->name }}</td><td>{{ $activity->actor_role }}</td><td>{{ $activity->module }}</td><td>{{ $activity->action }}</td><td>{{ $activity->description }}</td><td>{{ $activity->ip_address }}</td></tr>@empty<tr><td colspan="7" class="text-center tf-muted py-4">No admin activity yet.</td></tr>@endforelse</tbody>
</x-table>
<div class="mt-3">{{ $activities->links() }}</div>
@endsection
