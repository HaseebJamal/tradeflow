@extends('layouts.dashboard')

@section('page-title', 'Workspace Access Paused')
@section('page-subtitle', 'Contact Profit Point to restore access')

@section('content')
    @php
        $isTrial = (bool) ($subscriptionState['is_trial'] ?? false);
        $expiry = $subscriptionState['end_date'] ?? null;
        $settings = \App\Models\PlatformSetting::current();
        $whatsAppDigits = preg_replace('/\D+/', '', (string) $settings->whatsapp_number);
        $message = rawurlencode($settings->whatsapp_message ?: 'Hello, I would like to restore access for '.$business->business_name.'.');
    @endphp
    <section class="tf-card p-4 p-md-5 text-center mx-auto" style="max-width:680px" aria-labelledby="subscription-expired-title">
        <div class="mb-3"><i class="bi bi-shield-lock fs-1 text-warning" aria-hidden="true"></i></div>
        <h2 class="h3 mb-2" id="subscription-expired-title">Your {{ $isTrial ? 'free trial' : 'subscription' }} has ended</h2>
        <p class="tf-muted mb-2">Your business data is safe, but workspace access is paused. Contact Profit Point to activate your subscription.</p>
        <p class="small tf-muted mb-4"><strong>{{ $business->business_name }}</strong>@if($expiry) · Ended {{ $expiry->format('d M, Y') }}@endif</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            @if($settings->whatsapp_is_active && $whatsAppDigits)
                <a class="btn btn-tf-primary" target="_blank" rel="noopener" href="https://wa.me/{{ $whatsAppDigits }}?text={{ $message }}">Contact on WhatsApp</a>
            @endif
            <a class="btn btn-outline-primary" href="{{ route('business.support') }}">Contact Support</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-outline-secondary">Logout</button></form>
        </div>
    </section>
@endsection
