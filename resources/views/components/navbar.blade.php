<nav class="navbar navbar-expand-lg fixed-top tf-public-nav">
    <div class="container">
        <a class="navbar-brand tf-brand d-flex align-items-center" href="{{ route('public.home') }}" aria-label="TradeFlow home">
            <span class="tf-brand-mark"><i class="bi bi-box-seam"></i></span><span>TradeFlow</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="{{ route('public.home') }}#features" data-tf-smooth>Features</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('public.home') }}#pricing" data-tf-smooth>Pricing</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('public.home') }}#faq" data-tf-smooth>FAQ</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('public.home') }}#about" data-tf-smooth>About</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('public.home') }}#contact" data-tf-smooth>Contact</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('privacy.security') }}">Privacy & Security</a></li>
                @auth
                    <li class="nav-item"><a class="btn tf-public-auth-button" href="{{ route('dashboard.redirect') }}"><i class="bi bi-speedometer2 me-1"></i>Go to Dashboard</a></li>
                @else
                    <li class="nav-item"><a class="btn tf-public-auth-button" href="{{ route('login') }}">Sign In</a></li>
                    <li class="nav-item"><a class="btn tf-public-auth-button tf-public-register-button" href="{{ route('register.business') }}"><i class="bi bi-building-add me-1"></i>Register Business</a></li>
                @endauth
            </ul>
        </div>
    </div>
</nav>
