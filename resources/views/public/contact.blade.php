@extends('layouts.public')

@section('title', 'Contact | '.$platformSettings->company_name)

@section('content')
    @php
        $inquiryTypes = ['Sales Inquiry', 'Product Demo', 'Support', 'Partnership', 'Other'];
        $whatsAppUrl = app(\App\Services\PlatformSettingsService::class)->whatsAppUrl($platformSettings->whatsapp_message);
        $supportEmail = trim((string) $platformSettings->support_email);
        $supportPhone = trim((string) $platformSettings->support_phone);
    @endphp

    <section class="pp-contact-page">
        <div class="container">
            <div class="pp-contact-shell">
                <header class="pp-contact-hero">
                    <span class="pp-contact-eyebrow">GET IN TOUCH</span>
                    <h1>Let&rsquo;s talk about your business.</h1>
                </header>

            <div class="row g-4 g-xl-5 align-items-stretch pp-contact-layout">
                <div class="col-lg-5 col-xl-5">
                    <aside class="pp-contact-intro">
                        <div class="pp-contact-info-heading">
                            <h2>Contact information</h2>
                            <p>Reach our team through the channel that works best for you.</p>
                        </div>

                        <div class="pp-contact-details" aria-label="Contact details">
                            <div class="pp-contact-detail">
                                <span class="pp-contact-detail__icon"><i class="bi bi-envelope" aria-hidden="true"></i></span>
                                <div>
                                    <small>Support Email</small>
                                    @if(filter_var($supportEmail, FILTER_VALIDATE_EMAIL))
                                        <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                                    @else
                                        <span>Support email is being configured.</span>
                                    @endif
                                </div>
                            </div>

                            <div class="pp-contact-detail">
                                <span class="pp-contact-detail__icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
                                <div><small>Location</small><span>Lahore, Pakistan</span></div>
                            </div>

                            @if(filled($supportPhone))
                                <div class="pp-contact-detail">
                                    <span class="pp-contact-detail__icon"><i class="bi bi-telephone" aria-hidden="true"></i></span>
                                    <div><small>Support Phone</small><a href="tel:{{ preg_replace('/[^+\d]/', '', $supportPhone) }}">{{ $supportPhone }}</a></div>
                                </div>
                            @endif
                        </div>

                        @if($whatsAppUrl)
                            <a class="pp-contact-whatsapp" href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer">
                                <span><i class="bi bi-whatsapp" aria-hidden="true"></i></span>
                                <div><strong>Prefer WhatsApp?</strong><small>Start a conversation with our team.</small></div>
                                <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                            </a>
                        @endif
                    </aside>
                </div>

                <div class="col-lg-7 col-xl-7">
                    <article class="pp-contact-card">
                        <header class="pp-contact-card__header">
                            <div>
                                <h2>Send us a message</h2>
                                <p>We will make sure it reaches the right person.</p>
                            </div>
                            <span class="pp-contact-card__mark"><i class="bi bi-send" aria-hidden="true"></i></span>
                        </header>

                        @if(session('success'))
                            <div class="pp-contact-feedback is-success" role="status"><i class="bi bi-check-circle-fill" aria-hidden="true"></i>{{ session('success') }}</div>
                        @endif
                        @error('contact')
                            <div class="pp-contact-feedback is-error" role="alert"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>{{ $message }}</div>
                        @enderror

                        <form method="POST" action="{{ route('contact.store') }}" data-public-contact-form>
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="contact-name">Name <span aria-hidden="true">*</span></label>
                                    <input id="contact-name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Your name" required autocomplete="name">
                                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="contact-phone-visible">Phone <span aria-hidden="true">*</span></label>
                                    <div data-tf-phone-field>
                                        <input id="contact-phone-visible" data-tf-phone-visible type="tel" class="form-control @error('phone') is-invalid @enderror" placeholder="Phone number" required autocomplete="tel">
                                        <input data-tf-phone-value name="phone" type="hidden" value="{{ old('phone') }}">
                                    </div>
                                    @error('phone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="contact-email">Email <span aria-hidden="true">*</span></label>
                                    <input id="contact-email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="name@example.com" required autocomplete="email">
                                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="contact-inquiry-type">Inquiry Type</label>
                                    <select id="contact-inquiry-type" name="inquiry_type" class="form-select @error('inquiry_type') is-invalid @enderror">
                                        <option value="">Select an inquiry type</option>
                                        @foreach($inquiryTypes as $type)<option value="{{ $type }}" @selected(old('inquiry_type') === $type)>{{ $type }}</option>@endforeach
                                    </select>
                                    @error('inquiry_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="contact-message">Message <span aria-hidden="true">*</span></label>
                                    <textarea id="contact-message" name="message" class="form-control @error('message') is-invalid @enderror" rows="5" placeholder="How can we help?" required minlength="10" maxlength="2000">{{ old('message') }}</textarea>
                                    @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 pp-contact-submit-row">
                                    <button class="btn pp-contact-submit" type="submit" data-contact-submit>Send Message <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
                                    <p>We'll get back to you as soon as possible.</p>
                                </div>
                            </div>
                        </form>
                    </article>
                </div>
            </div>
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
            button.innerHTML = 'Sending... <i class="bi bi-arrow-repeat tf-spin" aria-hidden="true"></i>';
        });
    </script>
@endpush
