@extends('layouts.dashboard')
@section('page-title', 'Notifications')
@section('page-subtitle', 'Account and TradeFlow updates')
@section('content')
<div class="tf-card p-0 overflow-hidden"><div class="list-group list-group-flush">@forelse($notifications as $notification)<div class="list-group-item p-3 {{ $notification->read_at ? '' : 'bg-light' }}"><strong>{{ data_get($notification->data, 'title', 'TradeFlow Notification') }}</strong><p class="mb-1">{{ data_get($notification->data, 'message') }}</p><small class="tf-muted"><x-date-time :value="$notification->created_at" /></small></div>@empty<div class="p-5 text-center tf-muted">No notifications yet.</div>@endforelse</div></div><div class="mt-3">{{ $notifications->links() }}</div>
@endsection
