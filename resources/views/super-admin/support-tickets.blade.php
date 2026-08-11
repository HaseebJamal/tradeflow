@extends('layouts.dashboard')

@section('page-title', 'Complaints & Support')
@section('page-subtitle', 'Review, respond to, and resolve support conversations')

@php($statuses = ['Open', 'Assigned', 'In Progress', 'Waiting for User', 'Escalated', 'Resolved', 'Closed', 'Reopened', 'Pending'])
@php($statusClasses = ['Resolved' => 'tf-badge-success', 'Closed' => 'tf-badge-secondary', 'In Progress' => 'tf-badge-warning', 'Waiting for User' => 'tf-badge-warning', 'Escalated' => 'tf-badge-warning', 'Pending' => 'tf-badge-warning', 'Assigned' => 'tf-badge-warning'])
@php($priorityClasses = ['Urgent' => 'tf-badge-danger', 'High' => 'tf-badge-warning', 'Medium' => 'tf-badge-info', 'Low' => 'tf-badge-secondary'])
@php($today = now(config('app.timezone'))->toDateString())

@section('content')
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

<div class="tf-card p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-3"><label class="form-label">Search</label><input name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Ticket, sender, or subject"></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label">Type</label><select name="type" class="form-select"><option value="">All types</option>@foreach($ticketTypes as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? null) === $type)>{{ $type }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label">Priority</label><select name="priority" class="form-select"><option value="">All priorities</option>@foreach(['Low', 'Medium', 'High', 'Urgent'] as $priority)<option value="{{ $priority }}" @selected(($filters['priority'] ?? null) === $priority)>{{ $priority }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-1"><label class="form-label">From</label><input type="date" name="date_from" value="{{ ($filters['date_from'] ?? null) ?: $today }}" class="form-control"></div>
        <div class="col-sm-6 col-lg-1"><label class="form-label">To</label><input type="date" name="date_to" value="{{ ($filters['date_to'] ?? null) ?: $today }}" class="form-control"></div>
        <div class="col-sm-6 col-lg-1 d-flex gap-1"><button class="btn btn-outline-primary flex-fill">Filter</button><a href="{{ route('admin.support-tickets', ['clear' => 1]) }}" class="btn btn-outline-secondary" aria-label="Clear filters"><i class="bi bi-arrow-counterclockwise"></i></a></div>
    </form>
</div>

<x-table class="tf-admin-data-table tf-support-ticket-table">
    <thead><tr><th>Ticket</th><th>Sender</th><th>Source / Type</th><th>Subject</th><th>Priority</th><th>Status</th><th>Handler</th><th>Submitted</th><th>Actions</th></tr></thead>
    <tbody>
        @forelse($tickets as $ticket)
            @php($isFinalized = in_array($ticket->status, ['Resolved', 'Closed'], true))
            <tr>
                <td><strong>{{ $ticket->ticket_number ?: 'TF-TKT-'.$ticket->id }}</strong></td>
                <td>@if($ticket->contact_name)<strong>{{ $ticket->contact_name }}</strong><small class="d-block tf-muted">{{ $ticket->contact_email }}</small>@else{{ $ticket->user?->name ?? $ticket->business?->business_name ?? '—' }}@endif</td>
                <td><span class="tf-badge {{ $ticket->source === 'Public Contact' ? 'tf-badge-info' : 'tf-badge-secondary' }}">{{ $ticket->source ?? 'Workspace' }}</span><small class="d-block tf-muted mt-1">{{ $ticket->type ?? 'Other' }}</small></td>
                <td><strong class="d-block">{{ $ticket->subject }}</strong><small class="d-block tf-muted text-truncate" style="max-width:220px">{{ $ticket->message ?: $ticket->description }}</small></td>
                <td><span class="tf-badge {{ $priorityClasses[$ticket->priority] ?? 'tf-badge-secondary' }}">{{ $ticket->priority }}</span></td>
                <td><span class="tf-badge {{ $statusClasses[$ticket->status] ?? 'tf-badge-info' }}">{{ $ticket->status }}</span></td>
                <td>{{ $ticket->assignedAdmin?->name ?? 'Unassigned' }}</td>
                <td><x-date-time :value="$ticket->submitted_at ?: $ticket->created_at" /></td>
                <td><div class="d-flex align-items-center gap-1"><button type="button" class="btn btn-sm {{ $isFinalized ? 'btn-outline-primary' : 'btn-tf-primary' }}" data-bs-toggle="modal" data-bs-target="#ticketManageModal-{{ $ticket->id }}">{{ $isFinalized ? 'View' : 'Manage' }}</button><div class="dropdown"><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-label="Ticket actions"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#ticketManageModal-{{ $ticket->id }}">View details</button></li><li><button type="button" class="dropdown-item" data-ticket-modal-focus="conversation" data-bs-toggle="modal" data-bs-target="#ticketManageModal-{{ $ticket->id }}">View history</button></li>@if(! $isFinalized)<li><button type="button" class="dropdown-item" data-ticket-set-status="Closed" data-bs-toggle="modal" data-bs-target="#ticketManageModal-{{ $ticket->id }}">Close ticket</button></li>@endif</ul></div></div></td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center tf-muted py-4">No tickets found.</td></tr>
        @endforelse
    </tbody>
</x-table>

<div class="mt-3"><x-table-result-summary :paginator="$tickets" />{{ $tickets->links() }}</div>

@foreach($tickets as $ticket)
    @php($isFinalized = in_array($ticket->status, ['Resolved', 'Closed'], true))
    <div class="modal fade tf-ticket-modal" id="ticketManageModal-{{ $ticket->id }}" tabindex="-1" aria-labelledby="ticketManageTitle-{{ $ticket->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form method="POST" action="{{ route('admin.support-tickets.update', $ticket) }}" class="modal-content" @unless($isFinalized) data-ticket-manage-form @endunless>
                @csrf @method('PATCH')
                <div class="modal-header">
                    <div class="min-w-0"><span class="tf-ticket-reference">{{ $ticket->ticket_number ?: 'TF-TKT-'.$ticket->id }}</span><h2 class="modal-title" id="ticketManageTitle-{{ $ticket->id }}">{{ $ticket->subject }}</h2><div class="d-flex flex-wrap gap-1 mt-2"><span class="tf-badge {{ $ticket->source === 'Public Contact' ? 'tf-badge-info' : 'tf-badge-secondary' }}">{{ $ticket->source ?? 'Workspace' }}</span><span class="tf-badge tf-badge-secondary">{{ $ticket->type ?? 'Other' }}</span><span class="tf-badge {{ $priorityClasses[$ticket->priority] ?? 'tf-badge-secondary' }}">{{ $ticket->priority }}</span><span class="tf-badge {{ $statusClasses[$ticket->status] ?? 'tf-badge-info' }}">{{ $ticket->status }}</span></div></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <section class="tf-ticket-section"><h3>Sender details</h3><div class="tf-ticket-detail-grid"><div><span>Name</span><strong>{{ $ticket->contact_name ?? $ticket->user?->name ?? 'Not provided' }}</strong></div><div><span>Email</span><strong>{{ $ticket->contact_email ?? $ticket->user?->email ?? 'Not provided' }}</strong></div><div><span>Phone</span><strong>{{ $ticket->contact_phone ?? $ticket->user?->phone ?? 'Not provided' }}</strong></div><div><span>Business</span><strong>{{ $ticket->business?->business_name ?? 'Not linked' }}</strong></div><div><span>Source</span><strong>{{ $ticket->source ?? 'Workspace' }}</strong></div><div><span>Submitted</span><strong><x-date-time :value="$ticket->submitted_at ?: $ticket->created_at" /></strong></div></div></section>
                    <section class="tf-ticket-section"><h3>Message</h3><div class="tf-ticket-message">{{ $ticket->message ?: $ticket->description ?: 'No message provided.' }}</div></section>
                    <section class="tf-ticket-section"><h3>Ticket details</h3><div class="tf-ticket-detail-grid"><div><span>Priority</span><strong>{{ $ticket->priority }}</strong></div><div><span>Final status</span><strong>{{ $ticket->status }}</strong></div><div><span>Handler</span><strong>{{ $ticket->assignedAdmin?->name ?? 'Unassigned' }}</strong></div><div><span>Created</span><strong><x-date-time :value="$ticket->created_at" /></strong></div><div><span>Updated</span><strong><x-date-time :value="$ticket->updated_at" /></strong></div>@if($ticket->resolved_at)<div><span>Resolved</span><strong><x-date-time :value="$ticket->resolved_at" /></strong></div>@endif @if($ticket->closed_at)<div><span>Closed</span><strong><x-date-time :value="$ticket->closed_at" /></strong></div>@endif</div></section>
                    @unless($isFinalized)
                    <section class="tf-ticket-section"><h3>Ticket controls</h3><div class="row g-3"><div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select" data-ticket-status>@foreach($statuses as $status)<option value="{{ $status }}" @selected($ticket->status === $status)>{{ $status }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Priority</label><select name="priority" class="form-select">@foreach(['Low', 'Medium', 'High', 'Urgent'] as $priority)<option value="{{ $priority }}" @selected($ticket->priority === $priority)>{{ $priority }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Handler</label><select name="assigned_admin_id" class="form-select"><option value="">Unassigned</option>@foreach($supportHandlers as $handler)<option value="{{ $handler->id }}" @selected($ticket->assigned_admin_id === $handler->id)>{{ $handler->name }}</option>@endforeach</select></div><div class="col-12 {{ in_array($ticket->status, ['Resolved', 'Closed'], true) || filled($ticket->resolution) ? '' : 'd-none' }}" data-ticket-resolution><label class="form-label">Resolution</label><textarea name="resolution" class="form-control" rows="3" placeholder="Optional resolution summary">{{ $ticket->resolution }}</textarea></div></div></section>
                    <section class="tf-ticket-section"><div class="d-flex align-items-center justify-content-between gap-2"><h3 class="mb-0">Reply to customer</h3><small class="tf-muted">Replies are saved to this ticket.</small></div><textarea name="message" class="form-control mt-2" rows="4" maxlength="2000" placeholder="Write a concise response…" data-ticket-reply></textarea><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="internal_note" value="1" id="ticket-note-{{ $ticket->id }}" data-ticket-internal-note><label class="form-check-label" for="ticket-note-{{ $ticket->id }}">Save as internal note (visible to platform staff only)</label></div><div class="invalid-feedback d-block d-none" data-ticket-reply-error>Enter a reply or internal note before sending.</div></section>
                    <section class="tf-ticket-section" data-ticket-conversation><h3>Conversation &amp; activity</h3><div class="tf-ticket-timeline"><article class="tf-ticket-timeline-item"><span class="tf-ticket-timeline-icon"><i class="bi bi-ticket-perforated"></i></span><div><strong>Ticket submitted</strong><p>{{ $ticket->source ?? 'Workspace' }} · {{ $ticket->type ?? 'Other' }}</p><small><x-date-time :value="$ticket->submitted_at ?: $ticket->created_at" /></small></div></article>@forelse($ticket->messages as $message)<article class="tf-ticket-timeline-item"><span class="tf-ticket-timeline-icon"><i class="bi {{ $message->internal_note ? 'bi-lock' : 'bi-chat-dots' }}"></i></span><div><strong>{{ $message->sender?->name ?? 'System' }} @if($message->internal_note)<span class="tf-badge tf-badge-warning ms-1">Internal note</span>@endif</strong><p class="text-break">{{ $message->message }}</p><small><x-date-time :value="$message->created_at" /></small></div></article>@empty<div class="tf-ticket-empty">No replies or activity yet.</div>@endforelse</div></section>
                    @endunless
                    @if($isFinalized)
                    <section class="tf-ticket-section" data-ticket-conversation><h3>Conversation &amp; activity</h3><div class="tf-ticket-timeline"><article class="tf-ticket-timeline-item"><span class="tf-ticket-timeline-icon"><i class="bi bi-ticket-perforated"></i></span><div><strong>Ticket submitted</strong><p>{{ $ticket->source ?? 'Workspace' }} / {{ $ticket->type ?? 'Other' }}</p><small><x-date-time :value="$ticket->submitted_at ?: $ticket->created_at" /></small></div></article>@forelse($ticket->messages as $message)<article class="tf-ticket-timeline-item"><span class="tf-ticket-timeline-icon"><i class="bi {{ $message->internal_note ? 'bi-lock' : 'bi-chat-dots' }}"></i></span><div><strong>{{ $message->sender?->name ?? 'System' }} @if($message->internal_note)<span class="tf-badge tf-badge-warning ms-1">Internal note</span>@endif</strong><p class="text-break">{{ $message->message }}</p><small><x-date-time :value="$message->created_at" /></small></div></article>@empty<div class="tf-ticket-empty">No replies or activity yet.</div>@endforelse</div></section>
                    @endif
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>@unless($isFinalized)<div class="d-flex flex-wrap gap-2 ms-auto"><button type="submit" name="action" value="save" class="btn btn-outline-primary">Save changes</button><button type="submit" name="action" value="reply" class="btn btn-tf-primary" data-ticket-send-reply>Send reply</button></div>@endunless</div>
            </form>
        </div>
    </div>
@endforeach
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-ticket-manage-form]').forEach((form) => {
    const status = form.querySelector('[data-ticket-status]');
    const resolution = form.querySelector('[data-ticket-resolution]');
    const reply = form.querySelector('[data-ticket-reply]');
    const internal = form.querySelector('[data-ticket-internal-note]');
    const send = form.querySelector('[data-ticket-send-reply]');
    const error = form.querySelector('[data-ticket-reply-error]');
    const syncResolution = () => resolution.classList.toggle('d-none', !['Resolved', 'Closed'].includes(status.value) && !resolution.querySelector('textarea').value.trim());
    const syncReplyLabel = () => { send.textContent = internal.checked ? 'Add internal note' : 'Send reply'; };
    status.addEventListener('change', syncResolution);
    internal.addEventListener('change', syncReplyLabel);
    form.addEventListener('submit', (event) => {
        const submitter = event.submitter;
        if (submitter?.value !== 'reply') return;
        if (!reply.value.trim()) { event.preventDefault(); error.classList.remove('d-none'); reply.focus(); return; }
        error.classList.add('d-none');
        submitter.disabled = true;
        submitter.textContent = internal.checked ? 'Saving…' : 'Sending…';
    });
    syncResolution(); syncReplyLabel();
});
document.addEventListener('click', (event) => {
    const action = event.target.closest('[data-ticket-set-status]');
    if (action) {
        const status = document.querySelector(`${action.dataset.bsTarget} [data-ticket-status]`);
        if (status) {
            status.value = action.dataset.ticketSetStatus;
            window.syncTradeFlowTomSelect?.(status);
        }
    }
    const history = event.target.closest('[data-ticket-modal-focus="conversation"]');
    if (history) setTimeout(() => document.querySelector(`${history.dataset.bsTarget} [data-ticket-conversation]`)?.scrollIntoView({ block: 'start' }), 250);
});
@if(session('success'))window.Swal?.fire({ toast:true, position:'top-end', icon:'success', title:@json(session('success')), showConfirmButton:false, timer:2500 });@endif
</script>
@endpush
