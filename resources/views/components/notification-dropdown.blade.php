@php
    $notificationUser = auth()->user();
    $notificationContextBusiness = $notificationUser?->role === 'super_admin' && session('super_admin_business_context_id')
        ? \App\Models\Business::find(session('super_admin_business_context_id'))
        : null;
    $isPlatformSuperAdmin = $notificationUser?->role === 'super_admin' && ! $notificationContextBusiness;
    $notificationBusiness = $notificationContextBusiness
        ?? ($notificationUser?->role === 'business_owner'
            ? ($notificationUser?->ownedBusiness ?? $notificationUser?->business)
            : $notificationUser?->business);

    // The bell is a notification-module entry point, so it must follow the
    // same company permission gate as the notification routes. Platform-level
    // Super Admin notifications remain available outside a company context.
    $canUseNotifications = (bool) $notificationUser
        && ($isPlatformSuperAdmin
            || ($notificationBusiness
                && app(\App\Services\CompanyPermissionService::class)
                    ->allowsUser($notificationUser, 'notifications.view', $notificationBusiness)));
    $notificationIndexRoute = $isPlatformSuperAdmin
        ? route('admin.notifications.index')
        : ($notificationContextBusiness ? route('business.context.notifications') : route('notifications.index'));
    $notificationVisibility = app(\App\Services\NotificationVisibilityService::class);
    $latestNotifications = $canUseNotifications
        ? $notificationVisibility->withoutInlineProductPricingAlert($notificationUser?->notifications())->latest()->take(4)->get()
        : collect();
    $unreadNotificationCount = $canUseNotifications
        ? $notificationVisibility->withoutInlineProductPricingAlert($notificationUser?->unreadNotifications())->count()
        : 0;
@endphp

@if($canUseNotifications)
    <div class="dropdown tf-notification-dropdown">
        <button class="btn btn-light border position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="true" data-tf-notification-toggle aria-expanded="false" aria-label="Notifications" title="Notifications">
            <i class="bi bi-bell"></i>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger {{ $unreadNotificationCount ? '' : 'd-none' }}" data-notification-bell-count>{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-0 shadow tf-notification-menu tf-notification-menu--compact" aria-label="Latest notifications">
            <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                <span class="fw-semibold">Notifications</span>
                <span class="badge text-bg-primary {{ $unreadNotificationCount ? '' : 'd-none' }}" data-notification-menu-unread-count>{{ $unreadNotificationCount }} unread</span>
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
