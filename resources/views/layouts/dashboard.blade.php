<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('components.theme-initializer')
    @php
        /*
         * Blade sections can arrive here either as plain text or as text that
         * has already been entity-encoded by a parent/partial.  Decode the
         * presentation value until it is stable, then let Blade escape it once
         * when it is output. This keeps browser titles such as "Trial & Access"
         * readable without ever rendering unescaped HTML.
         */
        $normalisePresentationText = static function (mixed $value): string {
            $text = trim((string) $value);

            for ($attempt = 0; $attempt < 3; $attempt++) {
                $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if ($decoded === $text) {
                    break;
                }

                $text = $decoded;
            }

            return $text;
        };

        $dashboardTitle = str($normalisePresentationText($__env->yieldContent('title', $platformSettings->company_name.' Dashboard')))
            ->replace('TradeFlow', $platformSettings->company_name);
        $dashboardSubtitle = str($normalisePresentationText($__env->yieldContent('page-subtitle', $platformSettings->company_name.' workspace')))
            ->replace('TradeFlow', $platformSettings->company_name);
    @endphp
    <title>{{ $dashboardTitle }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="{{ asset('css/tradeflow.css') }}?v={{ filemtime(public_path('css/tradeflow.css')) }}" rel="stylesheet">
    @vite('resources/css/theme-system.css')
</head>
<body data-tf-sidebar-preference-key="{{ auth()->check() ? 'profitpoint.sidebar.'.auth()->id().'.collapsed' : '' }}">
@auth
<script>
/* Apply an existing account-specific desktop choice before the dashboard shell
   is parsed. A missing key deliberately leaves the sidebar expanded. */
(() => {
    const body = document.body;
    const key = body?.dataset.tfSidebarPreferenceKey;
    if (!key || !window.matchMedia('(min-width: 1200px)').matches) return;

    try {
        if (window.localStorage.getItem(key) === '1') body.classList.add('sidebar-collapsed');
    } catch (_) {
        // Storage may be unavailable; expanded is the safe first-visit state.
    }
})();
</script>
@endauth
<div class="tf-dashboard-shell dashboard-wrapper">
    <aside class="tf-sidebar sidebar" data-tf-sidebar>
        @include('components.sidebar')
    </aside>
    <div class="sidebar-overlay" data-tf-sidebar-overlay></div>
    <div class="tf-dashboard-main main-content d-flex flex-column">
        <div class="tf-dashboard-topbar dashboard-header px-3 px-lg-4 py-3 sticky-top">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <button class="btn btn-outline-secondary tf-sidebar-toggle tf-sidebar-toggle--topbar" data-tf-sidebar-toggle aria-label="Open sidebar" title="Open sidebar"><i class="bi bi-list"></i></button>
                <div class="min-w-0">
                    <h1 class="h4 mb-0">@yield('page-title', 'Dashboard')</h1>
                    <small class="tf-muted">{{ $dashboardSubtitle }}</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 tf-topbar-actions">
                @include('components.theme-toggle')
                @auth
                    @include('components.notification-dropdown')
                @endauth
                @include('components.user-dropdown')
            </div>
        </div>
        @if(request()->is('business/*') && auth()->check() && auth()->user()->role === 'super_admin' && session('super_admin_business_context_id'))
            <div class="alert alert-warning tf-company-preview-banner rounded-0 border-0 mb-0 d-flex flex-wrap align-items-center justify-content-between gap-3 px-3 px-lg-4 py-3" role="alert" data-tf-persistent-alert>
                <div><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>You are currently viewing &quot;{{ session('super_admin_business_context_name') }}&quot; as Super Admin.</strong><span class="d-block small mt-1">Any changes made here will affect this company's live data.</span></div>
                <form method="POST" action="{{ route('admin.company-context.return') }}">@csrf<button class="btn btn-sm btn-dark text-nowrap"><i class="bi bi-arrow-return-left me-1"></i>Return to Super Admin Dashboard</button></form>
            </div>
        @endif
        <main class="dashboard-page flex-grow-1">
            @if($welcomeBackName = session('welcome_back_name'))
                <div class="tf-welcome-back" role="status" aria-live="polite">
                    <span class="tf-welcome-back-icon" aria-hidden="true"><i class="bi bi-stars"></i></span>
                    <div><strong>Welcome back, {{ $welcomeBackName }}.</strong><span>Your workspace is ready for you.</span></div>
                </div>
            @endif
            @if($returnAlert = session('tradeflow_return_alert'))
                <div class="alert alert-success visually-hidden" data-tf-alert-title="{{ data_get($returnAlert, 'title', 'Completed') }}">
                    {{ data_get($returnAlert, 'message') }}
                </div>
            @endif
            @yield('content')
        </main>
        @if(
            request()->is('business/*')
            && ! request()->routeIs('business.pos.index')
            && (request()->attributes->get('super_admin_business_context') ?? auth()->user()?->business)
        )
            <x-business-application-footer :business="(request()->attributes->get('super_admin_business_context') ?? auth()->user()?->business)" />
        @endif
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/intlTelInput.min.js"></script>
<script src="{{ asset('js/phone-input.js') }}?v={{ filemtime(public_path('js/phone-input.js')) }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
<script src="{{ asset('js/tradeflow.js') }}?v={{ filemtime(public_path('js/tradeflow.js')) }}"></script>
@stack('scripts')
@auth
<script>
setInterval(() => {
    fetch('{{ route('activity.heartbeat') }}', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    }).then(async (response) => {
        if (response.status !== 401) return;
        const payload = await response.json().catch(() => ({}));
        window.Swal?.fire({ icon: 'info', title: 'Trial access ended', text: payload.message || 'Your workspace access has ended.', allowOutsideClick: false, confirmButtonText: 'Sign in' })
            .then(() => window.location.assign(payload.redirect || '{{ route('login') }}'));
    }).catch(() => {});
}, 60000);
</script>
@if((auth()->user()->role === 'super_admin' && request()->is('admin/*')) || request()->is('business/*'))
<script>
document.addEventListener('DOMContentLoaded', () => {
    const fields = [...document.querySelectorAll('.dashboard-page form input:not([type="hidden"]):not([disabled]), .dashboard-page form select:not([disabled]), .dashboard-page form textarea:not([disabled])')]
        .filter((field) => field.offsetParent !== null);
    fields.forEach((field, index) => field.tabIndex = index + 1);
    const skipInitialFormFocus = @json(trim($__env->yieldContent('disable-dashboard-autofocus')) === 'true');
    if (!skipInitialFormFocus && (!document.activeElement || document.activeElement === document.body)) fields[0]?.focus();
});
</script>
@endif
@endauth
<script>
(() => {
    const key = 'tradeflow_super_admin_sidebar_open';
    const toggles = [...document.querySelectorAll('[data-super-sidebar-toggle]')];
    if (!toggles.length) return;

    const setOpen = (id) => {
        toggles.forEach((button) => {
            const open = button.dataset.superSidebarToggle === id;
            button.classList.toggle('is-open', open);
            button.setAttribute('aria-expanded', String(open));
            document.getElementById(button.dataset.superSidebarToggle)?.classList.toggle('is-open', open);
        });

        if (id) localStorage.setItem(key, id);
        else localStorage.removeItem(key);
    };

    const active = toggles.find((button) => button.classList.contains('is-open'))?.dataset.superSidebarToggle;
    if (active) setOpen(active);
    else {
        const stored = localStorage.getItem(key);
        if (stored && document.getElementById(stored)) setOpen(stored);
    }

    toggles.forEach((button) => button.addEventListener('click', () => {
        setOpen(button.classList.contains('is-open') ? '' : button.dataset.superSidebarToggle);
    }));
})();
</script>
</body>
</html>
