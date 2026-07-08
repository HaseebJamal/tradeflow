@extends('layouts.dashboard')
@section('page-title', 'Audit Logs')
@section('content')
<x-table><thead><tr><th>User</th><th>Action</th><th>IP Address</th><th>Created At</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->user?->name ?? '-' }}</td><td>{{ $log->action }}</td><td>{{ $log->ip_address }}</td><td>{{ $log->created_at->format('M d, Y h:i A') }}</td></tr>@empty<tr><td colspan="4" class="text-center tf-muted py-4">No logs.</td></tr>@endforelse</tbody></x-table>
@endsection
