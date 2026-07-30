@extends('layouts.dashboard')
@section('page-title', 'Notifications')
@section('page-subtitle', isset($business) ? $business->business_name.' updates' : 'Account and '.$platformSettings->company_name.' updates')
@section('content')
@php
    $profileRequests = $profileRequests ?? collect();
    $emailChangeRequests = $emailChangeRequests ?? collect();
    $isBusinessOwner = auth()->user()?->role === 'business_owner';
    $canApproveEmailChanges = $canApproveEmailChanges ?? false;
    $readOnlyNotifications = $readOnlyNotifications ?? false;
    $filters = $filters ?? [];
@endphp
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="tf-card p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h2 class="h5 mb-1">Notification centre</h2><p class="tf-muted mb-0">{{ $isBusinessOwner ? 'Review staff requests and manage updates for your business.' : 'Manage your account and business updates.' }}</p></div>
        @if(!$readOnlyNotifications && auth()->user()->unreadNotifications()->count())
            <form method="POST" action="{{ route('notifications.read-all') }}" data-tf-confirm-message="Mark all notifications as read?">@csrf @method('PATCH')<button class="btn btn-outline-primary"><i class="bi bi-check2-all me-1"></i>Mark All as Read</button></form>
        @endif
    </div>
</div>

<div class="tf-card p-3 mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-lg-4 col-md-6"><label class="form-label">Search</label><input name="search" value="{{ $filters['search'] ?? request('search') }}" class="form-control" placeholder="Title, message, or category"></div>
        <div class="col-lg-2 col-md-6"><label class="form-label">Category</label><input name="category" value="{{ $filters['category'] ?? request('category') }}" class="form-control" placeholder="Category"></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="all">All</option><option value="unread" @selected(($filters['status'] ?? request('status')) === 'unread')>Unread</option><option value="read" @selected(($filters['status'] ?? request('status')) === 'read')>Read</option></select></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? request('date_from', now()->toDateString()) }}" class="form-control"></div>
        <div class="col-lg-2 col-md-4"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? request('date_to', now()->toDateString()) }}" class="form-control"></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-tf-primary">Filter</button><a class="btn btn-outline-secondary" href="{{ isset($business) ? route('business.context.notifications') : route('notifications.index') }}">Clear Filters</a></div>
    </form>
</div>

<div class="tf-card p-0 overflow-hidden">
    <x-table>
        <thead><tr><th>#</th><th>Category</th><th>Title</th><th>Message</th><th>Status</th><th>Date</th><th>Time</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($notifications as $notification)
            @php
                $category = data_get($notification->data, 'category', 'general');
                $profileRequest = $category === 'user_detail_change_request' ? $profileRequests->get(data_get($notification->data, 'change_request_id')) : null;
                $emailChangeRequest = $category === 'staff_email_change_request' ? $emailChangeRequests->get(data_get($notification->data, 'email_change_request_id')) : null;
                $requestStatus = $profileRequest?->status ?? $emailChangeRequest?->status;
                $statusClass = $requestStatus === 'Pending' ? 'tf-badge-warning' : ($requestStatus === 'Rejected' ? 'tf-badge-danger' : 'tf-badge-success');
            @endphp
            <tr class="{{ $notification->read_at ? '' : 'table-light' }}">
                <td>{{ $notifications->firstItem() + $loop->index }}</td>
                <td>{{ str($category)->headline() }}</td>
                <td class="fw-semibold">{{ data_get($notification->data, 'title', $platformSettings->company_name.' Notification') }}</td>
                <td title="{{ data_get($notification->data, 'message') }}">{{ str(data_get($notification->data, 'message'))->limit(90) }}</td>
                <td class="text-nowrap">@if(!$notification->read_at)<span class="tf-badge tf-badge-info">Unread</span>@else<span class="tf-badge">Read</span>@endif @if($requestStatus)<span class="tf-badge {{ $statusClass }}">{{ $requestStatus }}</span>@endif</td>
                <td>{{ $notification->created_at?->format('d M, Y') }}</td>
                <td>{{ $notification->created_at?->format('h:i A') }}</td>
                <td class="text-nowrap">
                    @if($profileRequest || $emailChangeRequest)<details class="d-inline-block"><summary class="btn btn-sm btn-outline-primary">View</summary><div class="tf-notification-table-detail text-start">
                        @if($profileRequest)
                            <div class="row g-2 small mb-2">@foreach(['Name' => 'name', 'Email' => 'email', 'Phone' => 'phone'] as $label => $field)<div class="col-md-4"><strong>{{ $label }}:</strong> {{ data_get($profileRequest->old_values, $field) ?: '--' }} <span class="tf-muted">to</span> {{ data_get($profileRequest->requested_values, $field) ?: '--' }}</div>@endforeach<div class="col-12"><strong>Reason:</strong> {{ $profileRequest->reason }}</div></div>
                            @if($isBusinessOwner && $profileRequest->status === 'Pending')
                                <div class="d-flex flex-wrap gap-2"><form method="POST" action="{{ route('profile.user-detail-change-requests.approve', $profileRequest) }}" data-tf-confirm-message="Approve this request?">@csrf @method('PATCH')<input type="hidden" name="review_note" value=""><button class="btn btn-sm btn-success">Approve</button></form><form method="POST" action="{{ route('profile.user-detail-change-requests.reject', $profileRequest) }}">@csrf @method('PATCH')<div class="input-group input-group-sm"><input name="review_note" class="form-control" maxlength="2000" required placeholder="Reason for rejection"><button class="btn btn-outline-danger">Reject</button></div></form></div>
                            @elseif($isBusinessOwner && $profileRequest->status === 'Approved')
                                <form method="POST" action="{{ route('profile.user-detail-change-requests.apply', $profileRequest) }}" data-tf-confirm-message="Apply these approved profile changes?">@csrf @method('PATCH')<button class="btn btn-sm btn-success">Apply Changes</button></form>
                            @endif
                        @elseif($emailChangeRequest)
                            <p class="small mb-2"><strong>Staff:</strong> {{ $emailChangeRequest->user?->name }}<br><strong>Current email:</strong> {{ $emailChangeRequest->current_email }}<br><strong>Requested email:</strong> {{ $emailChangeRequest->requested_email }}<br><strong>Reason:</strong> {{ $emailChangeRequest->reason }}</p>
                            @if($canApproveEmailChanges && $emailChangeRequest->status === 'Pending')<div class="d-flex flex-wrap gap-2"><form method="POST" action="{{ route('profile.email-change-requests.approve', $emailChangeRequest) }}" data-tf-confirm-message="Approve and update this staff login email now?">@csrf @method('PATCH')<input type="hidden" name="review_note" value=""><button class="btn btn-sm btn-success">Approve</button></form><form method="POST" action="{{ route('profile.email-change-requests.request-changes', $emailChangeRequest) }}">@csrf @method('PATCH')<div class="input-group input-group-sm"><input name="review_note" class="form-control" maxlength="2000" required placeholder="Required changes"><button class="btn btn-outline-primary">Send</button></div></form><form method="POST" action="{{ route('profile.email-change-requests.reject', $emailChangeRequest) }}">@csrf @method('PATCH')<div class="input-group input-group-sm"><input name="review_note" class="form-control" maxlength="2000" required placeholder="Reason for rejection"><button class="btn btn-outline-danger">Reject</button></div></form></div>@endif
                        @endif
                    </div></details>@endif
                    @if(!$readOnlyNotifications)
                        @if(!$notification->read_at)<form class="d-inline" method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">Read</button></form>@else<form class="d-inline" method="POST" action="{{ route('notifications.unread', $notification->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">Unread</button></form>@endif
                        <form class="d-inline" method="POST" action="{{ route('notifications.destroy', $notification->id) }}" data-tf-confirm-message="Delete this notification?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Delete notification" aria-label="Delete notification"><i class="bi bi-trash"></i></button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center tf-muted py-4">No notifications found.</td></tr>
        @endforelse
        </tbody>
    </x-table>
</div>
@if($notifications->total())<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3"><small class="tf-muted">Showing {{ $notifications->firstItem() }} to {{ $notifications->lastItem() }} of {{ $notifications->total() }} results</small>{{ $notifications->links('pagination::bootstrap-5') }}</div>@endif
@endsection
