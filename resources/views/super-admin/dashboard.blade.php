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
    <div class="col-xl-6"><div class="tf-card p-4 h-100"><h2 class="h5">Operational Shortcuts</h2><div class="d-grid gap-3"><a href="{{ route('admin.companies.pending') }}" class="p-3 border rounded d-flex justify-content-between text-decoration-none"><span>Businesses awaiting approval</span><strong>{{ $pendingApprovals }}</strong></a><a href="{{ route('admin.support-tickets') }}" class="p-3 border rounded d-flex justify-content-between text-decoration-none"><span>Open complaints/tickets</span><strong>{{ $ticketsCount }}</strong></a><a href="{{ route('admin.subscriptions') }}" class="p-3 border rounded d-flex justify-content-between text-decoration-none"><span>Expired subscriptions</span><strong>{{ $expiredSubscriptions }}</strong></a><a href="{{ route('admin.audit-logs') }}" class="p-3 border rounded d-flex justify-content-between text-decoration-none"><span>Security alerts</span><strong>{{ $securityAlerts }}</strong></a></div></div></div>
</div>
@endsection
