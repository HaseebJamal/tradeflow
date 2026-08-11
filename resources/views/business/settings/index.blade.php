@extends('layouts.dashboard')
@section('page-title', 'Settings')
@section('page-subtitle', 'Business and account settings')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@if(auth()->user()->role === 'business_owner')
<div class="tf-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div><h2 class="h5 mb-1">Business Details</h2><p class="tf-muted mb-0">Keep the information shown across your workspace accurate.</p></div>
        <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#protectedBusinessDetailsModal"><i class="bi bi-shield-lock me-1"></i>Request Name or Email Change</button>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-md-6"><label class="form-label" for="currentBusinessName">Business Name</label><div class="tf-identity-input-wrap"><input id="currentBusinessName" class="form-control tf-identity-input" value="{{ $business?->business_name }}" readonly aria-describedby="businessNameHelp"><i class="bi bi-lock-fill" aria-hidden="true"></i></div><div id="businessNameHelp" class="form-text tf-identity-helper">Protected — changes require Super Admin approval.</div></div>
        <div class="col-md-6"><label class="form-label" for="currentBusinessLoginEmail">Business Login Email</label><div class="tf-identity-input-wrap"><input id="currentBusinessLoginEmail" class="form-control tf-identity-input" value="{{ auth()->user()->email }}" readonly aria-describedby="businessLoginEmailHelp"><i class="bi bi-lock-fill" aria-hidden="true"></i></div><div id="businessLoginEmailHelp" class="form-text tf-identity-helper">Protected — changes require Super Admin approval.</div></div>
    </div>
    <form method="POST" action="{{ route('business.settings.business') }}" class="row g-3" data-tf-confirm-message="These changes will update your business details." data-tf-confirm-title="Save business details?" data-tf-confirm-button="Save Details" data-tf-confirm-icon="question" data-tf-confirm-color="#2563eb" data-tf-confirm-saving-text="Saving...">@csrf @method('PUT')
        <div class="col-md-6"><label class="form-label">Phone</label><x-phone-input name="phone" :value="old('phone', $business?->phone)" :required="true" :error="$errors->first('phone')" /></div>
        <div class="col-md-6"><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city', $business?->city) }}" required></div>
        <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" value="{{ old('address', $business?->address) }}" required></div>
        <div class="col-12"><button class="btn btn-tf-primary">Save Business Details</button></div>
    </form>
</div>

<div class="modal fade" id="protectedBusinessDetailsModal" tabindex="-1" aria-labelledby="protectedBusinessDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('business.settings.protected-details-request') }}" data-tf-confirm-message="Send this protected business-detail change request to Super Admin?" data-tf-confirm-title="Submit request?" data-tf-confirm-button="Submit Request" data-tf-confirm-icon="question" data-tf-confirm-color="#2563eb" data-tf-confirm-saving-text="Submitting...">@csrf
        <div class="modal-header"><h2 class="modal-title fs-5" id="protectedBusinessDetailsModalLabel">Request Protected Detail Change</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <p class="tf-muted small">Business name and login email changes are applied only after Super Admin approval.</p>
            @if($pendingProtectedDetailRequest)<div class="alert alert-warning small">A pending request already exists. Submitting this form updates that pending request.</div>@endif
            <div class="mb-3"><label class="form-label" for="requestedBusinessName">Requested Business Name</label><input id="requestedBusinessName" name="requested_business_name" class="form-control @error('requested_business_name') is-invalid @enderror" value="{{ old('requested_business_name', $pendingProtectedDetailRequest?->requested_values['business_name'] ?? $business?->business_name) }}" required>@error('requested_business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="mb-3"><label class="form-label" for="requestedBusinessLoginEmail">Requested Business Login Email</label><input id="requestedBusinessLoginEmail" name="requested_owner_email" type="email" class="form-control @error('requested_owner_email') is-invalid @enderror" value="{{ old('requested_owner_email', $pendingProtectedDetailRequest?->requested_values['owner_email'] ?? auth()->user()->email) }}" required>@error('requested_owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div><label class="form-label" for="protectedChangeReason">Reason for Change</label><textarea id="protectedChangeReason" name="reason" rows="3" minlength="10" maxlength="2000" required class="form-control @error('reason') is-invalid @enderror" placeholder="Explain why this change is needed.">{{ old('reason', $pendingProtectedDetailRequest?->reason) }}</textarea>@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tf-primary">Submit Change Request</button></div>
    </form></div></div>
</div>

@if($errors->has('requested_business_name') || $errors->has('requested_owner_email') || $errors->has('reason') || $errors->has('protected_details'))
@push('scripts')
<script>document.addEventListener('DOMContentLoaded', () => { const modal = document.getElementById('protectedBusinessDetailsModal'); if (modal && window.bootstrap) new bootstrap.Modal(modal).show(); });</script>
@endpush
@endif

<div class="tf-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h2 class="h5 mb-1">Company Logo</h2><p class="tf-muted mb-0">Upload, replace, or remove your business logo.</p></div>
        @if($business?->logo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($business->logo) }}" class="profile-avatar" alt="{{ $business->business_name }} logo">@endif
    </div>
    <form method="POST" action="{{ route('business.settings.logo') }}" enctype="multipart/form-data" class="row g-3" data-tf-confirm-message="These changes will update your business settings." data-tf-confirm-title="Save changes?" data-tf-confirm-button="Save Changes" data-tf-confirm-icon="question" data-tf-confirm-color="#2563eb" data-tf-confirm-saving-text="Saving...">@csrf @method('PATCH')
        <div class="col-md-8"><label class="form-label" for="businessLogo">Logo</label><div class="tf-file-upload"><input id="businessLogo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="visually-hidden @error('logo') is-invalid @enderror" data-tf-image-upload data-tf-file-upload><label for="businessLogo" class="tf-file-upload__control"><i class="bi bi-image"></i><span><strong>{{ $business?->logo ? 'Replace company logo' : 'Upload company logo' }}</strong><small>JPG, PNG, or WebP up to 2 MB</small></span><em data-tf-file-upload-name>No file selected</em></label><div class="invalid-feedback" data-tf-image-error></div><small class="tf-muted d-block mt-1" data-tf-image-file-status></small></div>@error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
        <div class="col-md-4 d-flex align-items-end"><div class="d-flex flex-wrap gap-2"><label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="remove_logo" value="1"> Remove current logo</label><button class="btn btn-tf-primary">Save Logo</button></div></div>
    </form>
</div>

@if($canManageDocumentFooter)
<div class="tf-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h2 class="h5 mb-1">Branding & Documents</h2><p class="tf-muted mb-0">Manage the receipt footer used on future invoices and printable documents.</p></div>
        <a class="btn btn-outline-primary" href="{{ route('business.settings.document-footer.edit') }}"><i class="bi bi-receipt me-1"></i>Receipt Footer</a>
    </div>
</div>
@endif
@else
<div class="tf-card p-4"><h2 class="h5 mb-1">Business Details</h2><p class="tf-muted mb-0">Only the Business Owner can update business details, branding, and receipt footer settings.</p></div>
@endif
@endsection
