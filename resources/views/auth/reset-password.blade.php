@extends('layouts.app')
@section('title', 'Reset Password | TradeFlow')
@section('content')
<section class="tf-auth-shell pp-auth-simple-shell">
    <div class="container"><div class="row justify-content-center"><div class="col-md-8 col-lg-5"><div class="tf-card tf-auth-card pp-auth-simple-card">
        <a href="{{ route('public.home') }}" class="tf-brand pp-auth-simple-brand d-flex align-items-center" aria-label="{{ $platformSettings->company_name }} home">@if($platformSettings->logo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($platformSettings->logo) }}" class="tf-brand-logo" alt="">@else<span class="tf-brand-mark"><i class="bi bi-box-seam"></i></span>@endif<span>{{ $platformSettings->company_name }}</span></a>
        <span class="pp-auth-eyebrow">ACCOUNT SECURITY</span><h1>Reset password</h1>
        <p class="pp-auth-simple-intro">Choose a new secure password for your account.</p>
        <form method="POST" action="{{ route('password.update') }}" class="pp-auth-simple-form" data-password-reset-update-form data-tf-tab-order>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="pp-auth-simple-field"><label class="form-label" for="resetPasswordEmail">Email</label><input id="resetPasswordEmail" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $email) }}" autocomplete="email" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="pp-auth-simple-field"><label class="form-label" for="newResetPassword">New password</label><div class="input-group"><input id="newResetPassword" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required><button class="btn tf-password-toggle" type="button" data-tf-password-toggle="#newResetPassword" data-tf-password-icon="#newResetPasswordIcon" aria-label="Show new password"><i id="newResetPasswordIcon" class="bi bi-eye" aria-hidden="true"></i></button></div><small class="pp-auth-password-help">Use 8+ characters with uppercase, number, and symbol.</small>@error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <div class="pp-auth-simple-field"><label class="form-label" for="newResetPasswordConfirmation">Confirm new password</label><div class="input-group"><input id="newResetPasswordConfirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" required><button class="btn tf-password-toggle" type="button" data-tf-password-toggle="#newResetPasswordConfirmation" data-tf-password-icon="#newResetPasswordConfirmationIcon" aria-label="Show confirm new password"><i id="newResetPasswordConfirmationIcon" class="bi bi-eye" aria-hidden="true"></i></button></div></div>
            <button class="btn pp-auth-simple-primary" type="submit" data-reset-password-submit><span data-reset-password-label>Reset Password</span><span class="spinner-border spinner-border-sm ms-2 d-none" data-reset-password-spinner aria-hidden="true"></span></button>
        </form>
    </div></div></div></div>
</section>
@endsection
