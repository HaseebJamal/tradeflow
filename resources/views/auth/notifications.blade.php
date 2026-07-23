@extends('layouts.dashboard')
@section('page-title', 'Notifications')
@section('page-subtitle', isset($business) ? $business->business_name.' updates' : 'Account and TradeFlow updates')
@section('content')
@php
    $profileRequests = $profileRequests ?? collect();
    $emailChangeRequests = $emailChangeRequests ?? collect();
    $passwordRequest = null;
    $isBusinessOwner = auth()->user()?->role === 'business_owner';
    $canApproveEmailChanges = $canApproveEmailChanges ?? false;
    $readOnlyNotifications = $readOnlyNotifications ?? false;
@endphp
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="tf-card p-3 p-lg-4 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 mb-1">Notification centre</h2>
            <p class="tf-muted mb-0">{{ $isBusinessOwner ? 'Review staff requests and manage updates for your business.' : 'Manage your account and business updates.' }}</p>
        </div>
        @if(!$readOnlyNotifications && auth()->user()->unreadNotifications()->count())
            <form method="POST" action="{{ route('notifications.read-all') }}" data-tf-confirm-message="Mark all notifications as read?">@csrf @method('PATCH')<button class="btn btn-outline-primary"><i class="bi bi-check2-all me-1"></i>Mark All as Read</button></form>
        @endif
    </div>
</div>

<div class="tf-card p-0 overflow-hidden">
    <div class="list-group list-group-flush">
        @forelse($notifications as $notification)
            @php
                $category = data_get($notification->data, 'category');
                $profileRequest = $category === 'user_detail_change_request' ? $profileRequests->get(data_get($notification->data, 'change_request_id')) : null;
                $emailChangeRequest = $category === 'staff_email_change_request' ? $emailChangeRequests->get(data_get($notification->data, 'email_change_request_id')) : null;
                $requestStatus = $profileRequest?->status ?? $emailChangeRequest?->status;
                $pending = $requestStatus === 'Pending';
                $statusClass = $requestStatus === 'Pending' ? 'tf-badge-warning' : ($requestStatus === 'Rejected' ? 'tf-badge-danger' : 'tf-badge-success');
            @endphp
            <article id="notification-{{ $notification->id }}" class="list-group-item p-3 p-lg-4 {{ $notification->read_at ? '' : 'bg-light' }}">
                <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-start">
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <strong>{{ data_get($notification->data, 'title', 'TradeFlow Notification') }}</strong>
                            @if(!$notification->read_at)<span class="tf-badge tf-badge-info">Unread</span>@endif
                            @if($requestStatus)<span class="tf-badge {{ $statusClass }}">{{ $requestStatus }}</span>@endif
                        </div>
                        <p class="mb-2">{{ data_get($notification->data, 'message') }}</p>
                        <small class="tf-muted d-block"><i class="bi bi-clock me-1"></i><x-date-time :value="$notification->created_at" /></small>

                        @if($profileRequest)
                            <details class="mt-3">
                                <summary class="text-primary">View Details</summary>
                                <div class="row g-2 small mt-1">
                                    @foreach(['Name' => 'name', 'Email' => 'email', 'Phone' => 'phone'] as $label => $field)
                                        <div class="col-md-4"><div class="border rounded p-2"><span class="tf-muted d-block">{{ $label }}</span><span>Current: {{ data_get($profileRequest->old_values, $field) ?: '—' }}</span><strong class="d-block">Requested: {{ data_get($profileRequest->requested_values, $field) ?: '—' }}</strong></div></div>
                                    @endforeach
                                    @if(data_get($profileRequest->requested_values, 'profile_image'))<div class="col-12"><span class="tf-muted">A new profile image was requested.</span></div>@endif
                                    <div class="col-12"><strong>Reason:</strong> {{ $profileRequest->reason }}</div>
                                </div>
                            </details>
                        @elseif($emailChangeRequest)
                            <details class="mt-3"><summary class="text-primary">View Details</summary><p class="mb-0 mt-2"><strong>Staff member:</strong> {{ $emailChangeRequest->user?->name }}<br><strong>Current Email:</strong> {{ $emailChangeRequest->current_email }}<br><strong>Requested Email:</strong> {{ $emailChangeRequest->requested_email }}<br><strong>Reason:</strong> {{ $emailChangeRequest->reason }}</p></details>
                        @endif
                    </div>

                    <div id="notification-actions-{{ $notification->id }}" class="tf-notification-actions flex-shrink-0">
                        <div class="tf-notification-action-row">
                        @if($isBusinessOwner && $profileRequest && $pending)
                            <form method="POST" action="{{ route('profile.user-detail-change-requests.approve', $profileRequest) }}" data-tf-confirm-message="Approve this request? Apply the approved changes afterwards to update the staff profile.">@csrf @method('PATCH')<input type="hidden" name="review_note" value=""><button class="btn btn-sm btn-success">Approve</button></form>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#notificationProfileReject{{ $notification->id }}" aria-expanded="false" aria-controls="notificationProfileReject{{ $notification->id }}">Reject</button>
                        @elseif($isBusinessOwner && $profileRequest && $requestStatus === 'Approved')
                            <form method="POST" action="{{ route('profile.user-detail-change-requests.apply', $profileRequest) }}" data-tf-confirm-message="Apply these approved changes and notify the staff member?">@csrf @method('PATCH')<button class="btn btn-sm btn-success">Apply Changes</button></form>
                        @endif

                        @if($canApproveEmailChanges && $emailChangeRequest && $pending)
                            <form method="POST" action="{{ route('profile.email-change-requests.approve', $emailChangeRequest) }}" data-tf-confirm-message="Approve and update this staff login email now?">@csrf @method('PATCH')<input type="hidden" name="review_note" value=""><button class="btn btn-sm btn-success">Approve</button></form>
                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#notificationEmailChanges{{ $notification->id }}" aria-expanded="false">Request Changes</button>
                            <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#notificationEmailReject{{ $notification->id }}" aria-expanded="false">Reject</button>
                        @endif
                        </div>

                        @if(!$readOnlyNotifications)
                            <div class="tf-notification-utility-actions">
                                @if(!$notification->read_at)
                                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">Mark as Read</button></form>
                                @else
                                    <form method="POST" action="{{ route('notifications.unread', $notification->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">Mark as Unread</button></form>
                                @endif
                                <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}" data-tf-confirm-message="Delete this notification?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Delete notification" aria-label="Delete notification"><i class="bi bi-trash"></i></button></form>
                            </div>
                        @endif

                        @if($isBusinessOwner && $profileRequest && $pending)
                            <div id="notificationProfileReject{{ $notification->id }}" class="collapse tf-notification-action-panel" data-bs-parent="#notification-actions-{{ $notification->id }}">
                                <form method="POST" action="{{ route('profile.user-detail-change-requests.reject', $profileRequest) }}">@csrf @method('PATCH')<label class="form-label" for="notificationProfileRejectNote{{ $notification->id }}">Optional rejection note</label><div class="input-group"><input id="notificationProfileRejectNote{{ $notification->id }}" name="review_note" class="form-control form-control-sm" maxlength="2000" placeholder="Optional rejection note"><button class="btn btn-sm btn-outline-danger">Reject Request</button></div></form>
                            </div>
                        @endif
                        @if($isBusinessOwner && $passwordRequest && $pending)
                            <div id="notificationApprove{{ $notification->id }}" class="collapse tf-notification-action-panel" data-bs-parent="#notification-actions-{{ $notification->id }}">
                                <form method="POST" action="{{ route('profile.staff-password-change-requests.approve', $passwordRequest) }}" data-tf-confirm-message="Approve this request and set the staff member's new password?">
                                    @csrf @method('PATCH')
                                    <label class="form-label" for="notificationPassword{{ $passwordRequest->id }}">New Password</label>
                                    <div class="input-group mb-2"><input id="notificationPassword{{ $passwordRequest->id }}" name="password" type="password" class="form-control form-control-sm" placeholder="New password" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#notificationPassword{{ $passwordRequest->id }}" data-tf-password-icon="#notificationPasswordIcon{{ $passwordRequest->id }}"><i id="notificationPasswordIcon{{ $passwordRequest->id }}" class="bi bi-eye"></i></button></div>
                                    <label class="form-label" for="notificationPasswordConfirmation{{ $passwordRequest->id }}">Confirm Password</label>
                                    <div class="input-group mb-2"><input id="notificationPasswordConfirmation{{ $passwordRequest->id }}" name="password_confirmation" type="password" class="form-control form-control-sm" placeholder="Confirm new password" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#notificationPasswordConfirmation{{ $passwordRequest->id }}" data-tf-password-icon="#notificationPasswordConfirmationIcon{{ $passwordRequest->id }}"><i id="notificationPasswordConfirmationIcon{{ $passwordRequest->id }}" class="bi bi-eye"></i></button></div>
                                    <label class="form-label" for="notificationPasswordNote{{ $passwordRequest->id }}">Optional Note</label>
                                    <input id="notificationPasswordNote{{ $passwordRequest->id }}" name="review_note" class="form-control form-control-sm mb-2" maxlength="2000" placeholder="Optional note">
                                    <button class="btn btn-sm btn-success w-100">Approve & Set Password</button>
                                </form>
                            </div>
                            <div id="notificationPasswordReject{{ $notification->id }}" class="collapse tf-notification-action-panel" data-bs-parent="#notification-actions-{{ $notification->id }}">
                                <form method="POST" action="{{ route('profile.staff-password-change-requests.reject', $passwordRequest) }}">@csrf @method('PATCH')<label class="form-label" for="notificationPasswordRejectNote{{ $notification->id }}">Optional rejection note</label><div class="input-group"><input id="notificationPasswordRejectNote{{ $notification->id }}" name="review_note" class="form-control form-control-sm" maxlength="2000" placeholder="Optional rejection note"><button class="btn btn-sm btn-outline-danger">Reject Request</button></div></form>
                            </div>
                        @endif
                        @if($canApproveEmailChanges && $emailChangeRequest && $pending)
                            <div id="notificationEmailChanges{{ $notification->id }}" class="collapse tf-notification-action-panel" data-bs-parent="#notification-actions-{{ $notification->id }}">
                                <form method="POST" action="{{ route('profile.email-change-requests.request-changes', $emailChangeRequest) }}">@csrf @method('PATCH')<label class="form-label">Required changes</label><div class="input-group"><input name="review_note" class="form-control form-control-sm" maxlength="2000" required><button class="btn btn-sm btn-outline-primary">Send</button></div></form>
                            </div>
                            <div id="notificationEmailReject{{ $notification->id }}" class="collapse tf-notification-action-panel" data-bs-parent="#notification-actions-{{ $notification->id }}">
                                <form method="POST" action="{{ route('profile.email-change-requests.reject', $emailChangeRequest) }}">@csrf @method('PATCH')<label class="form-label">Reason for rejection</label><div class="input-group"><input name="review_note" class="form-control form-control-sm" maxlength="2000" required><button class="btn btn-sm btn-outline-danger">Reject</button></div></form>
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="p-5 text-center tf-muted">No notifications yet.</div>
        @endforelse
    </div>
</div>
<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
