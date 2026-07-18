@extends('layouts.dashboard')
@section('page-title', 'Registration Review')
@section('page-subtitle', 'Review and decide this company registration without leaving the notification workflow')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
    <a href="{{ route('admin.notifications.registrations') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Registration queue</a>
    <a href="{{ route('admin.companies.show', $business) }}" class="btn btn-outline-primary">Open full company workspace <i class="bi bi-box-arrow-up-right ms-1"></i></a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="tf-card p-4 mb-4">
            <div class="d-flex flex-wrap gap-3 align-items-center mb-4">
                @if($business->logo)<img src="{{ asset('storage/'.$business->logo) }}" alt="{{ $business->business_name }} logo" class="rounded border" style="width:64px;height:64px;object-fit:cover">@else<span class="tf-icon-tile bg-blue text-white" style="width:64px;height:64px"><i class="bi bi-buildings fs-4"></i></span>@endif
                <div><h2 class="h4 mb-1">{{ $business->business_name }}</h2><p class="tf-muted mb-0">Submitted <x-date-time :value="$item->created_at" /></p></div>
                @php($statusClass = match(strtolower($business->status)) { 'approved' => 'success', 'rejected', 'suspended' => 'danger', default => 'warning' })
                <span class="tf-badge tf-badge-{{ $statusClass }} ms-lg-auto">{{ ucfirst($business->status) }}</span>
            </div>
            <div class="row g-3">
                <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block mb-1">Business type</small><strong>{{ $business->business_type ?: 'Not provided' }}</strong><small class="tf-muted d-block mt-2">Category: {{ $business->category ?: 'Not provided' }}</small></div></div>
                <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block mb-1">Business contact</small><strong>{{ $business->phone ?: 'Not provided' }}</strong><small class="tf-muted d-block mt-2">{{ $business->city ?: 'City not provided' }}</small></div></div>
                <div class="col-12"><div class="border rounded p-3"><small class="tf-muted d-block mb-1">Registered address</small>{{ $business->address ?: 'Not provided' }}</div></div>
            </div>
        </div>

        <div class="tf-card p-4 mb-4"><h2 class="h5 mb-3">Owner account</h2><div class="row g-3"><div class="col-md-6"><small class="tf-muted d-block">Name</small><strong>{{ $business->owner?->name ?? 'Not available' }}</strong></div><div class="col-md-6"><small class="tf-muted d-block">Private owner phone</small><strong>{{ $business->owner?->phone ?? 'Not available' }}</strong></div></div></div>

        <div class="tf-card p-4"><h2 class="h5 mb-3">Verification documents</h2><div class="row g-2">@forelse($business->documents as $document)<div class="col-md-6"><a href="{{ asset('storage/'.$document->file_path) }}" target="_blank" class="d-flex align-items-center gap-2 border rounded p-3 text-decoration-none"><i class="bi bi-file-earmark-text fs-4 text-primary"></i><span>{{ Str::headline($document->document_type) }}<small class="d-block tf-muted">Open document</small></span></a></div>@empty<div class="col-12"><p class="tf-muted mb-0">No verification documents were submitted.</p></div>@endforelse</div></div>
    </div>

    <div class="col-lg-4">
        <div class="tf-card p-4 mb-4"><h2 class="h5 mb-1">Decision control</h2><p class="tf-muted small">Your decision is recorded in the approval history and audit log.</p><form method="POST" action="{{ route('admin.companies.status', $business) }}" class="row g-3">@csrf @method('PATCH')<div class="col-12"><label for="review-status" class="form-label">Registration status</label><select id="review-status" name="status" class="form-select"><option value="pending" @selected(strtolower($business->status) === 'pending')>Keep pending</option><option value="approved" @selected(strtolower($business->status) === 'approved')>Approve and activate</option><option value="rejected" @selected(strtolower($business->status) === 'rejected')>Reject registration</option><option value="suspended" @selected(strtolower($business->status) === 'suspended')>Suspend account</option></select></div><div class="col-12"><label for="review-note" class="form-label">Decision note</label><textarea id="review-note" name="admin_note" class="form-control" rows="5" placeholder="Add the reason, requested changes, or approval note"></textarea></div><div class="col-12"><button class="btn btn-tf-primary w-100"><i class="bi bi-check2-circle me-1"></i>Save decision</button></div></form></div>
        <div class="tf-card p-4"><h2 class="h5 mb-3">Review history</h2><div class="d-grid gap-3">@forelse($business->approvalLogs->take(5) as $log)<div class="border-start border-3 border-primary ps-3"><strong class="d-block">{{ $log->new_status }}</strong><small class="tf-muted d-block"><x-date-time :value="$log->changed_at" /></small>@if($log->note)<small class="d-block mt-1">{{ $log->note }}</small>@endif</div>@empty<p class="tf-muted mb-0">No prior decisions have been recorded.</p>@endforelse</div></div>
    </div>
</div>
@endsection
