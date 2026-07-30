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
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3"><small class="tf-muted">Showing {{ $requests->firstItem() ?? 0 }} to {{ $requests->lastItem() ?? 0 }} of {{ $requests->total() }} results</small>{{ $requests->links() }}</div>

    <div class="modal fade" id="businessRequestDetailsModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-md"><div class="modal-content"><div class="modal-header"><div><h2 class="modal-title fs-5">Business Request Details</h2><p class="tf-muted mb-0" data-tf-request-subtitle></p></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><section data-tf-request-history-wrap><h3 class="h6">Status History</h3><div data-tf-request-history></div></section><form class="border-top pt-3 mt-3 d-none" data-tf-request-review method="POST"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="PATCH"><div class="mb-3"><label class="form-label">Decision</label><select name="decision" class="form-select" data-native-select data-tf-request-decision></select></div><button class="btn btn-tf-primary">Save Decision</button></form></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
@endsection

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('businessRequestDetailsModal');
    const instance = modal ? bootstrap.Modal.getOrCreateInstance(modal) : null;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    document.addEventListener('click', async event => {
        const trigger = event.target.closest('[data-tf-business-request-details]');
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
@endpush
