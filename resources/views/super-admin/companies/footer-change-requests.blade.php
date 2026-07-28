@extends('layouts.dashboard')

@section('page-title', 'Footer Change Requests')
@section('page-subtitle', 'Review company footer detail requests')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="tf-card p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-5"><label class="form-label">Company</label><select name="business_id" class="form-select"><option value="">All companies</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected((int) request('business_id') === $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['Pending', 'Approved', 'Rejected', 'Changes Requested', 'Cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-tf-primary w-100">Filter</button></div>
        <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="{{ route('admin.footer-change-requests.index') }}">Clear</a></div>
    </form>
</div>

@forelse($requests as $changeRequest)
    <div class="tf-card p-4 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div><h2 class="h5 mb-1">{{ $changeRequest->business?->business_name }}</h2><p class="tf-muted mb-0">{{ $fields[$changeRequest->field] ?? $changeRequest->field }} requested by {{ $changeRequest->requester?->name ?? 'Unknown' }}</p></div>
            <span class="tf-badge {{ $changeRequest->status === 'Approved' ? 'tf-badge-success' : ($changeRequest->status === 'Rejected' ? 'tf-badge-danger' : 'tf-badge-warning') }}">{{ $changeRequest->status }}</span>
        </div>
        <div class="row g-3">
            <div class="col-md-4"><small class="tf-muted d-block">Current Value</small><strong>{{ $changeRequest->current_value === '1' ? 'Visible' : ($changeRequest->current_value === '0' ? 'Hidden' : ($changeRequest->current_value ?: 'Not provided')) }}</strong></div>
            <div class="col-md-4"><small class="tf-muted d-block">Requested Value</small><strong>{{ $changeRequest->requested_value === '1' ? 'Visible' : ($changeRequest->requested_value === '0' ? 'Hidden' : $changeRequest->requested_value) }}</strong></div>
            <div class="col-md-4"><small class="tf-muted d-block">Reason</small><span>{{ $changeRequest->reason }}</span></div>
        </div>
        @if($changeRequest->status === 'Pending')
            <form method="POST" action="{{ route('admin.footer-change-requests.review', $changeRequest) }}" class="row g-2 mt-3">@csrf @method('PATCH')
                <div class="col-md-5"><label class="form-label">Decision</label><select name="decision" class="form-select" required><option value="Approved">Approve</option><option value="Rejected">Reject</option><option value="Changes Requested">Request Changes</option></select></div>
                <div class="col-md-5"><label class="form-label">Review Reason <span class="tf-muted small">Required for reject or request changes</span></label><input name="review_note" class="form-control" maxlength="2000"></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-tf-primary w-100">Save Decision</button></div>
            </form>
        @else
            <p class="mt-3 mb-0 tf-muted">Reviewed by {{ $changeRequest->reviewer?->name ?? 'System' }} <x-date-time :value="$changeRequest->reviewed_at" />@if($changeRequest->review_note) · {{ $changeRequest->review_note }}@endif</p>
        @endif
    </div>
@empty
    <div class="tf-card p-4 text-center tf-muted">No footer change requests found.</div>
@endforelse
<div class="mt-3">{{ $requests->links() }}</div>
@endsection
