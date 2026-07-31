@extends('layouts.dashboard')
@section('page-title', 'Profile')
@section('page-subtitle', 'Update profile information and password')
@section('content')
@php($hasProfileImage = $user->profile_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_image))
@php($requiresOwnerApproval = $user->business_id && $user->role !== 'business_owner' && ! $user->isSuperAdmin())
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-4">
    <div class="col-lg-7">
        <div class="tf-card p-4">
            <h2 class="h5">{{ $requiresOwnerApproval ? 'Request Profile Detail Change' : 'Profile Information' }}</h2>
            @if($requiresOwnerApproval)
                <p class="tf-muted">Your Business Owner must approve and apply profile-detail changes. Your current details stay unchanged until then.</p>
                @if($pendingProfileRequest)<div class="alert alert-warning py-2">A profile-change request is already pending owner review. Submitting this form updates that request.</div>@endif
            @endif
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="row g-3">@csrf @method('PUT')
                <div class="col-12 text-center">
                    <div class="dropdown d-inline-block tf-profile-image-dropdown">
                        <button type="button" class="tf-profile-image-button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Manage profile image">
                            @if($hasProfileImage)
                                <img src="{{ asset('storage/'.$user->profile_image) }}?v={{ $user->updated_at?->timestamp }}" class="profile-avatar" alt="{{ $user->name }}" data-tf-profile-preview>
                            @else
                                <span class="profile-avatar tf-avatar-empty" data-tf-profile-empty><i class="bi bi-person"></i></span>
                                <img src="" class="profile-avatar d-none" alt="{{ $user->name }}" data-tf-profile-preview>
                            @endif
                        </button>
                        <ul class="dropdown-menu tf-profile-image-menu">
                            <li><label for="profileImageUpload" class="dropdown-item mb-0"><i class="bi bi-image me-2"></i>{{ $hasProfileImage ? 'Replace' : 'Upload' }} Profile Image</label></li>
                            @if($hasProfileImage)
                                <li><button type="button" class="dropdown-item text-danger" data-tf-profile-remove-action><i class="bi bi-trash me-2"></i>Remove Profile Image</button></li>
                            @endif
                        </ul>
                    </div>
                    <div class="fw-bold">{{ $user->name }}</div>
                    <small class="tf-muted">{{ $user->role }}</small>
                </div>
                <div class="col-md-6"><label class="form-label">{{ $requiresOwnerApproval ? 'Requested Name' : 'Name' }}</label><input name="name" class="form-control" value="{{ old('name', $pendingProfileRequest?->requested_values['name'] ?? $user->name) }}" required></div>
                @if($requiresOwnerApproval)
                    <div class="col-md-6"><label class="form-label">Current Email</label><input type="email" class="form-control" value="{{ $user->email }}" readonly><small class="tf-muted">Login email changes require approval.</small></div>
                @else
                    <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}" required></div>
                @endif
                <div class="col-md-6"><label class="form-label">{{ $requiresOwnerApproval ? 'Requested Phone' : 'Phone' }}</label><x-phone-input name="phone" :value="old('phone', $pendingProfileRequest?->requested_values['phone'] ?? $user->phone)" :error="$errors->first('phone')" /></div>
                <input id="profileImageUpload" name="profile_image" type="file" class="visually-hidden" accept="image/jpeg,image/png,image/webp" data-tf-profile-input data-tf-image-upload tabindex="-1">
                @if($hasProfileImage)
                    <input name="remove_image" value="1" type="checkbox" class="d-none" data-tf-profile-remove>
                @endif
                @if($requiresOwnerApproval)
                    <div class="col-12"><label class="form-label">Reason for Change</label><textarea name="reason" class="form-control" rows="3" minlength="10" maxlength="2000" placeholder="Required only when changing your name or phone.">{{ old('reason', $pendingProfileRequest?->reason) }}</textarea></div>
                @endif
                <div class="col-12"><button class="btn btn-tf-primary" @if($requiresOwnerApproval) data-tf-confirm-message="Submit this profile-change request to your Business Owner?" @endif>{{ $requiresOwnerApproval ? 'Submit Change Request' : 'Save Profile Changes' }}</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="tf-card p-4"><h2 class="h5">Change Password</h2><form method="POST" action="{{ route('profile.password') }}" class="d-grid gap-3">@csrf @method('PUT')
                <div class="input-group"><input id="profileCurrentPassword" name="current_password" type="password" class="form-control" placeholder="Current password" autocomplete="current-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#profileCurrentPassword" data-tf-password-icon="#profileCurrentPasswordIcon"><i id="profileCurrentPasswordIcon" class="bi bi-eye"></i></button></div>
                <div class="input-group"><input id="profileNewPassword" name="password" type="password" class="form-control" placeholder="New password" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#profileNewPassword" data-tf-password-icon="#profileNewPasswordIcon"><i id="profileNewPasswordIcon" class="bi bi-eye"></i></button></div>
                <div class="input-group"><input id="profileConfirmPassword" name="password_confirmation" type="password" class="form-control" placeholder="Confirm password" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#profileConfirmPassword" data-tf-password-icon="#profileConfirmPasswordIcon"><i id="profileConfirmPasswordIcon" class="bi bi-eye"></i></button></div>
                <button class="btn btn-outline-primary">Change Password</button></form></div>
        @if($requiresOwnerApproval)
            <div class="tf-card p-4 mt-4">
                <h2 class="h5">Request Email Change</h2>
                <p class="tf-muted">Your login email is protected and requires Business Owner or authorized administrator approval.</p>
                @if($pendingEmailChangeRequest)
                    <div class="alert alert-warning mb-0">An email-change request is awaiting review.</div>
                @else
                    <form method="POST" action="{{ route('profile.email-change-requests.store') }}" class="d-grid gap-3">@csrf
                        <div><label class="form-label">Current Email</label><input name="current_email" type="email" class="form-control" value="{{ $user->email }}" readonly></div>
                        <div><label class="form-label">Requested New Email</label><input name="requested_email" type="email" class="form-control" value="{{ old('requested_email') }}" required></div>
                        <div><label class="form-label">Reason for Change</label><textarea name="reason" class="form-control" rows="3" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea></div>
                        <button class="btn btn-outline-primary">Request Email Change</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</div>
@if($user->role === 'business_owner')
    <div class="tf-card p-4 mt-4">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-1">User Profile Change Requests</h2><p class="tf-muted mb-0">Review requests from users in your business. Changes are only made after you approve and apply them.</p></div><span class="tf-badge {{ $profileChangeRequests->where('status', 'Pending')->isNotEmpty() ? 'tf-badge-warning' : 'tf-badge-success' }}">{{ $profileChangeRequests->where('status', 'Pending')->count() }} pending</span></div>
        @forelse($profileChangeRequests as $changeRequest)
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-2"><strong>{{ $changeRequest->user?->name }}</strong><span class="tf-badge {{ $changeRequest->status === 'Pending' ? 'tf-badge-warning' : 'tf-badge-success' }}">{{ $changeRequest->status }}</span></div>
                <p class="mb-2"><strong>Reason:</strong> {{ $changeRequest->reason }}</p>
                <div class="row g-2 small mb-3">
                    @foreach(['Name' => 'name', 'Email' => 'email', 'Phone' => 'phone'] as $label => $field)
                        <div class="col-md-4"><div class="bg-light rounded p-2"><span class="tf-muted d-block">{{ $label }}</span><span>Current: {{ $changeRequest->old_values[$field] ?: '—' }}</span><strong class="d-block">Requested: {{ $changeRequest->requested_values[$field] ?: '—' }}</strong></div></div>
                    @endforeach
                </div>
                @if($changeRequest->status === 'Pending')
                    <div class="row g-2">
                        <form method="POST" action="{{ route('profile.user-detail-change-requests.approve', $changeRequest) }}" class="col-md-6" data-tf-confirm-message="Approve this request? You must still apply the changes before they affect the user.">@csrf @method('PATCH')<div class="input-group"><input name="review_note" class="form-control" maxlength="2000" placeholder="Optional approval note"><button class="btn btn-success">Approve</button></div></form>
                        <form method="POST" action="{{ route('profile.user-detail-change-requests.reject', $changeRequest) }}" class="col-md-6" data-tf-confirm-message="Reject this profile-change request? The user will be notified.">@csrf @method('PATCH')<div class="input-group"><input name="review_note" class="form-control" maxlength="2000" required placeholder="Reason for rejection"><button class="btn btn-outline-danger">Reject</button></div></form>
                    </div>
                @elseif($changeRequest->status === 'Approved')
                    <form method="POST" action="{{ route('profile.user-detail-change-requests.apply', $changeRequest) }}" data-tf-confirm-message="Apply these approved profile changes? The user will be notified.">@csrf @method('PATCH')<button class="btn btn-success">Apply Changes & Notify User</button></form>
                @endif
            </div>
        @empty
            <div class="text-center tf-muted py-3">No user profile-change requests are awaiting review.</div>
        @endforelse
    </div>
@endif
@if($canApproveEmailChanges)
    <div class="tf-card p-4 mt-4">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-1">Staff Email Change Requests</h2><p class="tf-muted mb-0">Approve, reject, or request changes to staff login email requests.</p></div><span class="tf-badge {{ $emailChangeRequests->where('status', 'Pending')->isNotEmpty() ? 'tf-badge-warning' : 'tf-badge-success' }}">{{ $emailChangeRequests->where('status', 'Pending')->count() }} pending</span></div>
        @forelse($emailChangeRequests as $changeRequest)
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-2"><strong>{{ $changeRequest->user?->name }}</strong><span class="tf-badge {{ $changeRequest->status === 'Pending' ? 'tf-badge-warning' : 'tf-badge-info' }}">{{ $changeRequest->status }}</span></div>
                <div class="row g-2 small mb-3"><div class="col-md-6"><div class="bg-light rounded p-2"><span class="tf-muted d-block">Current Email</span>{{ $changeRequest->current_email }}</div></div><div class="col-md-6"><div class="bg-light rounded p-2"><span class="tf-muted d-block">Requested Email</span><strong>{{ $changeRequest->requested_email }}</strong></div></div></div>
                <p class="mb-3"><strong>Reason:</strong> {{ $changeRequest->reason }}</p>
                @if($changeRequest->status === 'Pending')
                    <div class="row g-2">
                        <form method="POST" action="{{ route('profile.email-change-requests.approve', $changeRequest) }}" class="col-md-4" data-tf-confirm-message="Approve and update this staff login email now?">@csrf @method('PATCH')<div class="input-group"><input name="review_note" class="form-control" maxlength="2000" placeholder="Optional note"><button class="btn btn-success">Approve</button></div></form>
                        <form method="POST" action="{{ route('profile.email-change-requests.request-changes', $changeRequest) }}" class="col-md-4">@csrf @method('PATCH')<div class="input-group"><input name="review_note" class="form-control" maxlength="2000" required placeholder="Required changes"><button class="btn btn-outline-primary">Request Changes</button></div></form>
                        <form method="POST" action="{{ route('profile.email-change-requests.reject', $changeRequest) }}" class="col-md-4">@csrf @method('PATCH')<div class="input-group"><input name="review_note" class="form-control" maxlength="2000" required placeholder="Reason for rejection"><button class="btn btn-outline-danger">Reject</button></div></form>
                    </div>
                @elseif($changeRequest->review_note)
                    <p class="mb-0"><strong>Review note:</strong> {{ $changeRequest->review_note }}</p>
                @endif
            </div>
        @empty
            <div class="text-center tf-muted py-3">No staff email-change requests are awaiting review.</div>
        @endforelse
    </div>
@endif
@endsection
