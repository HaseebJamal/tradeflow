@php
    $notificationUser = auth()->user();
    $isAdminArea = request()->is('admin/*');
    $notificationContextBusiness = $notificationUser?->role === 'super_admin' && session('super_admin_business_context_id')
        ? \App\Models\Business::find(session('super_admin_business_context_id'))
        : null;
    // Notifications are personal records. Every authenticated dashboard user
    // may read only their own notification relation, regardless of optional
    // operational module permissions.
    $canUseNotifications = (bool) $notificationUser;
    $notificationIndexRoute = $isAdminArea
        ? route('admin.notifications.index')
        : ($notificationContextBusiness ? route('business.context.notifications') : route('notifications.index'));
    $latestNotifications = $canUseNotifications ? $notificationUser?->notifications()->latest()->take(4)->get() : collect();
    $unreadNotificationCount = $canUseNotifications ? $notificationUser?->unreadNotifications()->count() : 0;
@endphp

@if($canUseNotifications)
    <div class="dropdown tf-notification-dropdown">
        <button class="btn btn-light border position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="true" data-tf-notification-toggle aria-expanded="false" aria-label="Notifications" title="Notifications">
            <i class="bi bi-bell"></i>
            @if($unreadNotificationCount)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
            @endif
        </button>
        <div class="dropdown-menu dropdown-menu-end p-0 shadow tf-notification-menu tf-notification-menu--compact" aria-label="Latest notifications">
            <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                <span class="fw-semibold">Notifications</span>
                @if($unreadNotificationCount)<span class="badge text-bg-primary">{{ $unreadNotificationCount }} unread</span>@endif
            </div>
            <div class="tf-notification-list tf-notification-list--compact">
            @forelse($latestNotifications as $notification)
                @php
                    $notificationData = $notification->data ?? [];
                    $notificationTitle = data_get($notificationData, 'title')
                        ?? data_get($notificationData, 'type')
                        ?? data_get($notificationData, 'category')
                        ?? 'Notification';
                @endphp
                <a class="dropdown-item tf-notification-item tf-notification-item--compact {{ is_null($notification->read_at) ? 'tf-notification-unread' : '' }}" href="{{ $notificationIndexRoute }}" title="{{ $notificationTitle }}">
                    <i class="bi {{ is_null($notification->read_at) ? 'bi-bell-fill text-primary' : 'bi-bell text-muted' }}"></i>
                    <span class="tf-notification-title text-truncate">{{ $notificationTitle }}</span>
                    <small class="tf-muted text-nowrap">{{ $notification->created_at?->diffForHumans() }}</small>
                </a>
            @empty
                <p class="tf-muted text-center small mb-0 px-3 py-4">No notifications yet.</p>
            @endforelse
            </div>
            <div class="border-top p-2">
                <a href="{{ $notificationIndexRoute }}" class="btn btn-sm btn-outline-primary w-100">View More Notifications</a>
            </div>
        </div>
    </div>
@endif
