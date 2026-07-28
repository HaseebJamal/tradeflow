@extends('layouts.dashboard')

@section('page-title', 'Notifications')
@section('page-subtitle', 'Platform registrations, alerts, and Super Admin updates')

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach([
            ['All notifications', $counts['all'], 'bi-bell', 'bg-blue'],
            ['Unread queue', $counts['unread'], 'bi-envelope-exclamation', 'bg-amber'],
            ['Company registrations', $counts['registrations'], 'bi-buildings', 'bg-navy'],
            ['System alerts', $counts['alerts'], 'bi-shield-exclamation', 'bg-red'],
        ] as [$label, $value, $icon, $color])
            <div class="col-sm-6 col-xl-3"><div class="tf-card tf-stat-card d-flex justify-content-between align-items-center"><div><p class="tf-muted small mb-1">{{ $label }}</p><p class="h3 mb-0">{{ $value }}</p></div><span class="tf-icon-tile {{ $color }} text-white"><i class="bi {{ $icon }}"></i></span></div></div>
        @endforeach
    </div>

    <div class="tf-card p-3 p-lg-4 mb-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div><h2 class="h5 mb-1">Notification queue</h2><p class="tf-muted small mb-0">Review registrations, acknowledge alerts, and keep the platform queue clean.</p></div>
            @if($counts['unread'])<form method="POST" action="{{ route('admin.notifications.read-all') }}">@csrf @method('PATCH')<button class="btn btn-outline-primary"><i class="bi bi-check2-all me-1"></i>Mark all read</button></form>@endif
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3" role="tablist" aria-label="Notification filters">
            @foreach([
                [null, 'All', $counts['all'], 'admin.notifications.index'],
                ['unread', 'Unread', $counts['unread'], 'admin.notifications.unread'],
                ['registrations', 'Company registrations', $counts['registrations'], 'admin.notifications.registrations'],
                ['alerts', 'System alerts', $counts['alerts'], 'admin.notifications.alerts'],
            ] as [$filter, $label, $count, $route])
                <a class="btn btn-sm {{ $category === $filter ? 'btn-tf-primary' : 'btn-outline-secondary' }}" href="{{ route($route) }}">{{ $label }} <span class="badge {{ $category === $filter ? 'text-bg-light text-primary' : 'text-bg-secondary' }} ms-1">{{ $count }}</span></a>
            @endforeach
        </div>
    </div>

    <div class="tf-card p-0 overflow-hidden">
        <div class="list-group list-group-flush">
            @forelse($notifications as $notification)
                @php($isRegistration = data_get($notification->data, 'category') === 'company_registration')
                @php($isDetailChangeRequest = data_get($notification->data, 'category') === 'business_detail_change_request')
                @php($isFooterChangeRequest = data_get($notification->data, 'category') === 'footer_change_request')
                @php($isSubscriptionRequest = data_get($notification->data, 'category') === 'subscription' && data_get($notification->data, 'subscription_request_id'))
                <div class="list-group-item p-3 p-lg-4 {{ $notification->read_at ? '' : 'bg-light' }}">
                    <div class="d-flex gap-3 justify-content-between align-items-start">
                        <span class="tf-icon-tile {{ $isRegistration ? 'bg-blue' : 'bg-amber' }} text-white flex-shrink-0"><i class="bi {{ $isRegistration ? 'bi-buildings' : 'bi-bell' }}"></i></span>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1"><strong>{{ data_get($notification->data, 'title', 'TradeFlow Notification') }}</strong>@if(!$notification->read_at)<span class="tf-badge tf-badge-info">Unread</span>@endif</div>
                            <p class="mb-2">{{ data_get($notification->data, 'message') }}</p>
                            <small class="tf-muted"><i class="bi bi-clock me-1"></i><x-date-time :value="$notification->created_at" /></small>
                        </div>
                        <div class="d-flex flex-wrap justify-content-end gap-2 flex-shrink-0">
                            @if($isRegistration)
                                <a class="btn btn-sm btn-tf-primary" href="{{ route('admin.notifications.review', $notification->id) }}"><i class="bi bi-clipboard-check me-1"></i>Review registration</a>
                            @endif
                            @if($isDetailChangeRequest)
                                <a class="btn btn-sm btn-tf-primary" href="{{ route('admin.notifications.review', $notification->id) }}"><i class="bi bi-clipboard-check me-1"></i>Review request</a>
                            @endif
                            @if($isFooterChangeRequest)
                                <a class="btn btn-sm btn-tf-primary" href="{{ route('admin.notifications.review', $notification->id) }}"><i class="bi bi-receipt me-1"></i>Review footer change</a>
                            @endif
                            @if($isSubscriptionRequest)
                                <a class="btn btn-sm btn-tf-primary" href="{{ route('admin.notifications.review', $notification->id) }}"><i class="bi bi-credit-card me-1"></i>Review Subscription</a>
                            @endif
                            @if(!$notification->read_at)
                                <form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">Mark read</button></form>
                            @else
                                <form method="POST" action="{{ route('admin.notifications.unread-item', $notification->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-secondary">Mark unread</button></form>
                            @endif
                            <form method="POST" action="{{ route('admin.notifications.destroy', $notification->id) }}" onsubmit="return confirm('Dismiss this notification?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" title="Dismiss notification" aria-label="Dismiss notification"><i class="bi bi-x-lg"></i></button></form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-5 text-center tf-muted">No notifications in this category.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-3">{{ $notifications->links() }}</div>
@endsection
