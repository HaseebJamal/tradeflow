@extends('layouts.dashboard')

@section('page-title', 'Business Detail Change Requests')
@section('page-subtitle', 'Review and control protected business-detail changes')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="tf-card p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end" data-tf-tab-order>
        <div class="col-md-5"><label class="form-label">Business</label><select name="business_id" class="form-select"><option value="">All businesses</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected(request('business_id') == $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['Pending', 'Approved', 'Applied', 'Rejected'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-tf-primary w-100">Filter</button></div>
        <div class="col-md-2"><a href="{{ route('admin.business-detail-change-requests.index') }}" class="btn btn-outline-secondary w-100">Clear</a></div>
    </form>
</div>

@forelse($requests as $changeRequest)
    <div class="tf-card p-4 mb-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div><h2 class="h5 mb-1">{{ $changeRequest->business?->business_name }}</h2><p class="tf-muted mb-0">Requested by {{ $changeRequest->requester?->name ?? 'Unknown' }} · <x-date-time :value="$changeRequest->created_at" /></p></div>
            <span class="tf-badge {{ $changeRequest->status === 'Applied' ? 'tf-badge-success' : ($changeRequest->status === 'Rejected' ? 'tf-badge-danger' : 'tf-badge-warning') }}">{{ $changeRequest->status }}</span>
        </div>
        <p><strong>Reason:</strong> {{ $changeRequest->reason }}</p>
        <div class="row g-3 mb-3">
            @foreach($changeRequest->requested_values as $field => $requested)
                <div class="col-md-6"><div class="border rounded p-3 h-100">
                    <small class="text-uppercase tf-muted">{{ str_replace('_', ' ', $field) }}</small>
                    @if($field === 'owner_email')
                        <div class="small tf-muted">A protected login email update was requested. The value is not displayed to Super Admins.</div>
                    @else
                        <div class="small tf-muted">Current: {{ $field === 'logo' ? (data_get($changeRequest->old_values, $field) ? 'Logo uploaded' : 'No logo') : (data_get($changeRequest->old_values, $field) ?: '—') }}</div>
                        @if($field === 'logo')<div class="mt-1">@if($requested)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($requested) }}" class="navbar-avatar" alt="Requested logo">@else No logo change @endif</div>@else<strong>Requested: {{ $requested ?: '—' }}</strong>@endif
                    @endif
                </div></div>
            @endforeach
        </div>
        @if($changeRequest->status === 'Pending')
            <div class="row g-2">
                <form method="POST" action="{{ route('admin.business-detail-change-requests.approve', $changeRequest) }}" class="col-md-6" data-tf-confirm-message="Approve and apply these protected business details now? The business name and login email will update immediately and the owner will be notified.">@csrf @method('PATCH')<label class="form-label">Approval Note <span class="tf-muted small">Optional</span></label><div class="input-group"><input name="review_note" class="form-control" maxlength="2000" placeholder="Optional review note"><button class="btn btn-success">Approve & Apply</button></div></form>
                <form method="POST" action="{{ route('admin.business-detail-change-requests.reject', $changeRequest) }}" class="col-md-6" data-tf-confirm-message="Reject this protected business-detail change request? The business owner will be notified.">@csrf @method('PATCH')<label class="form-label">Rejection Reason</label><div class="input-group"><input name="review_note" class="form-control" maxlength="2000" required placeholder="Explain why the request is rejected"><button class="btn btn-outline-danger">Reject</button></div></form>
            </div>
        @else
            <p class="mb-0"><strong>Reviewed by:</strong> {{ $changeRequest->reviewer?->name ?? 'System' }} · <x-date-time :value="$changeRequest->reviewed_at" />@if($changeRequest->review_note) · {{ $changeRequest->review_note }}@endif</p>
        @endif
    </div>
@empty
    <div class="tf-card p-4 text-center tf-muted">No business-detail change requests found.</div>
@endforelse
<div class="mt-3">{{ $requests->links() }}</div>
@endsection
