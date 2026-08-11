@extends('layouts.app')
@section('title', 'Forgot Password | TradeFlow')
@section('content')
@php($resendUntil = session('password_reset_last_sent_at') ? now()->setTimestamp((int) session('password_reset_last_sent_at'))->addSeconds(60)->toIso8601String() : null)
<section class="tf-auth-shell pp-auth-simple-shell">
    <div class="container"><div class="row justify-content-center"><div class="col-md-7 col-lg-5"><div class="tf-card tf-auth-card pp-auth-simple-card">
        <a href="{{ route('public.home') }}" class="tf-brand pp-auth-simple-brand d-flex align-items-center" aria-label="{{ $platformSettings->company_name }} home">@if($platformSettings->logo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($platformSettings->logo) }}" class="tf-brand-logo" alt="">@else<span class="tf-brand-mark"><i class="bi bi-box-seam"></i></span>@endif<span>{{ $platformSettings->company_name }}</span></a>
        <span class="pp-auth-eyebrow">ACCOUNT RECOVERY</span><h1>Forgot your password?</h1>
        <p class="pp-auth-simple-intro">Enter the email address for your {{ $platformSettings->company_name }} account and we will send a secure reset link.</p>
        @if(session('status'))<div class="alert alert-success pp-auth-feedback">{{ session('status') }}</div>@endif
        @if(session('password_reset_failure_message'))<div class="alert alert-danger pp-auth-feedback">{{ session('password_reset_failure_message') }}</div>@endif
        <form method="POST" action="{{ route('password.email') }}" class="pp-auth-simple-form" data-password-reset-request-form data-resend-until="{{ $resendUntil }}" data-tf-tab-order>
            @csrf
            <div class="pp-auth-simple-field">
                <label class="form-label" for="resetEmail">Email</label>
                <input id="resetEmail" name="email" type="email" class="form-control @error('email') is-invalid @enderror" placeholder="name@example.com" value="{{ old('email', session('password_reset_email')) }}" autocomplete="email" required autofocus>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @if(session('password_reset_last_sent_at'))
                <button class="btn pp-auth-simple-secondary" type="submit" name="resend" value="1" data-resend-reset-button disabled>Resend Reset Link <span data-resend-countdown></span></button>
            @else
                <button class="btn pp-auth-simple-primary" type="submit" data-send-reset-button><span data-send-reset-label>Send Reset Link</span><span class="spinner-border spinner-border-sm ms-2 d-none" data-send-reset-spinner aria-hidden="true"></span></button>
            @endif
            <a href="{{ route('login') }}" class="pp-auth-simple-link"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Sign In</a>
        </form>
    </div></div></div></div>
</section>
@endsection
