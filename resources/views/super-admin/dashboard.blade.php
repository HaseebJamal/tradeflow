@extends('layouts.dashboard')
@section('title', 'Super Admin Dashboard | TradeFlow')
@section('page-title', 'Super Admin Dashboard')
@section('page-subtitle', 'TradeFlow platform overview')
@section('content')
<div class="dashboard-cards mb-4">
@foreach([
['Total Businesses',$totalBusinesses ?? 0,'bi-buildings','bg-blue','All registered'],
['Pending Approvals',$pendingApprovals ?? 0,'bi-hourglass-split','bg-amber','Needs review'],
['Approved Businesses',$activeBusinesses ?? 0,'bi-check2-circle','bg-green','Approved'],
['Rejected Businesses',$rejectedBusinesses ?? 0,'bi-x-circle','bg-red','Rejected'],
['Suspended Businesses',$suspendedBusinesses ?? 0,'bi-pause-circle','bg-red','Suspended'],
['Total Users',$totalUsers ?? 0,'bi-people','bg-navy','All accounts'],
['Subscription Revenue','Rs '.number_format($monthlyRevenue ?? 0),'bi-cash-stack','bg-blue','This month'],
['Active Subscriptions',$activeSubscriptions ?? 0,'bi-credit-card','bg-green','Active'],
['Expired Subscriptions',$expiredSubscriptions ?? 0,'bi-calendar-x','bg-red','Expired'],
['Open Tickets',$ticketsCount ?? 0,'bi-life-preserver','bg-red','Open'],
['Security Alerts',$securityAlerts ?? 0,'bi-shield-exclamation','bg-red','Recent'],
] as [$label,$value,$icon,$color,$note])
<div>@include('components.card', compact('label','value','icon','color','note'))</div>
@endforeach
</div>

<div class="row g-4">
    <div class="col-xl-6"><div class="tf-card p-4 h-100"><h2 class="h5">Business Health</h2>
        @foreach([['Approved',$activeBusinesses ?? 0,'bg-success'],['Pending',$pendingApprovals ?? 0,'bg-warning'],['Suspended',$suspendedBusinesses ?? 0,'bg-danger'],['Rejected',$rejectedBusinesses ?? 0,'bg-secondary']] as [$name,$count,$bar])
            @php($width = max(4, min(100, ($totalBusinesses ?? 1) ? ($count / max(1, $totalBusinesses)) * 100 : 0)))
            <div class="mb-3"><div class="d-flex justify-content-between"><span>{{ $name }}</span><strong>{{ $count }}</strong></div><div class="progress"><div class="progress-bar {{ $bar }}" style="width: {{ $width }}%"></div></div></div>
        @endforeach
    </div></div>
    <div class="col-xl-6"><div class="tf-card p-4 h-100"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Platform Operations</h2><small class="tf-muted">Managed from the dashboard</small></div><div class="row g-2">@foreach([
        ['Companies & Approvals', 'admin.companies.index', 'bi-buildings', $pendingApprovals.' pending'],
        ['Subscriptions', 'admin.subscriptions', 'bi-credit-card', $expiredSubscriptions.' expired'],
        ['Complaints & Support', 'admin.support-tickets', 'bi-life-preserver', $ticketsCount.' open'],
        ['Audit Logs', 'admin.audit-logs', 'bi-activity', $securityAlerts.' alerts'],
        ['Notifications', 'admin.notifications.index', 'bi-bell', auth()->user()->unreadNotifications()->count().' unread'],
        ['Payments', 'admin.payments', 'bi-cash-stack', 'Payment records'],
        ['Business Reports', 'admin.business-reports', 'bi-graph-up', 'Review reports'],
        ['Categories', 'admin.categories', 'bi-tags', 'Catalog categories'],
        ['Platform Users', 'admin.users', 'bi-people', 'Manage accounts'],
    ] as [$label, $route, $icon, $note])<div class="col-md-6"><a href="{{ route($route) }}" class="p-3 border rounded h-100 d-flex align-items-center gap-2 text-decoration-none"><i class="bi {{ $icon }} fs-5"></i><span><strong class="d-block">{{ $label }}</strong><small class="tf-muted">{{ $note }}</small></span></a></div>@endforeach</div></div></div>
</div>

@endsection
