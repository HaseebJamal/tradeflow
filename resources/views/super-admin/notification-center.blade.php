@extends('layouts.dashboard')

@section('page-title', 'Notifications')
@section('page-subtitle', 'Platform registrations, alerts, and Super Admin updates')

@section('content')
@if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach([
            ['All notifications', $counts['all'], 'bi-bell', 'bg-blue'],
            ['Unread queue', $counts['unread'], 'bi-envelope-exclamation', 'bg-amber'],
            ['Company registrations', $counts['registrations'], 'bi-buildings', 'bg-navy'],
            ['System alerts', $counts['alerts'], 'bi-shield-exclamation', 'bg-red'],
        ] as [$label, $value, $icon, $color])
            <div class="col-sm-6 col-xl-3"><div class="tf-card tf-stat-card d-flex justify-content-between align-items-center"><div><p class="tf-muted small mb-1">{{ $label }}</p><p class="h3 mb-0" @if($label === 'Unread queue') data-notification-unread-count @endif>{{ $value }}</p></div><span class="tf-icon-tile {{ $color }} text-white"><i class="bi {{ $icon }}"></i></span></div></div>
        @endforeach
    </div>

    <div class="tf-card p-3 mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div><h2 class="h5 mb-1">Notification queue</h2><p class="tf-muted small mb-0">Review registrations, acknowledge alerts, and keep the platform queue clean.</p></div>
            @if($counts['unread'])<form method="POST" action="{{ route('admin.notifications.read-all') }}" data-notifications-mark-all-read>@csrf @method('PATCH')<button class="btn btn-outline-primary"><i class="bi bi-check2-all me-1"></i>Mark all read</button></form>@endif
        </div>
    </div>

    <div class="tf-card p-3 mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-6"><label class="form-label">Search</label><input name="search" value="{{ $filters['search'] ?? request('search') }}" class="form-control" placeholder="Title, message, or category"></div>
            <div class="col-lg-2 col-md-6"><label class="form-label">Category</label><input name="category" value="{{ $filters['category'] ?? request('category') }}" class="form-control" placeholder="Category"></div>
            <div class="col-lg-2 col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="all">All</option><option value="unread" @selected(($filters['status'] ?? request('status')) === 'unread')>Unread</option><option value="read" @selected(($filters['status'] ?? request('status')) === 'read')>Read</option></select></div>
            <div class="col-lg-2 col-md-4"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? request('date_from', now()->toDateString()) }}" class="form-control"></div>
            <div class="col-lg-2 col-md-4"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? request('date_to', now()->toDateString()) }}" class="form-control"></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-tf-primary">Filter</button><a class="btn btn-outline-secondary" href="{{ route('admin.notifications.index') }}">Clear Filters</a></div>
        </form>
    </div>

    <x-table class="tf-notification-data-table"><thead><tr><th>#</th><th>Category</th><th>Title</th><th>Message</th><th>Status</th><th>Date</th><th>Time</th><th>Actions</th></tr></thead><tbody>
        @forelse($notifications as $notification)
            @php($notificationCategory = data_get($notification->data, 'category', 'general'))
            @php($isReviewable = in_array($notificationCategory, ['company_registration', 'business_detail_change_request', 'footer_change_request'], true) || ($notificationCategory === 'subscription' && data_get($notification->data, 'subscription_request_id')))
            <tr class="{{ $notification->read_at ? '' : 'tf-notification-row--unread' }}" data-notification-row="{{ $notification->id }}"><td>{{ $notifications->firstItem() + $loop->index }}</td><td>{{ str($notificationCategory)->headline() }}</td><td class="fw-semibold">{{ data_get($notification->data, 'title', $platformSettings->company_name.' Notification') }}</td><td title="{{ data_get($notification->data, 'message') }}">{{ str(data_get($notification->data, 'message'))->limit(90) }}</td><td data-notification-status>@if(!$notification->read_at)<span class="tf-badge tf-badge-info">Unread</span>@else<span class="tf-badge">Read</span>@endif</td><td>{{ $notification->created_at?->format('n/j/Y') }}</td><td>{{ $notification->created_at?->format('h:i A') }}</td><td class="text-nowrap"><button class="btn btn-sm btn-tf-primary" type="button" data-notification-view="{{ route('admin.notifications.show', $notification->id) }}">View</button> @if(!$notification->read_at)<form class="d-inline" method="POST" action="{{ route('admin.notifications.read', $notification->id) }}" data-notification-read-action>@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">Mark as Read</button></form>@endif <form class="d-inline" method="POST" action="{{ route('admin.notifications.destroy', $notification->id) }}" data-tf-confirm-message="Dismiss this notification?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Dismiss notification" aria-label="Dismiss notification"><i class="bi bi-trash"></i></button></form></td></tr>
        @empty
            <tr><td colspan="8" class="text-center tf-muted py-4">No notifications found.</td></tr>
        @endforelse
    </tbody></x-table>
    @if($notifications->total())<div class="mt-3">{{ $notifications->links('pagination::bootstrap-5') }}</div>@endif
    <div class="modal fade" id="notificationViewModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-md modal-dialog-scrollable"><div class="modal-content"><div class="modal-header py-2"><h2 class="modal-title h6" data-notification-modal-title>Notification</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body py-3"><dl class="row mb-0 small"><dt class="col-4">Category</dt><dd class="col-8" data-notification-category></dd><dt class="col-4">Message</dt><dd class="col-8 text-wrap" data-notification-message></dd><dt class="col-4">Status</dt><dd class="col-8" data-notification-modal-status></dd><dt class="col-4">Date</dt><dd class="col-8" data-notification-date></dd><dt class="col-4">Time</dt><dd class="col-8" data-notification-time></dd><dt class="col-4 d-none" data-notification-business-label>Company</dt><dd class="col-8 d-none fw-semibold" data-notification-business></dd></dl></div><div class="modal-footer py-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><a class="btn btn-tf-primary d-none" data-notification-action></a></div></div></div></div>
@push('scripts')
<script>
const updateNotificationUnreadCounts = (count) => {
    document.querySelectorAll('[data-notification-unread-count]').forEach((element) => {
        element.textContent = count;
    });
    document.querySelectorAll('[data-notification-bell-count]').forEach((badge) => {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.toggle('d-none', count === 0);
    });
    document.querySelectorAll('[data-notification-menu-unread-count]').forEach((badge) => {
        badge.textContent = count + ' unread';
        badge.classList.toggle('d-none', count === 0);
    });
    document.querySelectorAll('[data-notifications-mark-all-read]').forEach((form) => {
        form.classList.toggle('d-none', count === 0);
    });
};

document.querySelectorAll('[data-notification-view]').forEach((button) => button.addEventListener('click', async () => {
    const response = await fetch(button.dataset.notificationView, { headers: { Accept: 'application/json' } });
    if (!response.ok) return;

    const item = await response.json();
    const modal = document.querySelector('#notificationViewModal');
    modal.querySelector('[data-notification-modal-title]').textContent = item.title;
    modal.querySelector('[data-notification-category]').textContent = item.category;
    modal.querySelector('[data-notification-message]').textContent = item.message || '';
    modal.querySelector('[data-notification-modal-status]').textContent = item.status;
    modal.querySelector('[data-notification-date]').textContent = item.date || '';
    modal.querySelector('[data-notification-time]').textContent = item.time || '';

    const company = modal.querySelector('[data-notification-business]');
    const companyLabel = modal.querySelector('[data-notification-business-label]');
    company.textContent = item.business || '';
    company.classList.toggle('d-none', !item.business);
    companyLabel.classList.toggle('d-none', !item.business);

    const action = modal.querySelector('[data-notification-action]');
    action.classList.toggle('d-none', !item.action);
    if (item.action) {
        action.href = item.action.url;
        action.textContent = item.action.label;
    }

    const row = document.querySelector('[data-notification-row="' + item.id + '"]');
    row?.classList.remove('tf-notification-row--unread');
    row?.querySelector('[data-notification-status]')?.replaceChildren(
        Object.assign(document.createElement('span'), { className: 'tf-badge', textContent: 'Read' })
    );
    row?.querySelector('[data-notification-read-action]')?.remove();
    updateNotificationUnreadCounts(Number(item.unread_count) || 0);

    bootstrap.Modal.getOrCreateInstance(modal).show();
}));
</script>
@endpush
@endsection
