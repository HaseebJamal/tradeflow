<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'TradeFlow')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="{{ asset('css/tradeflow.css') }}?v={{ filemtime(public_path('css/tradeflow.css')) }}" rel="stylesheet">
</head>
<body>
    @include('components.navbar')
    <main>@yield('content')</main>
    @if(session('registration_completed'))
        <div class="d-none" data-tf-registration-completed></div>
    @endif
    <footer class="tf-footer bg-navy text-white py-5">
        <div class="container">
            <div class="row g-4 align-items-start">
                <div class="col-lg-4">
                    <div class="tf-brand d-flex align-items-center mb-3"><img src="{{ asset('images/tradeflow-logo.svg') }}" class="tf-brand-logo tf-brand-logo-on-dark" alt="TradeFlow"></div>
                    <p class="text-white-50 mb-0">Smart Wholesale Management Platform for manufacturers, distributors, wholesalers, retailers, accountants, and delivery teams.</p>
                </div>
                <div class="col-6 col-lg-2"><h3 class="h6">Platform</h3><a href="{{ route('public.home') }}#features" data-tf-smooth>Features</a><a href="{{ route('public.home') }}#pricing" data-tf-smooth>Pricing</a><a href="{{ route('public.home') }}#faq" data-tf-smooth>FAQ</a><a href="{{ route('public.home') }}#about" data-tf-smooth>About</a><a href="{{ route('public.home') }}#contact" data-tf-smooth>Contact</a></div>
                <div class="col-6 col-lg-2"><h3 class="h6">Business Tools</h3><a href="{{ route('public.home') }}#features" data-tf-smooth>Products</a><a href="{{ route('public.home') }}#features" data-tf-smooth>Inventory</a><a href="{{ route('public.home') }}#features" data-tf-smooth>Orders</a><a href="{{ route('public.home') }}#features" data-tf-smooth>Payments</a><a href="{{ route('public.home') }}#features" data-tf-smooth>Khata</a><a href="{{ route('public.home') }}#features" data-tf-smooth>Reports</a></div>
                <div class="col-lg-4"><h3 class="h6">Trust & Support</h3><a href="{{ route('privacy.security') }}">Privacy & Security</a><a href="{{ route('register.business') }}">Business Verification</a><a href="{{ route('public.home') }}#faq" data-tf-smooth>Manual Payments</a><a href="{{ route('public.contact') }}">Support</a></div>
            </div>
            <div class="border-top border-light border-opacity-10 mt-4 pt-4 text-white-50 small d-flex flex-column flex-md-row justify-content-between gap-2">
                <span>&copy; 2026 TradeFlow. All rights reserved.</span>
                <span>Built with Laravel, MySQL, Blade, Bootstrap, and manual payment records only.</span>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/intlTelInput.min.js"></script>
<script src="{{ asset('js/phone-input.js') }}?v={{ filemtime(public_path('js/phone-input.js')) }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/tradeflow.js') }}?v={{ filemtime(public_path('js/tradeflow.js')) }}"></script>
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
                    text: 'Your business registration has been submitted successfully. Please wait for Super Admin approval.',
                    confirmButtonText: 'OK',
                    timer: 4000,
                    timerProgressBar: true,
                    allowOutsideClick: false,
                });
            });
        </script>
    @endif
    @stack('scripts')
</body>
</html>

