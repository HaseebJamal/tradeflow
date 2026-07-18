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
                    @if($hasProfileImage)
                        <img src="{{ asset('storage/'.$user->profile_image) }}?v={{ $user->updated_at?->timestamp }}" class="profile-avatar mb-2" alt="{{ $user->name }}" data-tf-profile-preview>
                    @else
                        <span class="profile-avatar tf-avatar-empty mb-2" data-tf-profile-empty><i class="bi bi-person"></i></span>
                        <img src="" class="profile-avatar mb-2 d-none" alt="{{ $user->name }}" data-tf-profile-preview>
                    @endif
                    <div class="fw-bold">{{ $user->name }}</div>
                    <small class="tf-muted">{{ $user->role }}</small>
                </div>
                <div class="col-md-6"><label class="form-label">{{ $requiresOwnerApproval ? 'Requested Name' : 'Name' }}</label><input name="name" class="form-control" value="{{ old('name', $pendingProfileRequest?->requested_values['name'] ?? $user->name) }}" required></div>
                <div class="col-md-6"><label class="form-label">{{ $requiresOwnerApproval ? 'Requested Email' : 'Email' }}</label><input name="email" type="email" class="form-control" value="{{ old('email', $pendingProfileRequest?->requested_values['email'] ?? $user->email) }}" required></div>
                <div class="col-md-6"><label class="form-label">{{ $requiresOwnerApproval ? 'Requested Phone' : 'Phone' }}</label><input name="phone" class="form-control" inputmode="numeric" maxlength="11" value="{{ old('phone', $pendingProfileRequest?->requested_values['phone'] ?? $user->phone) }}"></div>
                <div class="col-md-6">
                    <label class="form-label">Upload New Image</label>
                    <input name="profile_image" type="file" class="form-control" accept="image/jpeg,image/png,image/webp" data-tf-profile-input>
                    <small class="tf-muted">JPG, PNG, or WebP. Max 2MB.</small>
                </div>
                @if($hasProfileImage)
                    <div class="col-12"><small class="tf-muted">Saved image: {{ $user->profile_image }}</small></div>
                    <div class="col-12"><label class="form-check"><input name="remove_image" value="1" type="checkbox" class="form-check-input" data-tf-profile-remove> Remove Image</label></div>
                @endif
                @if($requiresOwnerApproval)
                    <div class="col-12"><label class="form-label">Reason for Change</label><textarea name="reason" class="form-control" rows="3" minlength="10" maxlength="2000" required placeholder="Explain why these profile details need to change.">{{ old('reason', $pendingProfileRequest?->reason) }}</textarea></div>
                @endif
                <div class="col-12"><button class="btn btn-tf-primary" @if($requiresOwnerApproval) data-tf-confirm-message="Submit this profile-change request to your Business Owner?" @endif>{{ $requiresOwnerApproval ? 'Submit Change Request' : 'Save Profile Changes' }}</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        @if($requiresOwnerApproval)
            <div class="tf-card p-4">
                <h2 class="h5">Request Password Change</h2>
                <p class="tf-muted">Password changes require approval from your Business Owner.</p>
                @if($pendingPasswordRequest)
                    <div class="alert alert-warning mb-0">
                        Your password-change request is pending review since {{ $pendingPasswordRequest->requested_at?->timezone(config('app.timezone'))->format('d M, Y h:i A') }}.
                    </div>
                @else
                    <form method="POST" action="{{ route('profile.staff-password-change-requests.store') }}" class="d-grid gap-3" data-tf-confirm-message="Send this password-change request to your Business Owner?">
                        @csrf
                        <div>
                            <label class="form-label" for="passwordChangeReason">Reason for password change</label>
                            <textarea id="passwordChangeReason" name="reason" class="form-control" rows="4" minlength="10" maxlength="2000" required placeholder="Explain why you need your password changed.">{{ old('reason') }}</textarea>
                        </div>
                        <button class="btn btn-outline-primary">Submit Request</button>
                    </form>
                @endif
            </div>
        @else
            <div class="tf-card p-4"><h2 class="h5">Change Password</h2><form method="POST" action="{{ route('profile.password') }}" class="d-grid gap-3">@csrf @method('PUT')
                <div class="input-group"><input id="profileCurrentPassword" name="current_password" type="password" class="form-control" placeholder="Current password" autocomplete="current-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#profileCurrentPassword" data-tf-password-icon="#profileCurrentPasswordIcon"><i id="profileCurrentPasswordIcon" class="bi bi-eye"></i></button></div>
                <div class="input-group"><input id="profileNewPassword" name="password" type="password" class="form-control" placeholder="New password" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#profileNewPassword" data-tf-password-icon="#profileNewPasswordIcon"><i id="profileNewPasswordIcon" class="bi bi-eye"></i></button></div>
                <div class="input-group"><input id="profileConfirmPassword" name="password_confirmation" type="password" class="form-control" placeholder="Confirm password" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#profileConfirmPassword" data-tf-password-icon="#profileConfirmPasswordIcon"><i id="profileConfirmPasswordIcon" class="bi bi-eye"></i></button></div>
                <button class="btn btn-outline-primary">Change Password</button></form></div>
        @endif
    </div>
</div>
@if($user->role === 'business_owner')
    <div class="tf-card p-4 mt-4">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-1">Staff Password Change Requests</h2><p class="tf-muted mb-0">Review staff password-change requests for this business. Passwords are never included in a request.</p></div><span class="tf-badge {{ $staffPasswordChangeRequests->where('status', 'Pending')->isNotEmpty() ? 'tf-badge-warning' : 'tf-badge-success' }}">{{ $staffPasswordChangeRequests->where('status', 'Pending')->count() }} pending</span></div>
        @forelse($staffPasswordChangeRequests as $passwordRequest)
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-2"><strong>{{ $passwordRequest->user?->name }}</strong><span class="tf-badge {{ $passwordRequest->status === 'Pending' ? 'tf-badge-warning' : ($passwordRequest->status === 'Rejected' ? 'tf-badge-danger' : 'tf-badge-success') }}">{{ $passwordRequest->status }}</span></div>
                <p class="mb-1"><strong>Requested:</strong> {{ $passwordRequest->requested_at?->timezone(config('app.timezone'))->format('d M, Y h:i A') }}</p>
                <details class="mb-2"><summary class="text-primary">View request</summary><p class="mb-0 mt-2"><strong>Reason:</strong> {{ $passwordRequest->reason }}</p></details>
                @if($passwordRequest->status === 'Pending')
                    <div class="row g-3">
                        <form method="POST" action="{{ route('profile.staff-password-change-requests.approve', $passwordRequest) }}" class="col-lg-7" data-tf-confirm-message="Approve this request and set the staff member's new password?">@csrf @method('PATCH')
                            <label class="form-label">Approve and set new password</label>
                            <div class="row g-2">
                                <div class="col-md-6"><div class="input-group"><input id="staffPassword{{ $passwordRequest->id }}" name="password" type="password" class="form-control" placeholder="New password" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#staffPassword{{ $passwordRequest->id }}" data-tf-password-icon="#staffPasswordIcon{{ $passwordRequest->id }}"><i id="staffPasswordIcon{{ $passwordRequest->id }}" class="bi bi-eye"></i></button></div></div>
                                <div class="col-md-6"><div class="input-group"><input id="staffPasswordConfirmation{{ $passwordRequest->id }}" name="password_confirmation" type="password" class="form-control" placeholder="Confirm new password" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#staffPasswordConfirmation{{ $passwordRequest->id }}" data-tf-password-icon="#staffPasswordConfirmationIcon{{ $passwordRequest->id }}"><i id="staffPasswordConfirmationIcon{{ $passwordRequest->id }}" class="bi bi-eye"></i></button></div></div>
                                <div class="col-12"><div class="input-group"><input name="review_note" class="form-control" maxlength="2000" placeholder="Optional note to the staff member"><button class="btn btn-success">Approve</button></div></div>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('profile.staff-password-change-requests.reject', $passwordRequest) }}" class="col-lg-5" data-tf-confirm-message="Reject this password-change request? The staff member will be notified.">@csrf @method('PATCH')
                            <label class="form-label">Reject request</label><div class="input-group"><input name="review_note" class="form-control" maxlength="2000" required placeholder="Reason for rejection"><button class="btn btn-outline-danger">Reject</button></div>
                        </form>
                    </div>
                @elseif($passwordRequest->review_note)
                    <p class="mb-0"><strong>Owner note:</strong> {{ $passwordRequest->review_note }}</p>
                @endif
            </div>
        @empty
            <div class="text-center tf-muted py-3">No staff password-change requests are awaiting review.</div>
        @endforelse
    </div>
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
@endsection
