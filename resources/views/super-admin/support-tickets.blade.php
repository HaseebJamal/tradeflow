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

<x-table>
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
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="collapse"
                        data-bs-target="#ticket-{{ $ticket->id }}"
                    >
                        Manage
                    </button>
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
    {{ $tickets->links() }}
</div>

@endsection
