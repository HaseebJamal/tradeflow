@extends('layouts.dashboard')

@section('page-title', 'Notifications')
@section('page-subtitle', 'Platform registrations, alerts, and Super Admin updates')

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
        <div class="btn-group">
            <a class="btn btn-sm {{ !$category ? 'btn-tf-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.notifications.index') }}">All</a>
            <a class="btn btn-sm {{ $category === 'unread' ? 'btn-tf-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.notifications.unread') }}">Unread</a>
            <a class="btn btn-sm {{ $category === 'registrations' ? 'btn-tf-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.notifications.registrations') }}">Company Registrations</a>
            <a class="btn btn-sm {{ $category === 'alerts' ? 'btn-tf-primary' : 'btn-outline-secondary' }}" href="{{ route('admin.notifications.alerts') }}">System Alerts</a>
        </div>
        <form method="POST" action="{{ route('admin.notifications.read-all') }}">
            @csrf
            @method('PATCH')
            <button class="btn btn-sm btn-outline-primary">Mark All Read</button>
        </form>
    </div>

    <div class="tf-card p-0 overflow-hidden">
        <div class="list-group list-group-flush">
            @forelse($notifications as $notification)
                @php($businessId = data_get($notification->data, 'business_id'))
                <div class="list-group-item p-3 {{ $notification->read_at ? '' : 'bg-light' }}">
                    <div class="d-flex gap-3 justify-content-between">
                        <div>
                            <strong>{{ data_get($notification->data, 'title', 'TradeFlow Notification') }}</strong>
                            <p class="mb-1">{{ data_get($notification->data, 'message') }}</p>
                            <small class="tf-muted"><x-date-time :value="$notification->created_at" /></small>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            @if($businessId)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.companies.show', $businessId) }}">Review</a>
                            @endif
                            @if(!$notification->read_at)
                                <form method="POST" action="{{ route('admin.notifications.read', $notification->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-secondary">Read</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-5 text-center tf-muted">No notifications in this category.</div>
            @endforelse
        </div>
    </div>

    <div class="mt-3">{{ $notifications->links() }}</div>
@endsection
