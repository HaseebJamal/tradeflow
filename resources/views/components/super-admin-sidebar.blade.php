<div class="tf-sidebar-inner p-3" data-super-admin-sidebar>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <a class="tf-brand text-white d-flex align-items-center mb-0" href="{{ route('public.home') }}" aria-label="TradeFlow home">
            <span class="tf-brand-mark tf-platform-brand-mark"><img src="{{ asset('images/tradeflow-mark.svg') }}" alt=""></span>
            <img src="{{ asset('images/tradeflow-logo.svg') }}" class="tf-sidebar-brand-logo tf-sidebar-text" alt="TradeFlow">
        </a>
        <button type="button" class="btn btn-sm btn-outline-light tf-sidebar-toggle tf-sidebar-toggle-inside d-none d-lg-inline-flex" data-tf-sidebar-toggle aria-label="Toggle sidebar" title="Toggle sidebar"><i class="bi bi-list"></i></button>
        <button type="button" class="btn btn-sm btn-outline-light tf-sidebar-close d-lg-none" data-tf-sidebar-close aria-label="Close sidebar"><i class="bi bi-x-lg"></i></button>
    </div>
    <nav class="d-grid gap-1">
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" title="Dashboard"><i class="bi bi-speedometer2"></i><span class="tf-sidebar-text">Dashboard</span></a>
        <a href="{{ route('admin.companies.index') }}" class="{{ request()->routeIs('admin.companies.*') || request()->routeIs('admin.approvals.*') || request()->routeIs('admin.permissions.*') ? 'active' : '' }}" title="Companies"><i class="bi bi-buildings"></i><span class="tf-sidebar-text">Companies</span></a>
        <a href="{{ route('admin.subscriptions') }}" class="{{ request()->routeIs('admin.subscriptions*') || request()->routeIs('admin.subscription-plans.*') ? 'active' : '' }}" title="Subscriptions"><i class="bi bi-credit-card"></i><span class="tf-sidebar-text">Subscriptions</span></a>
    </nav>
</div>
