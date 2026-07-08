@extends('layouts.dashboard')
@section('page-title', 'Support Tickets')
@section('page-subtitle', 'Reply and update ticket status')
@section('content')
<x-table><thead><tr><th>Business</th><th>Subject</th><th>Message</th><th>Reply / Status</th></tr></thead><tbody>@forelse($tickets as $ticket)<tr><td>{{ $ticket->business?->business_name ?? '-' }}</td><td>{{ $ticket->subject }}</td><td>{{ $ticket->message }}</td><td><form method="POST" action="{{ route('admin.support-tickets.update',$ticket) }}" class="d-grid gap-2">@csrf @method('PATCH')<textarea name="admin_reply" class="form-control" rows="2" placeholder="Admin reply">{{ $ticket->admin_reply }}</textarea><select name="status" class="form-select"><option @selected($ticket->status==='Open')>Open</option><option @selected($ticket->status==='Pending')>Pending</option><option @selected($ticket->status==='Closed')>Closed</option></select><button class="btn btn-sm btn-outline-primary">Save</button></form></td></tr>@empty<tr><td colspan="4" class="text-center tf-muted py-4">No tickets.</td></tr>@endforelse</tbody></x-table>
@endsection
