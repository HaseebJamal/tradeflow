@extends('layouts.app')
@section('title', 'Sign In | TradeFlow')
@section('content')
<section class="pp-login-shell">
    <aside class="pp-login-story" aria-label="Profit Point overview">
        <div class="pp-login-shape pp-login-shape-one"></div><div class="pp-login-shape pp-login-shape-two"></div>
        <div class="pp-login-story-content">
            <a href="{{ route('public.home') }}" class="pp-auth-brand" aria-label="{{ $platformSettings->company_name }} home">@if($platformSettings->logo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($platformSettings->logo) }}" alt="">@else<span><i class="bi bi-boxes"></i></span>@endif<strong>{{ $platformSettings->company_name }}</strong></a>
            <div class="pp-login-story-copy"><span class="pp-auth-eyebrow">WHOLESALE, SIMPLIFIED</span><h1>Manage your wholesale business smarter.</h1><p>One clear workspace for every order, payment, product, and delivery.</p><ul><li><i class="bi bi-check2"></i> Real-time business visibility</li><li><i class="bi bi-check2"></i> Inventory and orders in sync</li><li><i class="bi bi-check2"></i> Control for your whole team</li></ul></div>
            <div class="pp-login-trust"><i class="bi bi-shield-check"></i><span><strong>Built for trusted trade</strong><small>Secure access for every team member</small></span></div>
        </div>
    </aside>
    <div class="pp-login-panel"><div class="pp-login-card">
        <a href="{{ route('public.home') }}" class="pp-auth-brand pp-auth-brand-mobile" aria-label="{{ $platformSettings->company_name }} home">@if($platformSettings->logo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($platformSettings->logo) }}" alt="">@else<span><i class="bi bi-boxes"></i></span>@endif<strong>{{ $platformSettings->company_name }}</strong></a>
        <header><span class="pp-auth-eyebrow">WELCOME BACK</span><h1>Sign in to your workspace</h1><p>Enter your details to continue to your business dashboard.</p></header>
        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if(session('access_ended'))<div class="alert alert-warning" role="alert">Your workspace access period has ended. You have been signed out.</div><script>document.addEventListener('DOMContentLoaded', function () { if (window.Swal) { Swal.fire({icon:'info', title:'You have been signed out', text:'Your workspace access period has ended.', confirmButtonText:'OK'}); } });</script>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login.store') }}" data-tf-tab-order autocomplete="off">@csrf
            <div class="pp-auth-field"><label for="loginEmail">Email address</label><div class="pp-auth-input"><i class="bi bi-envelope" aria-hidden="true"></i><input id="loginEmail" name="email" type="email" placeholder="you@company.com" value="{{ old('email') }}" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" required></div></div>
            <div class="pp-auth-field"><div class="d-flex justify-content-between"><label for="loginPassword">Password</label><a href="{{ route('password.request') }}">Forgot password?</a></div><div class="pp-auth-input"><i class="bi bi-lock" aria-hidden="true"></i><input id="loginPassword" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required data-tf-password-control="manual"><button class="pp-auth-password-toggle" type="button" aria-label="Show password" data-tf-password-toggle="#loginPassword" data-tf-password-icon="#loginPasswordIcon"><i id="loginPasswordIcon" class="bi bi-eye" aria-hidden="true"></i></button></div></div>
            <label class="pp-auth-remember"><input name="remember" type="checkbox"><span></span>Remember me for 30 days</label>
            <button class="btn pp-auth-submit" type="submit">Sign In <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
            <div class="pp-auth-divider"><span>New to {{ $platformSettings->company_name }}?</span></div>
            <a href="{{ route('register.business') }}" class="btn pp-auth-secondary">Register your business <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        </form>
        <p class="pp-auth-support">Need help getting started? <a href="{{ route('public.contact') }}">Contact support</a></p>
    </div></div>
</section>
@endsection
