@php($pendingBusinessRequests = app(\App\Services\BusinessRequestIndexService::class)->pendingCount())
<div class="tf-sidebar-inner p-3" data-super-admin-sidebar>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <a class="tf-brand text-white d-flex align-items-center mb-0" href="{{ route('public.home') }}" aria-label="{{ $platformSettings->company_name }} home">
            <span class="tf-brand-mark bg-blue">@if($platformSettings->logo)<img src="{{ asset('storage/'.$platformSettings->logo) }}" class="tf-brand-logo" alt="">@else<i class="bi bi-box-seam"></i>@endif</span><span class="tf-sidebar-text">{{ $platformSettings->company_name }}</span>
        </a>
        <button type="button" class="btn btn-sm btn-outline-light tf-sidebar-toggle tf-sidebar-toggle-inside d-none d-lg-inline-flex" data-tf-sidebar-toggle aria-label="Toggle sidebar" title="Toggle sidebar"><i class="bi bi-list"></i></button>
        <button type="button" class="btn btn-sm btn-outline-light tf-sidebar-close d-lg-none" data-tf-sidebar-close aria-label="Close sidebar"><i class="bi bi-x-lg"></i></button>
    </div>
    <nav class="d-grid gap-1">
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" title="Dashboard"><i class="bi bi-speedometer2"></i><span class="tf-sidebar-text">Dashboard</span></a>
        <a href="{{ route('admin.companies.index') }}" class="{{ request()->routeIs('admin.companies.*') || request()->routeIs('admin.approvals.*') || request()->routeIs('admin.permissions.*') ? 'active' : '' }}" title="Companies"><i class="bi bi-buildings"></i><span class="tf-sidebar-text">Companies</span></a>
        <a href="{{ route('admin.business-requests.index') }}" class="{{ request()->routeIs('admin.business-requests.*') ? 'active' : '' }}" title="Business Requests"><i class="bi bi-inboxes"></i><span class="tf-sidebar-text">Business Requests</span>@if($pendingBusinessRequests)<span class="badge rounded-pill text-bg-danger ms-auto">{{ $pendingBusinessRequests }}</span>@endif</a>
        <a href="{{ route('admin.subscriptions') }}" class="{{ request()->routeIs('admin.subscriptions*') || request()->routeIs('admin.subscription-plans.*') ? 'active' : '' }}" title="Subscriptions"><i class="bi bi-credit-card"></i><span class="tf-sidebar-text">Subscriptions</span></a>
    </nav>
</div>
