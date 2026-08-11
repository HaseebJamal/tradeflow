@extends('layouts.dashboard')
@section('page-title', 'Business Details')
@section('page-subtitle', $business->business_name)
@section('content')
@php
    $currentStatus = strtolower($business->status);
    $statusLabels = [
        'approved' => ['Approved', 'bi-check-circle'],
        'rejected' => ['Rejected', 'bi-x-circle'],
        'suspended' => ['Suspended', 'bi-pause-circle'],
        'pending' => ['Pending', 'bi-hourglass-split'],
    ];
@endphp
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-4">
    <div class="col-lg-8">
        <div class="tf-card p-4">
            <h2 class="h5">Business Information</h2>
            <div class="row g-3">
                @foreach(['Business Name'=>$business->business_name,'Owner Name'=>$business->owner?->name,'Owner Login Email'=>$business->owner?->email ?: 'Not provided','Phone'=>$business->phone,'Address'=>$business->address,'City'=>$business->city,'Business Type'=>$business->display_business_type,'Registration Date'=>$business->created_at->format('n/j/Y')] as $k=>$v)
                <div class="col-md-6"><div class="border rounded p-3"><small class="tf-muted">{{ $k }}</small><strong class="d-block">{{ $v }}</strong></div></div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="tf-card p-4">
            <h2 class="h5">Business Status</h2>
            <p class="mb-3"><span class="tf-muted">Current Status:</span> <strong id="currentBusinessStatus">{{ ucfirst($currentStatus) }}</strong></p>
            <div class="alert alert-info d-none" id="statusSelectionMessage">Status change selected. Click Save Changes to apply.</div>
            <form method="POST" action="{{ route('admin.businesses.status', $business) }}" id="businessStatusForm">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" id="selectedBusinessStatus" value="{{ $currentStatus }}">
                <div class="d-grid gap-2 mb-3" role="group" aria-label="Business status options">
                    @foreach($statusLabels as $value => [$label, $icon])
                    <button type="button" class="btn {{ $currentStatus === $value ? 'btn-tf-primary active' : 'btn-outline-primary' }} text-start" data-business-status-option="{{ $value }}">
                        <i class="bi {{ $icon }} me-2"></i>{{ $label }}
                    </button>
                    @endforeach
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-tf-primary" id="saveBusinessStatus" disabled>Save Changes</button>
                    <button type="button" class="btn btn-outline-secondary" id="cancelBusinessStatus">Cancel</button>
                </div>
            </form>
        </div>
        <div class="tf-card p-4 mt-4">
            <h2 class="h5">Uploaded Documents</h2>
            @forelse($business->documents as $doc)
                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($doc->file_path) }}" target="_blank" class="d-block border rounded p-2 mb-2">{{ $doc->document_type }}</a>
            @empty
                <p class="tf-muted">No documents uploaded.</p>
            @endforelse
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const currentStatus = @json($currentStatus);
    const input = document.getElementById('selectedBusinessStatus');
    const message = document.getElementById('statusSelectionMessage');
    const save = document.getElementById('saveBusinessStatus');
    const cancel = document.getElementById('cancelBusinessStatus');
    const buttons = [...document.querySelectorAll('[data-business-status-option]')];

    function paint(selected) {
        buttons.forEach((button) => {
            const active = button.dataset.businessStatusOption === selected;
            button.classList.toggle('btn-tf-primary', active);
            button.classList.toggle('active', active);
            button.classList.toggle('btn-outline-primary', !active);
        });
        input.value = selected;
        const changed = selected !== currentStatus;
        message.classList.toggle('d-none', !changed);
        save.disabled = !changed;
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => paint(button.dataset.businessStatusOption));
    });

    cancel.addEventListener('click', () => paint(currentStatus));
});
</script>
@endsection
