@php
    $user = auth()->user();
    $contextBusiness = request()->attributes->get('super_admin_business_context');
    $isBusinessContext = $user?->role === 'super_admin' && $contextBusiness;
    $businessWorkspace = $isBusinessContext
        ? $contextBusiness
        : (request()->is('business/*') ? ($user?->business ?? $user?->ownedBusiness) : null);
    $usesBusinessBranding = (bool) $businessWorkspace;
    $displayName = $isBusinessContext ? $contextBusiness->business_name : $user?->name;
    $displayRole = $isBusinessContext ? 'Business workspace' : $user?->role;
    // Business pages always use the company logo; owner profile images must not
    // replace the brand identity in a business dashboard.
    $displayImage = $usesBusinessBranding ? $businessWorkspace->logo : $user?->profile_image;
    $displayUpdatedAt = $usesBusinessBranding ? $businessWorkspace->updated_at : $user?->updated_at;
    $settingsRoute = route('profile.edit');
    $hasProfileImage = $displayImage && \Illuminate\Support\Facades\Storage::disk('public')->exists($displayImage);
    $hidePasswordOption = in_array($user?->role, ['super_admin', 'business_owner'], true);
    $showAccountSettings = false;
    $showBusinessSettings = $usesBusinessBranding
        && \Illuminate\Support\Facades\Route::has('business.settings')
        && app(\App\Services\CompanyPermissionService::class)->allowsUser($user, 'settings.view');

    if (!$isBusinessContext && $user?->role === 'super_admin' && \Illuminate\Support\Facades\Route::has('admin.settings')) {
        $settingsRoute = route('admin.settings');
        $showAccountSettings = true;
    }
@endphp
<div class="dropdown">
    <button class="btn btn-light border d-flex align-items-center gap-2 tf-user-dropdown-toggle" data-bs-toggle="dropdown">
        @if($hasProfileImage)
            <img src="{{ asset('storage/'.$displayImage) }}?v={{ $displayUpdatedAt?->timestamp }}" class="navbar-avatar" alt="{{ $displayName }}">
        @else
            <span class="navbar-avatar tf-avatar-empty"><i class="bi {{ $usesBusinessBranding ? 'bi-building' : 'bi-person' }}"></i></span>
        @endif
        <span class="d-none d-md-inline">{{ $displayName }}</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow-sm tf-user-menu">
        <div class="px-3 py-3 text-center border-bottom">
            @if($hasProfileImage)
                <img src="{{ asset('storage/'.$displayImage) }}?v={{ $displayUpdatedAt?->timestamp }}" class="tf-avatar tf-avatar-lg mb-2" alt="{{ $displayName }}">
            @else
                <span class="tf-avatar tf-avatar-lg tf-avatar-empty mb-2"><i class="bi {{ $usesBusinessBranding ? 'bi-building' : 'bi-person' }}"></i></span>
            @endif
            <div class="fw-bold">{{ $displayName }}</div>
            <small class="tf-muted">{{ $displayRole }}</small>
        </div>
        @if($user?->role === 'super_admin' && !$isBusinessContext)
            <h6 class="dropdown-header">Settings</h6>
            <a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear me-2"></i>My Profile</a>
            <a class="dropdown-item" href="{{ route('admin.settings') }}"><i class="bi bi-gear me-2"></i>Platform Settings</a>
            <a class="dropdown-item" href="{{ route('admin.profile.security') }}"><i class="bi bi-shield-lock me-2"></i>Security</a>
        @elseif($usesBusinessBranding)
            <h6 class="dropdown-header">Profile &amp; Settings</h6>
            <a class="dropdown-item" href="{{ $isBusinessContext ? route('business.context.profile') : route('profile.edit') }}"><i class="bi bi-person-gear me-2"></i>Profile</a>
            @if($showBusinessSettings)<a class="dropdown-item" href="{{ route('business.settings') }}"><i class="bi bi-gear me-2"></i>Business Settings</a>@endif
        @else
            <a class="dropdown-item" href="{{ $isBusinessContext ? route('business.context.profile') : route('profile.edit') }}"><i class="bi bi-person-gear me-2"></i>View Profile</a>
            @if($showAccountSettings)<a class="dropdown-item" href="{{ $settingsRoute }}"><i class="bi bi-gear me-2"></i>Account Settings</a>@endif
        @endif
        @unless($hidePasswordOption)
            <a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-key me-2"></i>Change Password</a>
        @endunless
        <div class="dropdown-divider"></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Logout</button></form>
    </div>
</div>
