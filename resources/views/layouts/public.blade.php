<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('components.theme-initializer')
    @php($publicTitle = str(trim($__env->yieldContent('title', $platformSettings->company_name)))->replace('TradeFlow', $platformSettings->company_name))
    <title>{{ $publicTitle }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="{{ asset('css/tradeflow.css') }}?v={{ filemtime(public_path('css/tradeflow.css')) }}" rel="stylesheet">
    @if(request()->routeIs('public.home'))
        @vite('resources/css/public-landing.css')
    @endif
    @vite('resources/css/theme-system.css')
    <script>if (window.location.hash) document.documentElement.classList.add('tf-initial-anchor');</script>
</head>
<body @class(['pp-landing-page' => request()->routeIs('public.home')])>
    @include('components.navbar')
    <main>@yield('content')</main>
    @if(session('registration_completed'))
        <div class="d-none" data-tf-registration-completed></div>
    @endif
    @php($footerAnchorBase = request()->routeIs('public.home') ? '' : route('public.home'))
    @php($footerPlatformLogoPath = preg_replace('#^(?:public/|storage/)#', '', ltrim((string) ($platformSettings->logo ?? ''), '/')))
    @php($hasFooterPlatformLogo = filled($footerPlatformLogoPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($footerPlatformLogoPath))
    <footer class="tf-footer pp-footer">
        <div class="container">
            <div class="pp-footer-top">
                <div class="pp-footer-about"><a href="{{ route('public.home') }}" class="tf-brand pp-brand" aria-label="{{ $platformSettings->company_name }} home">@if($hasFooterPlatformLogo)<span class="pp-brand-icon"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($footerPlatformLogoPath) }}?v={{ $platformSettings->updated_at?->timestamp }}" alt="" class="tf-brand-logo"></span>@else<span class="tf-brand-mark pp-brand-icon"><i class="bi bi-boxes"></i></span>@endif<span class="pp-brand-name">{{ $platformSettings->company_name }}</span></a><p>Modern wholesale management for teams that want a clearer way to grow.</p><div class="pp-socials"><a href="https://www.linkedin.com/" target="_blank" rel="noopener noreferrer" aria-label="Visit LinkedIn"><i class="bi bi-linkedin"></i></a><a href="https://www.facebook.com/" target="_blank" rel="noopener noreferrer" aria-label="Visit Facebook"><i class="bi bi-facebook"></i></a><a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" aria-label="Visit Instagram"><i class="bi bi-instagram"></i></a><a href="https://x.com/" target="_blank" rel="noopener noreferrer" aria-label="Visit X"><i class="bi bi-twitter-x"></i></a></div></div>
                <div class="pp-footer-column"><h3>Platform</h3><a href="{{ $footerAnchorBase }}#hero" data-tf-smooth>Overview</a><a href="{{ $footerAnchorBase }}#features" data-tf-smooth>Features</a><a href="{{ $footerAnchorBase }}#pricing" data-tf-smooth>Free Trial</a><a href="{{ $footerAnchorBase }}#how-it-works" data-tf-smooth>How It Works</a></div>
                <div class="pp-footer-column"><h3>Company</h3><a href="{{ route('public.contact') }}">Contact</a><a href="{{ route('privacy.security') }}">Privacy &amp; Security</a><a href="{{ route('public.contact') }}">Help Center</a></div>
                <div class="pp-footer-newsletter"><h3>Stay in the loop</h3><p>Tips and product news for smarter wholesale businesses.</p><form action="{{ route('newsletter-subscriptions.store') }}" method="POST" data-footer-newsletter><input type="hidden" name="_token" value="{{ csrf_token() }}"><label class="visually-hidden" for="newsletter">Email address</label><input id="newsletter" name="email" type="email" placeholder="Your email address" required autocomplete="email" aria-describedby="newsletter-feedback"><button type="submit" aria-label="Subscribe to newsletter"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></button></form><div id="newsletter-feedback" class="pp-newsletter-feedback {{ session('newsletter_feedback') ? 'is-success' : '' }}" aria-live="polite">{{ session('newsletter_feedback.message') }}</div><small>By subscribing, you agree to our <a href="{{ route('privacy.security') }}">privacy policy</a>.</small></div>
            </div>
            <div class="pp-footer-bottom"><span>&copy; {{ now()->year }} {{ $platformSettings->company_name }}. All rights reserved.</span><span>Made for businesses that keep trade moving.</span><div><a href="{{ route('privacy.security') }}">Privacy</a><a href="{{ route('privacy.security') }}">Terms</a></div></div>
        </div>
    </footer>
    <script>
        document.querySelector('[data-footer-newsletter]')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            if (!form.reportValidity()) return;

            const button = form.querySelector('button');
            const feedback = form.parentElement.querySelector('.pp-newsletter-feedback');
            const icon = button.querySelector('i');
            button.disabled = true;
            icon?.classList.replace('bi-arrow-up-right', 'bi-arrow-repeat');
            icon?.classList.add('tf-spin');
            feedback.textContent = '';
            feedback.className = 'pp-newsletter-feedback';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                    body: new FormData(form),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw payload;

                feedback.textContent = payload.message || 'Thanks for subscribing to Profit Point updates.';
                feedback.classList.add('is-success');
                form.reset();
            } catch (payload) {
                feedback.textContent = payload?.errors?.email?.[0] || payload?.message || 'Unable to subscribe right now. Please try again.';
                feedback.classList.add('is-error');
            } finally {
                button.disabled = false;
                icon?.classList.remove('tf-spin');
                icon?.classList.replace('bi-arrow-repeat', 'bi-arrow-up-right');
            }
        });
    </script>
    <script>
        document.querySelector('[data-footer-newsletter-legacy]')?.addEventListener('submit', (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            if (!form.reportValidity()) return;
            const button = form.querySelector('button');
            button.disabled = true;
            window.Swal?.fire({ icon: 'success', title: 'Thanks for subscribing.', text: 'We’ll keep you posted with useful product updates.', timer: 2500, showConfirmButton: false })
                ?? window.alert('Thanks for subscribing.');
            form.reset();
            window.setTimeout(() => { button.disabled = false; }, 300);
        });
    </script>
    @if(session('registration_completed'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const registrationDraftKeys = [
                    'tradeflow_business_registration_step',
                    'tradeflow_business_registration_data',
                    'tradeflow_business_registration_saved_at',
                ];

                registrationDraftKeys.forEach((key) => {
                    sessionStorage.removeItem(key);
                    localStorage.removeItem(key);
                });

                window.Swal?.fire({
                    icon: 'success',
                    title: 'Registration Completed',
                    text: 'Your workspace is ready. Your free trial is active.',
                    confirmButtonText: 'OK',
                    timer: 4000,
                    timerProgressBar: true,
                    allowOutsideClick: false,
                });
            });
        </script>
    @endif
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/intlTelInput.min.js"></script>
    <script src="{{ asset('js/phone-input.js') }}?v={{ filemtime(public_path('js/phone-input.js')) }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
    <script src="{{ asset('js/tradeflow.js') }}?v={{ filemtime(public_path('js/tradeflow.js')) }}"></script>
    @stack('scripts')
</body>
</html>
