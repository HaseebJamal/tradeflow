@php
    $ctaClass = $classes ?? 'btn pp-btn-primary';
    $isFloating = (bool) ($floating ?? false);
    $ctaLabel = $isLoggedIn ? 'Go to Dashboard' : 'Start Free Trial';
@endphp

@if($isLoggedIn)
    <a href="{{ route('dashboard.redirect') }}" class="{{ $ctaClass }}" aria-label="{{ $ctaLabel }}" @if($isFloating) data-floating-trial-cta aria-hidden="true" tabindex="-1" @endif>
        @if($isFloating)<i class="bi bi-rocket-takeoff-fill" aria-hidden="true"></i><span class="pp-demo-float__label" aria-hidden="true">{{ $ctaLabel }}</span>@else{{ $ctaLabel }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i>@endif
    </a>
@elseif($isFloating)
    <button type="button" class="{{ $ctaClass }}" aria-label="{{ $ctaLabel }}" data-bs-toggle="modal" data-bs-target="#profitPointTrialModal" @if($isFloating) data-floating-trial-cta aria-hidden="true" tabindex="-1" @endif>
        @if($isFloating)<i class="bi bi-rocket-takeoff-fill" aria-hidden="true"></i><span class="pp-demo-float__label" aria-hidden="true">{{ $ctaLabel }}</span>@else{{ $ctaLabel }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i>@endif
    </button>
@else
    <a href="{{ route('register.business') }}" class="{{ $ctaClass }}" aria-label="{{ $ctaLabel }}">
        {{ $ctaLabel }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
    </a>
@endif
