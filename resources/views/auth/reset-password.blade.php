@extends('layouts.app')
@section('title', 'Reset Password | TradeFlow')
@section('content')
<section class="tf-auth-shell">
    <div class="container"><div class="row justify-content-center"><div class="col-md-8 col-lg-5"><div class="tf-card tf-auth-card p-4 p-lg-5">
        <a href="{{ route('public.home') }}" class="tf-brand d-flex align-items-center mb-4"><span class="tf-brand-mark"><i class="bi bi-box-seam"></i></span>TradeFlow</a>
        <h1 class="h3 fw-bold">Reset Password</h1>
        <p class="tf-muted">Choose a new secure password for your account.</p>
        <form method="POST" action="{{ route('password.update') }}" class="d-grid gap-3" data-password-reset-update-form>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div><label class="form-label" for="resetPasswordEmail">Email</label><input id="resetPasswordEmail" name="email" type="email" class="form-control form-control-lg @error('email') is-invalid @enderror" value="{{ old('email', $email) }}" autocomplete="email" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div><label class="form-label" for="newResetPassword">New Password</label><div class="input-group input-group-lg"><input id="newResetPassword" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#newResetPassword" data-tf-password-icon="#newResetPasswordIcon" aria-label="Show new password"><i id="newResetPasswordIcon" class="bi bi-eye"></i></button></div><small class="tf-muted">At least 8 characters with uppercase, lowercase, number, and special character.</small>@error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <div><label class="form-label" for="newResetPasswordConfirmation">Confirm New Password</label><div class="input-group input-group-lg"><input id="newResetPasswordConfirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#newResetPasswordConfirmation" data-tf-password-icon="#newResetPasswordConfirmationIcon" aria-label="Show confirm new password"><i id="newResetPasswordConfirmationIcon" class="bi bi-eye"></i></button></div></div>
            <button class="btn btn-tf-primary btn-lg" type="submit" data-reset-password-submit><span data-reset-password-label>Reset Password</span><span class="spinner-border spinner-border-sm ms-2 d-none" data-reset-password-spinner aria-hidden="true"></span></button>
        </form>
    </div></div></div></div>
</section>
@endsection
