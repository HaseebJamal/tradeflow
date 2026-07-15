<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'TradeFlow Dashboard')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="{{ asset('css/tradeflow.css') }}?v={{ filemtime(public_path('css/tradeflow.css')) }}" rel="stylesheet">
</head>
<body>
<div class="tf-dashboard-shell dashboard-wrapper">
    <aside class="tf-sidebar sidebar" data-tf-sidebar>
        @include('components.sidebar')
    </aside>
    <div class="sidebar-overlay" data-tf-sidebar-overlay></div>
    <div class="tf-dashboard-main main-content">
        @if(request()->is('business/*') && auth()->check() && auth()->user()->role === 'super_admin' && session('super_admin_business_context_id'))
            <div class="alert alert-warning rounded-0 border-0 mb-0 d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 px-lg-4 py-2" data-tf-persistent-alert>
                <span><i class="bi bi-person-workspace me-1"></i>You are currently viewing the dashboard of: <strong>{{ session('super_admin_business_context_name') }}</strong>.</span>
                <form method="POST" action="{{ route('admin.company-context.return') }}">@csrf<button class="btn btn-sm btn-dark">Return to Super Admin Dashboard</button></form>
            </div>
        @endif
        <div class="tf-dashboard-topbar dashboard-header px-3 px-lg-4 py-3 sticky-top">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <button class="btn btn-outline-secondary tf-sidebar-toggle d-lg-none" data-tf-sidebar-toggle aria-label="Open sidebar" title="Open sidebar"><i class="bi bi-list"></i></button>
                <div class="min-w-0">
                    <h1 class="h4 mb-0">@yield('page-title', 'Dashboard')</h1>
                    <small class="tf-muted">@yield('page-subtitle', 'TradeFlow workspace')</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                @auth
                    @if(request()->is('business/*') || request()->is('admin/*'))
                        @php
                            $dashboardUser = auth()->user();
                            $contextBusiness = $dashboardUser->role === 'super_admin' && session('super_admin_business_context_id')
                                ? \App\Models\Business::find(session('super_admin_business_context_id'))
                                : null;
                            $canUseNotificationBell = request()->is('admin/*')
                                || app(\App\Services\CompanyPermissionService::class)->allowsUser($dashboardUser, 'notifications.view', $contextBusiness);
                            $notificationRoute = request()->is('admin/*')
                                ? route('admin.notifications.index')
                                : ($contextBusiness ? route('business.context.notifications') : route('notifications.index'));
                        @endphp
                        @if($canUseNotificationBell)
                            <a href="{{ $notificationRoute }}" class="btn btn-light border position-relative" aria-label="Notifications" title="Notifications">
                                <i class="bi bi-bell"></i>
                                @if($dashboardUser->unreadNotifications()->count())<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $dashboardUser->unreadNotifications()->count() }}</span>@endif
                            </a>
                        @endif
                    @endif
                @endauth
                @include('components.user-dropdown')
            </div>
        </div>
        <main class="dashboard-page">@yield('content')</main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/tradeflow.js') }}?v={{ filemtime(public_path('js/tradeflow.js')) }}"></script>
@stack('scripts')
@auth
<script>
setInterval(() => {
    fetch('{{ route('activity.heartbeat') }}', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    }).catch(() => {});
}, 60000);
</script>
@if((auth()->user()->role === 'super_admin' && request()->is('admin/*')) || request()->is('business/*'))
<script>
document.addEventListener('DOMContentLoaded', () => {
    const fields = [...document.querySelectorAll('.dashboard-page form input:not([type="hidden"]):not([disabled]), .dashboard-page form select:not([disabled]), .dashboard-page form textarea:not([disabled])')]
        .filter((field) => field.offsetParent !== null);
    fields.forEach((field, index) => field.tabIndex = index + 1);
    if (!document.activeElement || document.activeElement === document.body) fields[0]?.focus();
});
</script>
@endif
@endauth
<script>
(() => {
    const key = 'tradeflow_super_admin_sidebar_open';
    const toggles = [...document.querySelectorAll('[data-super-sidebar-toggle]')];
    if (!toggles.length) return;

    const setOpen = (id) => {
        toggles.forEach((button) => {
            const open = button.dataset.superSidebarToggle === id;
            button.classList.toggle('is-open', open);
            button.setAttribute('aria-expanded', String(open));
            document.getElementById(button.dataset.superSidebarToggle)?.classList.toggle('is-open', open);
        });

        if (id) localStorage.setItem(key, id);
        else localStorage.removeItem(key);
    };

    const active = toggles.find((button) => button.classList.contains('is-open'))?.dataset.superSidebarToggle;
    if (active) setOpen(active);
    else {
        const stored = localStorage.getItem(key);
        if (stored && document.getElementById(stored)) setOpen(stored);
    }

    toggles.forEach((button) => button.addEventListener('click', () => {
        setOpen(button.classList.contains('is-open') ? '' : button.dataset.superSidebarToggle);
    }));
})();
</script>
</body>
</html>
