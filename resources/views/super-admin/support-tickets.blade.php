@extends('layouts.dashboard')

@section('page-title', 'Complaints & Support')
@section('page-subtitle', 'Assign, reply, escalate, resolve, and close tickets')

@section('content')

@if (session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

<div class="tf-card p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-3"><label class="form-label">Search</label><input name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Ticket, business, or subject"></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label">Type</label><select name="type" class="form-select"><option value="">All types</option>@foreach($ticketTypes as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? null) === $type)>{{ $type }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label">Priority</label><select name="priority" class="form-select"><option value="">All priorities</option>@foreach(['Low', 'Medium', 'High', 'Urgent'] as $priority)<option value="{{ $priority }}" @selected(($filters['priority'] ?? null) === $priority)>{{ $priority }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['Open', 'Assigned', 'In Progress', 'Waiting for User', 'Escalated', 'Resolved', 'Closed', 'Reopened', 'Pending'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>@endforeach</select></div>
        <div class="col-sm-6 col-lg-1"><label class="form-label">From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? (request()->boolean('clear') ? '' : today()->toDateString()) }}" class="form-control"></div>
        <div class="col-sm-6 col-lg-1"><label class="form-label">To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? (request()->boolean('clear') ? '' : today()->toDateString()) }}" class="form-control"></div>
        <div class="col-sm-6 col-lg-1 d-flex gap-1"><button class="btn btn-outline-primary flex-fill">Filter</button><a href="{{ route('admin.support-tickets', ['clear' => 1]) }}" class="btn btn-outline-secondary" title="Clear filters"><i class="bi bi-arrow-counterclockwise"></i></a></div>
    </form>
</div>

<x-table class="tf-admin-data-table">
    <thead>
        <tr>
            <th>Ticket</th>
            <th>Business</th>
            <th>Type</th>
            <th>Subject</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Handled By</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>
        @forelse ($tickets as $ticket)
            <tr>
                <td>
                    {{ $ticket->ticket_number ?: 'TF-TKT-' . $ticket->id }}
                </td>

                <td>
                    {{ $ticket->business?->business_name ?? '-' }}
                </td>

                <td>
                    {{ $ticket->type ?? 'Other' }}
                </td>

                <td>
                    <strong>{{ $ticket->subject }}</strong>

                    <div class="small tf-muted">
                        {{ $ticket->message ?: $ticket->description }}
                    </div>
                </td>

                <td>{{ $ticket->priority }}</td>
                <td>{{ $ticket->status }}</td>

                <td>
                    {{ $ticket->assignedAdmin?->name ?? '-' }}
                </td>

                <td>
                    <x-date-time :value="$ticket->created_at" />
                </td>

                <td>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><button type="button" class="dropdown-item" data-ticket-action="view" data-ticket-id="{{ $ticket->id }}">View</button></li>
                            @if($ticket->status !== 'Closed')
                                <li><button type="button" class="dropdown-item" data-ticket-action="Assigned" data-ticket-id="{{ $ticket->id }}">Assign</button></li>
                                <li><button type="button" class="dropdown-item" data-ticket-action="reply" data-ticket-id="{{ $ticket->id }}">Reply</button></li>
                                <li><button type="button" class="dropdown-item" data-ticket-action="Escalated" data-ticket-id="{{ $ticket->id }}">Escalate</button></li>
                            @endif
                            @if(!in_array($ticket->status, ['Resolved', 'Closed'], true))<li><button type="button" class="dropdown-item" data-ticket-action="Resolved" data-ticket-id="{{ $ticket->id }}">Resolve</button></li>@endif
                            @if($ticket->status !== 'Closed')<li><button type="button" class="dropdown-item" data-ticket-action="Closed" data-ticket-id="{{ $ticket->id }}">Close</button></li>@endif
                        </ul>
                    </div>
                </td>
            </tr>

            <tr
                class="collapse"
                id="ticket-{{ $ticket->id }}"
            >
                <td colspan="9">
                    <form
                        method="POST"
                        action="{{ route('admin.support-tickets.update', $ticket) }}"
                        class="row g-3"
                    >
                        @csrf
                        @method('PATCH')

                        <div class="col-md-3">
                            <label class="form-label">Status</label>

                            <select name="status" class="form-select">
                                @foreach ([
                                    'Open',
                                    'Assigned',
                                    'In Progress',
                                    'Waiting for User',
                                    'Escalated',
                                    'Resolved',
                                    'Closed',
                                    'Reopened',
                                    'Pending'
                                ] as $status)
                                    <option
                                        value="{{ $status }}"
                                        @selected($ticket->status === $status)
                                    >
                                        {{ $status }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Priority</label>

                            <select name="priority" class="form-select">
                                @foreach ([
                                    'Low',
                                    'Medium',
                                    'High',
                                    'Urgent'
                                ] as $priority)
                                    <option
                                        value="{{ $priority }}"
                                        @selected($ticket->priority === $priority)
                                    >
                                        {{ $priority }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">
                                Reply / Note
                            </label>

                            <textarea
                                name="message"
                                class="form-control"
                                rows="3"
                            ></textarea>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">
                                Resolution
                            </label>

                            <textarea
                                name="resolution"
                                class="form-control"
                                rows="2"
                            >{{ $ticket->resolution }}</textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">
                                Admin Reply Snapshot
                            </label>

                            <textarea
                                name="admin_reply"
                                class="form-control"
                                rows="2"
                            >{{ $ticket->admin_reply }}</textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-check">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    name="internal_note"
                                    value="1"
                                >

                                Internal note
                            </label>
                        </div>

                        <div class="col-12">
                            <button class="btn btn-tf-primary">
                                Save Ticket
                            </button>
                        </div>
                    </form>

                    <div class="mt-3">
                        <strong>History</strong>

                        <div class="d-grid gap-2 mt-2">
                            @forelse ($ticket->messages as $message)
                                <div class="border rounded p-2">
                                    <div class="small tf-muted">
                                        <x-date-time
                                            :value="$message->created_at"
                                        />

                                        · {{ $message->sender?->name ?? 'System' }}

                                        @if ($message->internal_note)
                                            · Internal
                                        @endif
                                    </div>

                                    {{ $message->message }}
                                </div>
                            @empty
                                <div class="tf-muted">
                                    No replies yet.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td
                    colspan="9"
                    class="text-center tf-muted py-4"
                >
                    No tickets.
                </td>
            </tr>
        @endforelse
    </tbody>
</x-table>

<div class="mt-3">
    <x-table-result-summary :paginator="$tickets" />
    {{ $tickets->links() }}
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('click', (event) => {
    const action = event.target.closest('[data-ticket-action]');
    if (!action) return;

    const detail = document.getElementById(`ticket-${action.dataset.ticketId}`);
    if (!detail) return;
    bootstrap.Collapse.getOrCreateInstance(detail).show();

    const status = detail.querySelector('[name="status"]');
    if (status && !['view', 'reply'].includes(action.dataset.ticketAction)) status.value = action.dataset.ticketAction;
    detail.scrollIntoView({ block: 'nearest' });
});
</script>
@endpush
