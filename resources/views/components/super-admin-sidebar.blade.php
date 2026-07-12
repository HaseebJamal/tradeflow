@php
    $groups = [
        'companies' => ['Companies', 'bi-buildings', [
            ['All Companies', 'admin.companies.index'], ['Create Company', 'admin.companies.create'], ['Archived Companies', 'admin.companies.archived'], ['Pending Companies', 'admin.companies.pending'], ['Approved Companies', 'admin.companies.approved'], ['Rejected Companies', 'admin.companies.rejected'], ['Suspended Companies', 'admin.companies.suspended'],
        ]],
        'permissions' => ['Permissions', 'bi-shield-lock', [
            ['Company Module Access', 'admin.permissions.modules'], ['Feature Permissions', 'admin.permissions.features'], ['Button / Action Permissions', 'admin.permissions.actions'], ['Permission Templates', 'admin.permissions.templates'],
        ]],
        'operations' => ['Operations', 'bi-grid-1x2', [
            ['Subscriptions', 'admin.subscriptions'], ['Complaints & Support', 'admin.support-tickets'], ['Audit Logs', 'admin.audit-logs'],
        ]],
        'notifications' => ['Notifications', 'bi-bell', [
            ['All Notifications', 'admin.notifications.index'], ['Unread Notifications', 'admin.notifications.unread'], ['Company Registrations', 'admin.notifications.registrations'], ['System Alerts', 'admin.notifications.alerts'],
        ]],
        'approvals' => ['Approvals', 'bi-patch-check', [
            ['Pending Approvals', 'admin.approvals.pending'], ['Approval History', 'admin.approvals.history'], ['Rejected Requests', 'admin.approvals.rejected'], ['Suspended Companies', 'admin.approvals.suspended'],
        ]],
        'profile' => ['Profile', 'bi-person-circle', [
            ['My Profile', 'admin.profile.show'], ['Account Settings', 'admin.profile.settings'], ['Security', 'admin.profile.security'],
        ]],
    ];
    $activeParent = collect($groups)->search(fn ($group) => collect($group[2])->contains(fn ($link) => request()->routeIs($link[1])));
@endphp
<div class="tf-sidebar-inner p-3" data-super-admin-sidebar>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <a class="tf-brand text-white d-flex align-items-center mb-0" href="{{ route('admin.dashboard') }}">
            <span class="tf-brand-mark bg-blue"><i class="bi bi-box-seam"></i></span>
            <span class="tf-sidebar-text">TradeFlow</span>
        </a>
        <button type="button" class="btn btn-sm btn-outline-light tf-sidebar-toggle tf-sidebar-toggle-inside d-none d-lg-inline-flex" data-tf-sidebar-toggle aria-label="Toggle sidebar" title="Toggle sidebar"><i class="bi bi-list"></i></button>
        <button type="button" class="btn btn-sm btn-outline-light tf-sidebar-close d-lg-none" data-tf-sidebar-close aria-label="Close sidebar"><i class="bi bi-x-lg"></i></button>
    </div>
    <nav class="d-grid gap-1">
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" title="Dashboard"><i class="bi bi-speedometer2"></i><span class="tf-sidebar-text">Dashboard</span></a>
        @foreach($groups as $key => [$label, $icon, $links])
            @php($open = $activeParent === $key)
            <button type="button" class="tf-sidebar-parent {{ $open ? 'is-open' : '' }}" data-super-sidebar-toggle="{{ $key }}-menu" aria-expanded="{{ $open ? 'true' : 'false' }}" title="{{ $label }}">
                <span><i class="bi {{ $icon }}"></i><span class="tf-sidebar-text">{{ $label }}</span></span>
            </button>
            <div id="{{ $key }}-menu" class="tf-sidebar-submenu {{ $open ? 'is-open' : '' }}">
                @foreach($links as [$childLabel, $routeName])
                    <a href="{{ route($routeName) }}" class="{{ request()->routeIs($routeName) ? 'active' : '' }}" title="{{ $childLabel }}"><span class="tf-sidebar-text">{{ $childLabel }}</span></a>
                @endforeach
            </div>
        @endforeach
        <a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings*') ? 'active' : '' }}" title="Settings"><i class="bi bi-gear"></i><span class="tf-sidebar-text">Settings</span></a>
    </nav>
</div>
