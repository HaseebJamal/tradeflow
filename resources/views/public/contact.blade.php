@extends('layouts.public')
@section('title', 'Contact | TradeFlow')
@section('content')
<section class="tf-section" style="padding-top:140px">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5"><h1 class="fw-bold">Contact {{ $platformSettings->company_name }}</h1><p class="tf-muted">Send an inquiry directly to our support team.</p><div class="tf-card p-4">@if($platformSettings->support_email)<p><i class="bi bi-envelope text-blue me-2"></i>{{ $platformSettings->support_email }}</p>@endif @if($platformSettings->support_phone)<p><i class="bi bi-telephone text-blue me-2"></i>{{ $platformSettings->support_phone }}</p>@endif <p class="mb-0"><i class="bi bi-geo-alt text-blue me-2"></i>Lahore, Pakistan</p></div></div>
            <div class="col-lg-7"><div class="tf-card p-4">@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif @error('contact')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror<form method="POST" action="{{ route('contact.store') }}" data-public-contact-form>@csrf<div class="row g-3"><div class="col-md-6"><label class="form-label" for="contact-name">Name <span class="text-danger">*</span></label><input id="contact-name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Your name" required autocomplete="name">@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-6"><label class="form-label" for="contact-phone-visible">Phone <span class="text-danger">*</span></label><div data-tf-phone-field><input id="contact-phone-visible" data-tf-phone-visible type="tel" class="form-control @error('phone') is-invalid @enderror" placeholder="Phone number" required autocomplete="tel"><input data-tf-phone-value name="phone" type="hidden" value="{{ old('phone') }}"></div>@error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div><div class="col-12"><label class="form-label" for="contact-email">Email <span class="text-danger">*</span></label><input id="contact-email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="name@example.com" required autocomplete="email">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-12"><label class="form-label" for="contact-message">Message <span class="text-danger">*</span></label><textarea id="contact-message" name="message" class="form-control @error('message') is-invalid @enderror" rows="5" placeholder="How can we help?" required minlength="10" maxlength="2000">{{ old('message') }}</textarea>@error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-12"><button class="btn btn-tf-primary" type="submit" data-contact-submit>Send Message</button></div></div></form></div></div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.querySelector('[data-public-contact-form]')?.addEventListener('submit', (event) => {
    const form = event.currentTarget;
    if (!form.checkValidity()) return;
    const button = form.querySelector('[data-contact-submit]');
    if (!button || button.disabled) return;
    button.disabled = true;
    button.textContent = 'Sending…';
});
</script>
@endpush
