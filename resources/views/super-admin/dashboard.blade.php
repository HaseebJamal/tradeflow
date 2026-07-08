@extends('layouts.dashboard')
@section('title', 'Super Admin Dashboard | TradeFlow')
@section('page-title', 'Super Admin Dashboard')
@section('page-subtitle', 'Platform overview')
@section('content')
<div class="d-flex flex-wrap gap-2 mb-4"><a href="{{ route('admin.businesses') }}" class="btn btn-tf-primary">Review Businesses</a><a href="{{ route('admin.subscriptions') }}" class="btn btn-outline-primary">Create Subscription Plan</a><a href="{{ route('admin.support-tickets') }}" class="btn btn-outline-primary">View Tickets</a><a href="{{ route('admin.notifications') }}" class="btn btn-outline-primary">Send Announcement</a></div>
<div class="row g-3">@foreach([
['Total Businesses',$totalBusinesses ?? 0,'bi-buildings','bg-blue','All registered'],['Pending Approvals',$pendingApprovals ?? 0,'bi-hourglass-split','bg-amber','Needs review'],['Approved Businesses',$activeBusinesses ?? 0,'bi-check2-circle','bg-green','Approved'],['Rejected Businesses',$rejectedBusinesses ?? 0,'bi-x-circle','bg-red','Rejected'],['Suspended Businesses',$suspendedBusinesses ?? 0,'bi-pause-circle','bg-amber','Suspended'],['Total Users',$totalUsers ?? 0,'bi-people','bg-navy','Platform users'],['Active Subscriptions',$activeSubscriptions ?? 0,'bi-credit-card','bg-green','Active'],['Expired Subscriptions',$expiredSubscriptions ?? 0,'bi-calendar-x','bg-red','Expired'],['Monthly Revenue','Rs '.number_format($monthlyRevenue ?? 0),'bi-graph-up','bg-blue','Manual subscriptions'],['Support Tickets',$ticketsCount ?? 0,'bi-life-preserver','bg-red','Open']
] as [$label,$value,$icon,$color,$note])<div class="col-md-6 col-xl-3">@include('components.card', compact('label','value','icon','color','note'))</div>@endforeach</div>
<div class="row g-4 mt-1">@foreach(['Business Growth','Monthly Revenue','New Registrations','Active vs Inactive Users'] as $chart)<div class="col-md-6"><div class="tf-card p-4"><h2 class="h5">{{ $chart }}</h2><div class="bg-light border rounded d-flex align-items-center justify-content-center" style="height:180px"><span class="tf-muted">Chart placeholder</span></div></div></div>@endforeach</div>
@endsection
