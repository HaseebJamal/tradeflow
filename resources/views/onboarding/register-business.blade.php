@extends('layouts.public')

@section('title', 'Start Free Trial | Profit Point')

@section('content')
<section class="pp-registration-shell">
    <div class="container pp-registration-container">
        <div class="pp-registration-card">
            <div class="pp-registration-intro text-center">
                <a class="pp-registration-mark" href="{{ route('public.home') }}" aria-label="Profit Point home"><i class="bi bi-boxes"></i></a>
                <span class="pp-auth-eyebrow">START YOUR FREE TRIAL</span>
                <h1>Register your business</h1>
                <p>Create your workspace and start your free trial instantly.</p>
            </div>

            @if($errors->any())
                <div class="alert alert-danger pp-registration-alert" role="alert">Please correct the highlighted fields.</div>
            @endif

            <form method="POST" action="{{ route('register.business.store') }}" enctype="multipart/form-data" data-tf-register-form novalidate>
                @csrf

                <section class="pp-registration-section" aria-labelledby="account-information-title">
                    <div class="pp-registration-section-title"><span><i class="bi bi-person-circle"></i></span><div><h2 id="account-information-title">Account information</h2><p>These details are used to access your workspace.</p></div></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="business-owner-name">Owner name <span class="text-danger">*</span></label><input id="business-owner-name" name="name" type="text" autocomplete="name" maxlength="255" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required data-tf-name-only><div class="invalid-feedback">@error('name'){{ $message }}@enderror</div></div>
                        <div class="col-md-6"><label class="form-label" for="business-owner-phone">Phone <span class="text-danger">*</span></label><x-phone-input name="phone" id="business-owner-phone" :value="old('phone')" :required="true" :error="$errors->first('phone')" /></div>
                        <div class="col-md-6"><label class="form-label" for="business-owner-email">Work email <span class="text-danger">*</span></label><input id="business-owner-email" name="email" type="email" autocomplete="email" maxlength="255" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required><div class="invalid-feedback">@error('email'){{ $message }}@enderror</div></div>
                        <div class="col-md-6"><label class="form-label" for="ownerPassword">Password <span class="text-danger">*</span></label><div class="input-group"><input id="ownerPassword" name="password" type="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" required><button class="btn tf-password-toggle" type="button" aria-label="Show password" data-tf-password-toggle="#ownerPassword" data-tf-password-icon="#ownerPasswordIcon"><i id="ownerPasswordIcon" class="bi bi-eye"></i></button></div><div class="pp-registration-password-meta"><span data-register-password-strength>Use 8+ characters with uppercase, number, and symbol.</span></div><div class="invalid-feedback d-block">@error('password'){{ $message }}@enderror</div></div>
                        <div class="col-md-6"><label class="form-label" for="ownerPasswordConfirmation">Confirm password <span class="text-danger">*</span></label><div class="input-group"><input id="ownerPasswordConfirmation" name="password_confirmation" type="password" autocomplete="new-password" class="form-control" required><button class="btn tf-password-toggle" type="button" aria-label="Show confirm password" data-tf-password-toggle="#ownerPasswordConfirmation" data-tf-password-icon="#ownerPasswordConfirmationIcon"><i id="ownerPasswordConfirmationIcon" class="bi bi-eye"></i></button></div><div class="invalid-feedback" data-register-confirmation-error></div></div>
                    </div>
                </section>

                <section class="pp-registration-section" aria-labelledby="business-information-title">
                    <div class="pp-registration-section-title"><span><i class="bi bi-building"></i></span><div><h2 id="business-information-title">Business information</h2><p>A few details to personalise your workspace.</p></div></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="business-name">Business name <span class="text-danger">*</span></label><input id="business-name" name="business_name" type="text" autocomplete="organization" maxlength="255" class="form-control @error('business_name') is-invalid @enderror" value="{{ old('business_name') }}" required><div class="invalid-feedback">@error('business_name'){{ $message }}@enderror</div></div>
                        <div class="col-md-6"><label class="form-label" for="business-city">City</label><input id="business-city" name="city" type="text" autocomplete="address-level2" maxlength="100" class="form-control @error('city') is-invalid @enderror" value="{{ old('city') }}"><div class="invalid-feedback">@error('city'){{ $message }}@enderror</div></div>
                        <div class="col-md-6"><label class="form-label" for="business-address">Business address</label><input id="business-address" name="address" type="text" autocomplete="street-address" maxlength="1000" class="form-control @error('address') is-invalid @enderror" value="{{ old('address') }}" placeholder="Street, area, or landmark"><div class="invalid-feedback">@error('address'){{ $message }}@enderror</div></div>
                        <div class="col-md-6"><div class="pp-registration-trial-field"><label for="registration-trial-days">Free trial days</label><div class="input-group"><input id="registration-trial-days" class="form-control" type="text" value="{{ $trialDays }} days" readonly aria-readonly="true" tabindex="-1"><span class="input-group-text" aria-hidden="true"><i class="bi bi-lock-fill"></i></span></div><small>Configured by Profit Point.</small></div></div>
                        <div class="col-12"><label class="form-label" for="business-logo">Business logo <span class="tf-muted fw-normal">(optional)</span></label><label class="pp-registration-upload" for="business-logo"><i class="bi bi-cloud-arrow-up"></i><span><strong>Upload your logo</strong><small>JPG, PNG, or WebP · up to 2 MB</small></span><input id="business-logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="visually-hidden"><em data-register-logo-name>No file selected</em></label><div class="invalid-feedback d-block">@error('logo'){{ $message }}@enderror</div></div>
                    </div>
                </section>

                <button type="submit" class="btn pp-registration-submit w-100" data-tf-step-submit><span>Start My Free Trial</span><i class="bi bi-arrow-right"></i></button>
                <p class="pp-registration-cta-context">Your {{ $trialDays }}-day trial starts immediately after registration.</p>
                <p class="pp-registration-signin">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
            </form>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/register-business.js') }}?v={{ filemtime(public_path('js/register-business.js')) }}"></script>
@endpush
