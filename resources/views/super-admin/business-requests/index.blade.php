@extends('layouts.dashboard')

@section('page-title', 'Business Requests')
@section('page-subtitle', 'Review subscription, footer, and business change requests')

@section('content')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="tf-card p-3 mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-xl-3 col-md-4"><label class="form-label">Search</label><input name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Request ID, business, or owner"></div>
            <div class="col-xl-2 col-md-4"><label class="form-label">Request Type</label><select name="type" class="form-select"><option value="">Select Type</option>@foreach($types as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['Pending', 'Approved', 'Rejected', 'Cancelled', 'Changes Requested', 'Scheduled', 'Completed', 'Active', 'Applied'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-3"><label class="form-label">Date From</label><input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="col-xl-2 col-md-3"><label class="form-label">Date To</label><input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="col-xl-auto col-md-3 d-flex gap-1"><button class="btn btn-outline-primary">Filter</button><a href="{{ route('admin.business-requests.index', ['clear' => 1]) }}" class="btn btn-outline-secondary">Clear</a></div>
        </form>
    </div>

    <x-table class="tf-business-request-table">
        <thead><tr><th>Request ID</th><th>Business</th><th>Owner</th><th>Request Type</th><th>Change Summary</th><th>Status</th><th>Requested</th><th>Reviewed By</th><th>Reviewed At</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
            @forelse($requests as $requestItem)
                <tr>
                    <td class="text-nowrap">#{{ $requestItem->source }}-{{ $requestItem->id }}</td>
                    <td>{{ $requestItem->business_name }}</td>
                    <td>{{ $requestItem->owner_name ?? 'Owner not assigned' }}</td>
                    <td>{{ $requestItem->request_type }}</td>
                    <td class="tf-request-summary" title="{{ $requestItem->change_summary }}">{{ $requestItem->change_summary }}</td>
                    <td><span class="tf-badge {{ in_array($requestItem->status, ['Approved', 'Active', 'Applied', 'Completed'], true) ? 'tf-badge-success' : (in_array($requestItem->status, ['Rejected', 'Cancelled'], true) ? 'tf-badge-danger' : 'tf-badge-warning') }}">{{ $requestItem->status }}</span></td>
                    <td>{{ \Illuminate\Support\Carbon::parse($requestItem->requested_at)->format('d M, Y h:i A') }}</td>
                    <td>{{ $requestItem->reviewer_name ?? 'Not reviewed' }}</td>
                    <td>{{ $requestItem->reviewed_at ? \Illuminate\Support\Carbon::parse($requestItem->reviewed_at)->format('d M, Y h:i A') : '—' }}</td>
                    <td class="text-end">
                        @php
                            $canReview = in_array($requestItem->source, ['subscription', 'footer', 'business_detail'], true)
                                && in_array($requestItem->status, ['Pending', 'Changes Requested'], true);
                            $requestUrl = route('admin.business-requests.show', ['source' => $requestItem->source, 'requestId' => $requestItem->id]);
                        @endphp
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" data-bs-boundary="viewport">Actions</button>
                            <div class="dropdown-menu dropdown-menu-end shadow">
                                <button class="dropdown-item" data-tf-business-request-details data-url="{{ $requestUrl }}"><i class="bi bi-eye me-2"></i>View Details</button>
                                <button class="dropdown-item" data-tf-business-request-details data-url="{{ $requestUrl }}" data-history-only="1"><i class="bi bi-clock-history me-2"></i>View History</button>
                                @if($canReview)
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-success" data-tf-business-request-details data-url="{{ $requestUrl }}" data-decision="Approved">Approve</button>
                                    <button class="dropdown-item text-warning" data-tf-business-request-details data-url="{{ $requestUrl }}" data-decision="Changes Requested">Request Changes</button>
                                    <button class="dropdown-item text-danger" data-tf-business-request-details data-url="{{ $requestUrl }}" data-decision="Rejected">Reject</button>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center tf-muted py-4">No business requests found.</td></tr>
            @endforelse
        </tbody>
    </x-table>
    <div class="mt-3">{{ $requests->links() }}</div>

    <div class="modal fade" id="businessRequestDetailsModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h2 class="modal-title fs-5">Business Request Details</h2><p class="tf-muted mb-0" data-tf-request-subtitle></p></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
        <section class="mb-4"><h3 class="h6">Request Overview</h3><div class="table-responsive"><table class="table table-sm mb-0"><tbody data-tf-request-overview></tbody></table></div></section>
        <section class="mb-4"><h3 class="h6">Current Information</h3><div class="table-responsive"><table class="table table-sm mb-0"><tbody data-tf-request-current></tbody></table></div></section>
        <section class="mb-4"><h3 class="h6">Requested Changes</h3><div class="table-responsive"><table class="table table-sm mb-0"><tbody data-tf-request-requested></tbody></table></div></section>
        <section class="mb-4 d-none" data-tf-request-comparison-section><h3 class="h6">Comparison</h3><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Field</th><th>Current</th><th>Requested</th></tr></thead><tbody data-tf-request-comparison></tbody></table></div></section>
        <section class="mb-4 d-none" data-tf-request-payment-section><h3 class="h6">Payment Information</h3><div class="table-responsive"><table class="table table-sm mb-0"><tbody data-tf-request-payment></tbody></table></div></section>
        <section><h3 class="h6">Status History</h3><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Status</th><th>Performed By</th><th>Date &amp; Time</th><th>Reason / Admin Note</th></tr></thead><tbody data-tf-request-history></tbody></table></div></section>
        <form class="border-top pt-3 mt-3 d-none" data-tf-request-review method="POST"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="PATCH"><div class="row g-3"><div class="col-md-4"><label class="form-label">Decision</label><select name="decision" class="form-select" data-native-select data-tf-request-decision></select></div><div class="col-md-8"><label class="form-label" data-tf-request-note-label>Admin message</label><textarea class="form-control" rows="2" maxlength="2000" data-tf-request-note></textarea></div></div><button class="btn btn-tf-primary mt-3">Save Decision</button></form>
    </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
@endsection

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('businessRequestDetailsModal');
    const instance = modal ? bootstrap.Modal.getOrCreateInstance(modal) : null;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    document.addEventListener('click', async event => {
        const trigger = event.target.closest('[data-tf-business-request-legacy]');
        if (!trigger || !modal) return;
        try {
            const response = await fetch(trigger.dataset.url, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('Request unavailable');
            const request = await response.json();
            modal.querySelector('[data-tf-request-subtitle]').textContent = request.type + ' · ' + request.status;
            modal.querySelector('[data-tf-request-history]').innerHTML = request.history.map(item => `<div class="mb-2"><strong>${escapeHtml(item.status)}</strong><span class="tf-muted"> · ${escapeHtml(item.at)} · ${escapeHtml(item.performed_by)}</span>${item.message ? `<div>${escapeHtml(item.message)}</div>` : ''}</div>`).join('');
            const form = modal.querySelector('[data-tf-request-review]');
            const decision = modal.querySelector('[data-tf-request-decision]');
            const actions = request.actions || {};
            if ((actions.url || actions.decision_urls) && actions.decisions?.length && !trigger.dataset.historyOnly) {
                form.classList.remove('d-none'); form.action = actions.url || actions.decision_urls[actions.decisions[0]];
                decision.innerHTML = actions.decisions.map(value => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
                decision.onchange = () => { form.action = actions.decision_urls?.[decision.value] || actions.url; };
                if (trigger.dataset.decision && actions.decisions.includes(trigger.dataset.decision)) { decision.value = trigger.dataset.decision; decision.onchange(); }
            } else { form.classList.add('d-none'); form.action = ''; decision.innerHTML = ''; }
            instance.show();
        } catch (error) { window.Swal ? Swal.fire('Unavailable', 'The related request is no longer available.', 'warning') : alert('The related request is no longer available.'); }
    });
})();
</script>
<script>
(() => {
    const modal = document.getElementById('businessRequestDetailsModal');
    if (!modal) return;
    const instance = bootstrap.Modal.getOrCreateInstance(modal);
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const rows = values => Object.entries(values || {}).map(([label, value]) => `<tr><th class="fw-medium text-nowrap">${escapeHtml(label)}</th><td>${escapeHtml(value)}</td></tr>`).join('') || '<tr><td class="tf-muted">No details available.</td></tr>';

    document.addEventListener('click', async event => {
        const trigger = event.target.closest('[data-tf-business-request-details]');
        if (!trigger) return;

        try {
            const response = await fetch(trigger.dataset.url, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('Request unavailable');
            const request = await response.json();
            const comparison = request.changes || {};
            const payment = request.payment_details || {};
            const form = modal.querySelector('[data-tf-request-review]');
            const decision = modal.querySelector('[data-tf-request-decision]');
            const note = modal.querySelector('[data-tf-request-note]');
            const noteLabel = modal.querySelector('[data-tf-request-note-label]');
            const actions = request.actions || {};

            modal.querySelector('[data-tf-request-subtitle]').textContent = `${request.type} · ${request.status}`;
            modal.querySelector('[data-tf-request-overview]').innerHTML = rows({
                'Request ID': `#${request.source}-${request.id}`,
                'Request type': request.type,
                'Current status': request.status,
                'Business name': request.business,
                'Owner name': request.owner,
                'Requested by': request.requested_by,
                'Requested at': request.requested_at,
                'Request reason': request.reason || 'Not provided',
            });
            modal.querySelector('[data-tf-request-current]').innerHTML = rows(request.current_details);
            modal.querySelector('[data-tf-request-requested]').innerHTML = rows(request.requested_details);
            modal.querySelector('[data-tf-request-comparison]').innerHTML = Object.entries(comparison).map(([label, values]) => `<tr><td>${escapeHtml(label)}</td><td>${escapeHtml(values.current)}</td><td>${escapeHtml(values.requested)}</td></tr>`).join('');
            modal.querySelector('[data-tf-request-comparison-section]').classList.toggle('d-none', Object.keys(comparison).length === 0);
            modal.querySelector('[data-tf-request-payment]').innerHTML = rows(payment);
            modal.querySelector('[data-tf-request-payment-section]').classList.toggle('d-none', Object.keys(payment).length === 0);
            modal.querySelector('[data-tf-request-history]').innerHTML = (request.history || []).map(item => `<tr><td>${escapeHtml(item.status)}</td><td>${escapeHtml(item.performed_by)}</td><td>${escapeHtml(item.at)}</td><td>${escapeHtml(item.message || '—')}</td></tr>`).join('') || '<tr><td colspan="4" class="tf-muted">No history available.</td></tr>';

            if ((actions.url || actions.decision_urls) && actions.decisions?.length && !trigger.dataset.historyOnly) {
                form.classList.remove('d-none');
                form.action = actions.url || actions.decision_urls[actions.decisions[0]];
                decision.innerHTML = actions.decisions.map(value => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join('');
                note.name = actions.note_field || 'admin_note';
                note.value = '';
                decision.onchange = () => {
                    form.action = actions.decision_urls?.[decision.value] || actions.url;
                    const required = ['Rejected', 'Changes Requested'].includes(decision.value);
                    note.required = required;
                    noteLabel.textContent = required ? 'Admin message (required)' : 'Admin message';

                    const confirmations = {
                        Approved: {
                            title: 'Approve this request?',
                            button: 'Approve Request',
                            message: 'This will approve and apply the requested change where applicable.',
                            icon: 'question',
                            color: '#198754',
                        },
                        Rejected: {
                            title: 'Reject this request?',
                            button: 'Reject Request',
                            message: 'This request will be rejected. The business owner will be notified.',
                            icon: 'warning',
                            color: '#dc3545',
                        },
                        'Changes Requested': {
                            title: 'Request changes?',
                            button: 'Request Changes',
                            message: 'The request will be sent back to the business owner with your message.',
                            icon: 'warning',
                            color: '#f59e0b',
                        },
                    };
                    const confirmation = confirmations[decision.value];
                    if (confirmation) {
                        form.dataset.tfConfirmTitle = confirmation.title;
                        form.dataset.tfConfirmButton = confirmation.button;
                        form.dataset.tfConfirmMessage = confirmation.message;
                        form.dataset.tfConfirmIcon = confirmation.icon;
                        form.dataset.tfConfirmColor = confirmation.color;
                    } else {
                        delete form.dataset.tfConfirmTitle;
                        delete form.dataset.tfConfirmButton;
                        delete form.dataset.tfConfirmMessage;
                        delete form.dataset.tfConfirmIcon;
                        delete form.dataset.tfConfirmColor;
                    }
                };
                if (trigger.dataset.decision && actions.decisions.includes(trigger.dataset.decision)) decision.value = trigger.dataset.decision;
                decision.onchange();
            } else {
                form.classList.add('d-none');
                form.action = '';
                decision.innerHTML = '';
                note.value = '';
            }

            instance.show();
        } catch (error) {
            window.Swal ? Swal.fire('Unavailable', 'The related request is no longer available.', 'warning') : alert('The related request is no longer available.');
        }
    });
})();
</script>
@endpush
