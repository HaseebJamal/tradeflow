<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'TradeFlow Dashboard')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/tradeflow.css') }}?v={{ filemtime(public_path('css/tradeflow.css')) }}" rel="stylesheet">
</head>
<body>
<div class="tf-dashboard-shell">
    <aside class="tf-sidebar sidebar" data-tf-sidebar>
        @include('components.sidebar')
    </aside>
    <div class="sidebar-overlay" data-tf-sidebar-overlay></div>
    <div class="tf-dashboard-main main-content">
        <div class="tf-dashboard-topbar px-3 px-lg-4 py-3 d-flex align-items-center justify-content-between sticky-top">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-secondary tf-sidebar-toggle d-lg-none" data-tf-sidebar-toggle aria-label="Open sidebar" title="Open sidebar"><i class="bi bi-list"></i></button>
                <div>
                    <h1 class="h4 mb-0">@yield('page-title', 'Dashboard')</h1>
                    <small class="tf-muted">@yield('page-subtitle', 'TradeFlow workspace')</small>
                </div>
            </div>
            @include('components.user-dropdown')
        </div>
        <main class="p-3 p-lg-4">@yield('content')</main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/tradeflow.js') }}?v={{ filemtime(public_path('js/tradeflow.js')) }}"></script>
</body>
</html>
