@extends('layouts.app')
@section('title', 'Forgot Password | TradeFlow')
@section('content')
@php($resendUntil = session('password_reset_last_sent_at') ? now()->setTimestamp((int) session('password_reset_last_sent_at'))->addSeconds(60)->toIso8601String() : null)
<section class="tf-auth-shell">
    <div class="container"><div class="row justify-content-center"><div class="col-md-7 col-lg-5"><div class="tf-card tf-auth-card p-4 p-lg-5">
        <a href="{{ route('public.home') }}" class="tf-brand d-flex align-items-center mb-4"><span class="tf-brand-mark"><i class="bi bi-box-seam"></i></span>TradeFlow</a>
        <h1 class="h3 fw-bold">Forgot Password</h1>
        <p class="tf-muted">Enter the email address for your TradeFlow account and we will send a secure reset link.</p>
        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        <form method="POST" action="{{ route('password.email') }}" class="d-grid gap-3" data-password-reset-request-form data-resend-until="{{ $resendUntil }}" data-tf-tab-order>
            @csrf
            <div>
                <label class="form-label" for="resetEmail">Email</label>
                <input id="resetEmail" name="email" type="email" class="form-control form-control-lg @error('email') is-invalid @enderror" placeholder="name@example.com" value="{{ old('email', session('password_reset_email')) }}" autocomplete="email" required autofocus>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @if(session('password_reset_last_sent_at'))
                <button class="btn btn-outline-primary btn-lg" type="submit" name="resend" value="1" data-resend-reset-button disabled>Resend Reset Link <span data-resend-countdown></span></button>
            @else
                <button class="btn btn-tf-primary btn-lg" type="submit" data-send-reset-button><span data-send-reset-label>Send Reset Link</span><span class="spinner-border spinner-border-sm ms-2 d-none" data-send-reset-spinner aria-hidden="true"></span></button>
            @endif
            <a href="{{ route('login') }}" class="btn btn-outline-primary btn-lg">Back to Sign In</a>
        </form>
    </div></div></div></div>
</section>
@endsection
