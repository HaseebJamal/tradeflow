@php($landingAnchorBase = request()->routeIs('public.home') ? '' : route('public.home'))
@php($platformLogoPath = preg_replace('#^(?:public/|storage/)#', '', ltrim((string) ($platformSettings->logo ?? ''), '/')))
@php($hasPlatformLogo = filled($platformLogoPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($platformLogoPath))
<nav class="navbar navbar-expand-lg fixed-top tf-public-nav pp-nav" aria-label="Primary navigation">
    <div class="pp-nav-container">
        <a class="navbar-brand tf-brand pp-brand" href="{{ route('public.home') }}" aria-label="{{ $platformSettings->company_name }} home">
            @if($hasPlatformLogo)
                <span class="pp-brand-icon"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($platformLogoPath) }}?v={{ $platformSettings->updated_at?->timestamp }}" alt="" class="tf-brand-logo"></span>
            @else
                <span class="tf-brand-mark pp-brand-icon" aria-hidden="true"><i class="bi bi-boxes"></i></span>
            @endif
            <span class="pp-brand-name">{{ $platformSettings->company_name }}</span>
        </a>

        <button class="navbar-toggler pp-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse pp-nav-collapse" id="publicNav">
            <ul class="navbar-nav pp-nav-links" role="list">
                <li class="nav-item">
                    <a @class(['nav-link', 'is-active' => request()->routeIs('public.home')]) href="{{ $landingAnchorBase }}#platform" data-tf-smooth @if(request()->routeIs('public.home')) aria-current="page" @endif>Platform</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ $landingAnchorBase }}#features" data-tf-smooth>Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ $landingAnchorBase }}#how-it-works" data-tf-smooth>How It Works</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ $landingAnchorBase }}#pricing" data-tf-smooth>Free Trial</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ $landingAnchorBase }}#faq" data-tf-smooth>FAQ</a>
                </li>
            </ul>

            <div class="pp-nav-actions">
                @auth
                    <a class="btn pp-nav-dashboard" href="{{ route('dashboard.redirect') }}">Go to dashboard <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                @else
                    <a class="pp-sign-in" href="{{ route('login') }}">Sign In</a>
                    <a class="btn pp-nav-cta" href="{{ route('register.business') }}">Start Free <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                @endauth
            </div>
        </div>
    </div>
</nav>
