@extends('layouts.dashboard')

@section('page-title', 'Subscription & Access')
@section('page-subtitle', 'Review your workspace access and billing history')

@section('content')
    @php
        $state = $subscriptionState ?? [];
        $status = $state['status'] ?? 'Not configured';
        $isTrial = (bool) ($state['is_trial'] ?? false);
        $start = $state['start_date'] ?? null;
        $end = $state['end_date'] ?? null;
        $days = $state['days_remaining'] ?? null;
        $settings = \App\Models\PlatformSetting::current();
        $whatsAppDigits = preg_replace('/\D+/', '', (string) $settings->whatsapp_number);
        $message = rawurlencode($settings->whatsapp_message ?: 'Hello, I would like to discuss access for '.$business->business_name.'.');
        $statusClass = in_array($status, ['Trial', 'Active', 'Expiring'], true) ? 'tf-badge-success' : 'tf-badge-danger';
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="h5 mb-0">Workspace access</h2>
        <a class="btn btn-outline-primary" href="{{ route('business.subscription.history') }}"><i class="bi bi-clock-history me-1"></i>Payment History</a>
    </div>

    <section class="tf-card p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div><h3 class="h4 mb-1">{{ $business->business_name }}</h3><span class="tf-badge {{ $statusClass }}">{{ $status }}</span></div>
            <div class="text-lg-end"><small class="tf-muted d-block">Access</small><strong>{{ ($state['can_access_business'] ?? false) ? 'Active' : 'Paused' }}</strong></div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Access type</small><strong>{{ $isTrial ? 'Free Trial' : 'Paid Subscription' }}</strong></div></div>
            <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">{{ $isTrial ? 'Trial Start' : 'Subscription Start' }}</small><strong>{{ $start?->format('d M, Y') ?? '-' }}</strong></div></div>
            <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">{{ $isTrial ? 'Trial End' : 'Subscription End' }}</small><strong>{{ $end?->format('d M, Y') ?? '-' }}</strong></div></div>
            <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Days remaining</small><strong>{{ $days === null ? '-' : ($days === 0 ? 'Ends today' : $days.' days') }}</strong></div></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if($settings->whatsapp_is_active && $whatsAppDigits)
                <a class="btn btn-tf-primary" target="_blank" rel="noopener" href="https://wa.me/{{ $whatsAppDigits }}?text={{ $message }}"><i class="bi bi-whatsapp me-1"></i>Contact Sales</a>
            @endif
            <a class="btn btn-outline-primary" href="{{ route('business.support') }}"><i class="bi bi-headset me-1"></i>Contact Support</a>
        </div>
    </section>
@endsection
