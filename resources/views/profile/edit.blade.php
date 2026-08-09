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
                    <div class="d-inline-flex flex-column align-items-center gap-2" data-tf-profile-image-controls>
                        <button type="button" class="tf-profile-image-button" data-tf-profile-editor-trigger aria-label="Edit profile image" title="Edit profile image">
                            @if($hasProfileImage)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_image) }}?v={{ $user->updated_at?->timestamp }}" class="profile-avatar" alt="{{ $user->name }}" data-tf-profile-preview>
                            @else
                                <span class="profile-avatar tf-avatar-empty" data-tf-profile-empty><i class="bi bi-person"></i></span>
                                <img src="" class="profile-avatar d-none" alt="{{ $user->name }}" data-tf-profile-preview>
                            @endif
                        </button>
                        <div class="tf-profile-image-actions">
                            <input id="profileImageUpload" name="profile_image" type="file" class="visually-hidden" accept="image/jpeg,image/png,image/webp" data-tf-profile-input data-tf-image-upload tabindex="-1">
                            <label for="profileImageUpload" class="btn btn-outline-primary btn-sm mb-0"><i class="bi bi-image me-1"></i>{{ $hasProfileImage ? 'Change profile image' : 'Upload profile image' }}</label>
                            @if($hasProfileImage)
                                <input name="remove_image" value="1" type="checkbox" class="d-none" data-tf-profile-remove>
                                <button type="button" class="btn btn-link btn-sm text-danger p-0 ms-2" data-tf-profile-remove-action>Remove</button>
                            @endif
                            <small class="tf-muted d-block mt-1" data-tf-image-file-status>JPG, PNG, or WebP · max 2 MB</small>
                            <div class="invalid-feedback" data-tf-image-error></div>
                        </div>
                    </div>
                    <div class="fw-bold">{{ $user->name }}</div>
                    <small class="tf-muted">{{ $user->role }}</small>
                </div>
                <div class="col-md-6"><label class="form-label" for="profileName">{{ $requiresOwnerApproval ? 'Requested Name' : 'Name' }}</label><input id="profileName" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $pendingProfileRequest?->requested_values['name'] ?? $user->name) }}" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @if($requiresOwnerApproval)
                    <div class="col-md-6"><label class="form-label">Current Email</label><input type="email" class="form-control" value="{{ $user->email }}" readonly><small class="tf-muted">Login email changes require approval.</small></div>
                @else
                    <div class="col-md-6"><label class="form-label" for="profileEmail">Email</label><input id="profileEmail" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @endif
                <div class="col-md-6"><label class="form-label">{{ $requiresOwnerApproval ? 'Requested Phone' : 'Phone' }}</label><x-phone-input name="phone" :value="old('phone', $pendingProfileRequest?->requested_values['phone'] ?? $user->phone)" :error="$errors->first('phone')" /></div>
                @if($requiresOwnerApproval)
                    <div class="col-12"><label class="form-label">Reason for Change</label><textarea name="reason" class="form-control" rows="3" minlength="10" maxlength="2000" placeholder="Required only when changing your name or phone.">{{ old('reason', $pendingProfileRequest?->reason) }}</textarea></div>
                @endif
                <div class="col-12"><button class="btn btn-tf-primary" @if($requiresOwnerApproval) data-tf-confirm-message="Submit this profile-change request to your Business Owner?" @endif>{{ $requiresOwnerApproval ? 'Submit Change Request' : 'Save Profile Changes' }}</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="tf-card p-4"><h2 class="h5">Change Password</h2><form method="POST" action="{{ route('profile.password') }}" class="d-grid gap-3">@csrf @method('PUT')
                <div><div class="input-group"><input id="profileCurrentPassword" name="current_password" type="password" class="form-control @error('current_password') is-invalid @enderror" placeholder="Current password" autocomplete="current-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#profileCurrentPassword" data-tf-password-icon="#profileCurrentPasswordIcon"><i id="profileCurrentPasswordIcon" class="bi bi-eye"></i></button></div>@error('current_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <div><div class="input-group"><input id="profileNewPassword" name="password" type="password" class="form-control @error('password') is-invalid @enderror" placeholder="New password" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#profileNewPassword" data-tf-password-icon="#profileNewPasswordIcon"><i id="profileNewPasswordIcon" class="bi bi-eye"></i></button></div>@error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <div><div class="input-group"><input id="profileConfirmPassword" name="password_confirmation" type="password" class="form-control" placeholder="Confirm password" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#profileConfirmPassword" data-tf-password-icon="#profileConfirmPasswordIcon"><i id="profileConfirmPasswordIcon" class="bi bi-eye"></i></button></div>@error('password_confirmation')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
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

<div class="modal fade" id="profileImageEditorModal" tabindex="-1" aria-labelledby="profileImageEditorTitle" aria-hidden="true" data-tf-profile-image-editor>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <div><h2 class="modal-title fs-5" id="profileImageEditorTitle">Adjust profile image</h2><p class="mb-0 small text-muted">Drag to reposition, zoom, or rotate before saving.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="tf-profile-crop-surface mx-auto"><canvas width="512" height="512" data-tf-profile-crop-canvas aria-label="Profile image crop area"></canvas></div>
                <div class="d-flex align-items-center gap-3 mt-4"><i class="bi bi-zoom-out text-muted"></i><input type="range" class="form-range flex-grow-1" min="1" max="3" value="1" step="0.01" data-tf-profile-crop-zoom aria-label="Zoom image"><i class="bi bi-zoom-in text-muted"></i></div>
                <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
                    <button type="button" class="btn btn-light border" data-tf-profile-crop-rotate="-90"><i class="bi bi-arrow-counterclockwise me-1"></i>Rotate left</button>
                    <button type="button" class="btn btn-light border" data-tf-profile-crop-rotate="90">Rotate right<i class="bi bi-arrow-clockwise ms-1"></i></button>
                    <button type="button" class="btn btn-link" data-tf-profile-crop-reset>Reset</button>
                </div>
                <div class="small text-muted text-center mt-3" data-tf-profile-crop-error role="alert"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-tf-primary" data-tf-profile-crop-apply>Apply image</button></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('form[action="{{ route('profile.update') }}"]');
    const input = form?.querySelector('[data-tf-profile-input]');
    const trigger = form?.querySelector('[data-tf-profile-editor-trigger]');
    const modal = document.querySelector('[data-tf-profile-image-editor]');
    const canvas = modal?.querySelector('[data-tf-profile-crop-canvas]');
    if (!form || !input || !trigger || !modal || !canvas || !window.bootstrap) return;

    const context = canvas.getContext('2d');
    const zoomControl = modal.querySelector('[data-tf-profile-crop-zoom]');
    const error = modal.querySelector('[data-tf-profile-crop-error]');
    const preview = form.querySelector('[data-tf-profile-preview]');
    const emptyAvatar = form.querySelector('[data-tf-profile-empty]');
    const fileStatus = form.querySelector('[data-tf-image-file-status]');
    const editor = bootstrap.Modal.getOrCreateInstance(modal);
    const state = { image: null, zoom: 1, rotation: 0, offsetX: 0, offsetY: 0, dragging: false, pointerX: 0, pointerY: 0 };

    const setError = (message = '') => { error.textContent = message; };
    const normalisedRotation = () => ((state.rotation % 360) + 360) % 360;
    const coverScale = () => {
        if (!state.image) return 1;
        const sideways = normalisedRotation() % 180 !== 0;
        const width = sideways ? state.image.naturalHeight : state.image.naturalWidth;
        const height = sideways ? state.image.naturalWidth : state.image.naturalHeight;
        return Math.max(canvas.width / width, canvas.height / height);
    };
    const draw = () => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#f1f5f9';
        context.fillRect(0, 0, canvas.width, canvas.height);
        if (!state.image) return;
        const scale = coverScale() * state.zoom;
        const width = state.image.naturalWidth * scale;
        const height = state.image.naturalHeight * scale;
        context.save();
        context.translate(canvas.width / 2 + state.offsetX, canvas.height / 2 + state.offsetY);
        context.rotate(state.rotation * Math.PI / 180);
        context.drawImage(state.image, -width / 2, -height / 2, width, height);
        context.restore();
        context.save();
        context.strokeStyle = 'rgba(255,255,255,.92)';
        context.lineWidth = 4;
        context.strokeRect(2, 2, canvas.width - 4, canvas.height - 4);
        context.restore();
    };
    const reset = () => {
        state.zoom = 1;
        state.rotation = 0;
        state.offsetX = 0;
        state.offsetY = 0;
        zoomControl.value = '1';
        draw();
    };
    const openEditor = (file) => {
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            setError('Choose a JPG, PNG, or WebP image smaller than 2 MB.');
            editor.show();
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            const image = new Image();
            image.onload = () => {
                state.image = image;
                setError('');
                reset();
                editor.show();
            };
            image.onerror = () => { setError('This image could not be opened. Please choose another file.'); editor.show(); };
            image.src = String(reader.result || '');
        };
        reader.readAsDataURL(file);
    };

    input.addEventListener('change', (event) => {
        if (input.dataset.tfCropApplying === '1') {
            delete input.dataset.tfCropApplying;
            return;
        }
        const file = input.files?.[0];
        if (!file) return;
        event.stopImmediatePropagation();
        input.value = '';
        openEditor(file);
    }, true);

    trigger.addEventListener('click', async () => {
        if (!preview?.src || preview.classList.contains('d-none')) {
            input.click();
            return;
        }
        try {
            const response = await fetch(preview.src);
            const blob = await response.blob();
            openEditor(new File([blob], 'profile-image.jpg', { type: blob.type || 'image/jpeg' }));
        } catch (_) {
            input.click();
        }
    });
    zoomControl.addEventListener('input', () => { state.zoom = Number(zoomControl.value); draw(); });
    modal.querySelectorAll('[data-tf-profile-crop-rotate]').forEach((button) => button.addEventListener('click', () => {
        state.rotation += Number(button.dataset.tfProfileCropRotate || 0);
        state.offsetX = 0;
        state.offsetY = 0;
        draw();
    }));
    modal.querySelector('[data-tf-profile-crop-reset]').addEventListener('click', reset);
    canvas.addEventListener('pointerdown', (event) => {
        state.dragging = true;
        state.pointerX = event.clientX;
        state.pointerY = event.clientY;
        canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', (event) => {
        if (!state.dragging) return;
        const bounds = canvas.getBoundingClientRect();
        state.offsetX += (event.clientX - state.pointerX) * (canvas.width / bounds.width);
        state.offsetY += (event.clientY - state.pointerY) * (canvas.height / bounds.height);
        state.pointerX = event.clientX;
        state.pointerY = event.clientY;
        draw();
    });
    ['pointerup', 'pointercancel'].forEach((eventName) => canvas.addEventListener(eventName, () => { state.dragging = false; }));
    modal.querySelector('[data-tf-profile-crop-apply]').addEventListener('click', () => {
        if (!state.image) return;
        canvas.toBlob((blob) => {
            if (!blob) { setError('Unable to prepare this image. Please try another file.'); return; }
            const transfer = new DataTransfer();
            transfer.items.add(new File([blob], 'profile-image.jpg', { type: 'image/jpeg' }));
            input.files = transfer.files;
            input.dataset.tfCropApplying = '1';
            input.dispatchEvent(new Event('change', { bubbles: true }));
            fileStatus.textContent = 'Profile image ready. Click Save Profile Changes to upload it.';
            emptyAvatar?.classList.add('d-none');
            editor.hide();
        }, 'image/jpeg', 0.92);
    });
})();
</script>
@endpush
