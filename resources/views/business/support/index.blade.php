@extends('layouts.dashboard')

@section('title', 'Support | TradeFlow')
@section('page-title', 'Support')
@section('page-subtitle', 'Send a request and track your business support tickets')

@section('content')
    <div class="row g-4">
        <div class="col-xl-4">
            <div class="tf-card p-4">
                <h2 class="h5 mb-3">New Support Request</h2>
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                <form method="POST" action="{{ route('business.support.store') }}" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <label for="support-subject" class="form-label">Subject</label>
                        <input id="support-subject" name="subject" value="{{ old('subject') }}" class="form-control @error('subject') is-invalid @enderror" maxlength="255" required>
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="support-priority" class="form-label">Priority</label>
                        <select id="support-priority" name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                            @foreach(['Low', 'Medium', 'High'] as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', 'Medium') === $priority)>{{ $priority }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="support-message" class="form-label">Message</label>
                        <textarea id="support-message" name="message" rows="6" class="form-control @error('message') is-invalid @enderror" maxlength="2000" required>{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12"><button class="btn btn-tf-primary w-100"><i class="bi bi-send me-2"></i>Submit Request</button></div>
                </form>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="tf-card p-4">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3"><h2 class="h5 mb-0">Your Support Tickets</h2><span class="tf-muted small">{{ $tickets->total() }} total</span></div>
                <x-table>
                    <thead><tr><th>Ticket</th><th>Subject</th><th>Priority</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                    @forelse($tickets as $ticket)
                        @php($statusClass = match(strtolower($ticket->status)) { 'open' => 'tf-badge-warning', 'closed', 'resolved' => 'tf-badge-success', default => 'tf-badge-secondary' })
                        <tr>
                            <td>{{ $ticket->ticket_number ?: 'TF-TKT-'.$ticket->id }}</td>
                            <td>{{ $ticket->subject }}</td>
                            <td>{{ $ticket->priority }}</td>
                            <td><span class="tf-badge {{ $statusClass }}">{{ $ticket->status }}</span></td>
                            <td><x-date-time :value="$ticket->created_at" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center tf-muted py-4">No support tickets yet.</td></tr>
                    @endforelse
                    </tbody>
                </x-table>
                <div class="mt-3">{{ $tickets->links() }}</div>
            </div>
        </div>
    </div>
@endsection
