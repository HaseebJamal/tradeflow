@extends('layouts.dashboard')
@section('title', 'Super Admin Dashboard | TradeFlow')
@section('page-title', 'Super Admin Dashboard')
@section('page-subtitle', 'Profit Point platform overview')
@section('content')
@php
    $kpis = [
        ['Total Businesses', $totalBusinesses ?? 0, 'bi-buildings', 'bg-blue', 'All registered', 'admin.companies.index'],
        ['Pending Approvals', $pendingApprovals ?? 0, 'bi-hourglass-split', 'bg-amber', 'Needs review', 'admin.approvals.pending'],
        ['Approved Businesses', $activeBusinesses ?? 0, 'bi-check2-circle', 'bg-green', 'Approved', 'admin.companies.approved'],
        ['Rejected Businesses', $rejectedBusinesses ?? 0, 'bi-x-circle', 'bg-red', 'Rejected', 'admin.companies.rejected'],
        ['Suspended Businesses', $suspendedBusinesses ?? 0, 'bi-pause-circle', 'bg-red', 'Suspended', 'admin.companies.suspended'],
        ['Total Users', $totalUsers ?? 0, 'bi-people', 'bg-navy', 'All accounts', 'admin.users'],
        ['Open Tickets', $ticketsCount ?? 0, 'bi-life-preserver', 'bg-amber', 'Open', 'admin.support-tickets'],
        ['Security Alerts', $securityAlerts ?? 0, 'bi-shield-exclamation', 'bg-red', 'Last 7 days', 'admin.audit-logs'],
    ];
    $statusTotal = max(1, ($pendingApprovals ?? 0) + ($activeBusinesses ?? 0) + ($rejectedBusinesses ?? 0) + ($suspendedBusinesses ?? 0));
    $maxRegistration = max(1, collect($registrationTrend ?? [])->max('count'));
    $platformGreeting = now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening');
    $platformUserName = auth()->user()?->name ?: 'Super Admin';
@endphp

<div data-tf-super-admin-dashboard>
<section class="tf-dashboard-intro" data-tf-motion-item>
    <div class="tf-dashboard-intro-copy">
        <span class="tf-dashboard-eyebrow"><i class="bi bi-activity"></i> Platform overview</span>
        <h2>Good {{ $platformGreeting }}, {{ $platformUserName }}.</h2>
        <p>Monitor businesses, approvals, billing, support, and platform activity from one place.</p>
    </div>
</section>

<section class="dashboard-cards tf-dashboard-kpis" aria-label="Platform statistics">
    @foreach($kpis as [$label, $value, $icon, $color, $note, $route])
        <a href="{{ route($route) }}" class="tf-dashboard-kpi-link" aria-label="View {{ $label }}" data-tf-motion-item style="--tf-motion-order: {{ $loop->index }}">
            @include('components.card', compact('label', 'value', 'icon', 'color', 'note'))
        </a>
    @endforeach
</section>

<section class="row g-4 tf-dashboard-analytics" data-tf-motion-item>
    <div class="col-xl-8">
        <article class="tf-card tf-dashboard-panel h-100">
            <div class="tf-panel-heading"><div><span class="tf-dashboard-eyebrow">Growth</span><h2>Business registrations</h2><p>New businesses created over the last six months.</p></div><a href="{{ route('admin.companies.index') }}" class="tf-panel-link">View companies <i class="bi bi-arrow-up-right"></i></a></div>
            <div class="tf-registration-chart" role="img" aria-label="Business registrations over the last six months" data-tf-dashboard-chart>
                @forelse($registrationTrend ?? [] as $month)
                    <div class="tf-registration-bar-wrap"><span class="tf-registration-value">{{ $month['count'] }}</span><div class="tf-registration-bar" style="height:{{ max(8, ($month['count'] / $maxRegistration) * 100) }}%"></div><span class="tf-registration-label">{{ $month['label'] }}</span></div>
                @empty
                    <div class="tf-dashboard-empty"><i class="bi bi-bar-chart"></i><span>No registration data is available yet.</span></div>
                @endforelse
            </div>
        </article>
    </div>
    <div class="col-xl-4">
        <article class="tf-card tf-dashboard-panel h-100">
            <div class="tf-panel-heading"><div><span class="tf-dashboard-eyebrow">Portfolio</span><h2>Business status</h2><p>Current company approval distribution.</p></div></div>
            <div class="tf-status-distribution" data-tf-status-distribution>
                @foreach([['Approved', $activeBusinesses ?? 0, 'success'], ['Pending', $pendingApprovals ?? 0, 'warning'], ['Suspended', $suspendedBusinesses ?? 0, 'danger'], ['Rejected', $rejectedBusinesses ?? 0, 'slate']] as [$label, $value, $tone])
                    <div class="tf-status-row"><div><span class="tf-status-dot is-{{ $tone }}"></span><span>{{ $label }}</span><strong>{{ $value }}</strong></div><div class="tf-status-track"><i class="is-{{ $tone }}" style="width:{{ ($value / $statusTotal) * 100 }}%"></i></div></div>
                @endforeach
            </div>
        </article>
    </div>
</section>

<section class="tf-dashboard-section" data-tf-motion-item>
    <div class="tf-section-heading"><div><span class="tf-dashboard-eyebrow">Operations</span><h2>Platform operations</h2><p>Jump straight into the tools that keep Profit Point moving.</p></div></div>
    <div class="tf-operation-grid">
        @foreach([
            ['Companies', 'admin.companies.index', 'bi-buildings', 'Manage company profiles, status, and access.', 'blue'],
            ['Trial & Access', 'admin.subscriptions', 'bi-hourglass-split', 'Manage trials, business access, and paid access periods.', 'violet'],
            ['Payments & Billing', 'admin.payments', 'bi-cash-stack', 'Review agreed charges, payment references, receipts, and billing periods.', 'green'],
            ['Business Requests', 'admin.business-requests.index', 'bi-inboxes', 'Review business requests outside the primary navigation.', 'amber'],
            ['Complaints & Support', 'admin.support-tickets', 'bi-life-preserver', 'Respond to open support conversations.', 'amber'],
            ['Audit Logs', 'admin.audit-logs', 'bi-activity', 'Review security and platform events.', 'rose'],
            ['Business Reports', 'admin.business-reports', 'bi-graph-up', 'Review submitted business reports.', 'blue'],
            ['Platform Users', 'admin.users', 'bi-people', 'Manage platform accounts.', 'violet'],
        ] as [$label, $route, $icon, $description, $tone])
            <a href="{{ route($route) }}" class="tf-operation-card" data-tf-motion-item style="--tf-motion-order: {{ $loop->index }}"><span class="tf-operation-icon is-{{ $tone }}"><i class="bi {{ $icon }}"></i></span><span class="tf-operation-copy"><strong>{{ $label }}</strong><small>{{ $description }}</small></span><i class="bi bi-arrow-up-right tf-operation-arrow"></i></a>
        @endforeach
    </div>
</section>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/super-admin-dashboard-motion.js') }}?v={{ filemtime(public_path('js/super-admin-dashboard-motion.js')) }}" defer></script>
@endpush
