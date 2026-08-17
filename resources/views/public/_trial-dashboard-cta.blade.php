@php
    $ctaClass = $classes ?? 'btn pp-btn-primary';
    $isFloating = (bool) ($floating ?? false);
    $ctaLabel = $isLoggedIn ? 'Go to Dashboard' : 'Start Free Trial';
@endphp

@if($isLoggedIn)
    <a href="{{ route('dashboard.redirect') }}" class="{{ $ctaClass }}" aria-label="{{ $ctaLabel }}" @if($isFloating) data-tooltip="{{ $ctaLabel }}" @endif>
        @if($isFloating)<i class="bi bi-rocket-takeoff-fill" aria-hidden="true"></i><span class="pp-demo-float__label" aria-hidden="true">Dashboard</span><span class="visually-hidden">{{ $ctaLabel }}</span>@else{{ $ctaLabel }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i>@endif
    </a>
@else
    <button type="button" class="{{ $ctaClass }}" aria-label="{{ $ctaLabel }}" data-bs-toggle="modal" data-bs-target="#profitPointTrialModal" @if($isFloating) data-tooltip="{{ $ctaLabel }}" @endif>
        @if($isFloating)<i class="bi bi-rocket-takeoff-fill" aria-hidden="true"></i><span class="pp-demo-float__label" aria-hidden="true">Free Trial</span><span class="visually-hidden">{{ $ctaLabel }}</span>@else{{ $ctaLabel }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i>@endif
    </button>
@endif
