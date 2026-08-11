@php($platformLogoPath = preg_replace('#^(?:public/|storage/)#', '', ltrim((string) ($platformSettings->logo ?? ''), '/')))
@php($hasPlatformLogo = filled($platformLogoPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($platformLogoPath))
@php($sidebarCounts = app(\App\Services\SuperAdminSidebarBadgeService::class)->forUser(auth()->user()))
@php($badgeLabel = fn (int $count): string => $count > 99 ? '99+' : (string) $count)
<div class="tf-sidebar-inner p-3" data-super-admin-sidebar>
    <div class="d-flex align-items-center justify-content-between mb-4">
        <a class="tf-brand tf-sidebar-brand text-white d-flex align-items-center mb-0" href="{{ route('public.home') }}" aria-label="{{ $platformSettings->company_name }} home">
            <span class="tf-brand-mark bg-blue">@if($hasPlatformLogo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($platformLogoPath) }}?v={{ $platformSettings->updated_at?->timestamp }}" class="tf-brand-logo" alt="">@else<i class="bi bi-boxes"></i>@endif</span><span class="tf-sidebar-text">{{ $platformSettings->company_name }}</span>
        </a>
        <button type="button" class="btn btn-sm btn-outline-light tf-sidebar-toggle tf-sidebar-toggle-inside" data-tf-sidebar-toggle aria-label="Toggle sidebar" title="Toggle sidebar"><i class="bi bi-list"></i></button>
        <button type="button" class="btn btn-sm btn-outline-light tf-sidebar-close" data-tf-sidebar-close aria-label="Close sidebar"><i class="bi bi-x-lg"></i></button>
    </div>
    <nav class="d-grid gap-1">
        <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" title="Dashboard"><i class="bi bi-speedometer2"></i><span class="tf-sidebar-text">Dashboard</span></a>
        <a href="{{ route('admin.companies.index') }}" class="{{ request()->routeIs('admin.companies.*') || request()->routeIs('admin.approvals.*') || request()->routeIs('admin.permissions.*') ? 'active' : '' }}" title="Companies"><i class="bi bi-buildings"></i><span class="tf-sidebar-text">Companies</span></a>
        <a href="{{ route('admin.subscriptions') }}" class="{{ request()->routeIs('admin.subscriptions', 'admin.subscriptions.*', 'admin.subscription-plans.*', 'admin.subscription-change-requests.*') ? 'active' : '' }}" title="Trial &amp; Access"><i class="bi bi-hourglass-split"></i><span class="tf-sidebar-text">Trial &amp; Access</span>@if($sidebarCounts['trial_access'] > 0)<span class="tf-sidebar-attention-badge {{ $sidebarCounts['trial_access_critical'] ? 'is-critical' : 'is-warning' }}" aria-label="{{ $badgeLabel($sidebarCounts['trial_access']) }} actionable trial and access items">{{ $badgeLabel($sidebarCounts['trial_access']) }}</span>@endif</a>
        <a href="{{ route('admin.payments') }}" class="{{ request()->routeIs('admin.payments', 'admin.payments.*') ? 'active' : '' }}" title="Payments &amp; Billing"><i class="bi bi-cash-stack"></i><span class="tf-sidebar-text">Payments &amp; Billing</span>@if($sidebarCounts['payments'] > 0)<span class="tf-sidebar-attention-badge is-warning" aria-label="{{ $badgeLabel($sidebarCounts['payments']) }} actionable billing items">{{ $badgeLabel($sidebarCounts['payments']) }}</span>@endif</a>
        <a href="{{ route('admin.support-tickets') }}" class="{{ request()->routeIs('admin.support-tickets', 'admin.support-tickets.*') ? 'active' : '' }}" title="Complaints & Support"><i class="bi bi-life-preserver"></i><span class="tf-sidebar-text">Support</span>@if($sidebarCounts['support'] > 0)<span class="tf-sidebar-attention-badge is-critical" aria-label="{{ $badgeLabel($sidebarCounts['support']) }} open support tickets">{{ $badgeLabel($sidebarCounts['support']) }}</span>@endif</a>
        <a href="{{ route('admin.newsletter-subscribers.index') }}" class="{{ request()->routeIs('admin.newsletter-subscribers.*') ? 'active' : '' }}" title="Newsletter Subscribers"><i class="bi bi-envelope-paper"></i><span class="tf-sidebar-text">Newsletter</span></a>
    </nav>
</div>
