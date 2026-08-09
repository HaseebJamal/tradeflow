@php
    $user = auth()->user();
    $contextBusiness = request()->attributes->get('super_admin_business_context');
    $isBusinessContext = $user?->role === 'super_admin' && $contextBusiness;
    // Notifications use the shared dashboard header but are not nested under
    // /business. Keep an owner's business branding consistent on that page.
    $isBusinessOwnerNotificationPage = $user?->role === 'business_owner'
        && request()->routeIs('notifications.*');
    $authenticatedBusiness = $user?->business ?? $user?->ownedBusiness;
    $businessWorkspace = $isBusinessContext
        ? $contextBusiness
        : (($user?->role === 'business_owner' || request()->is('business/*') || request()->is('staff/*') || $isBusinessOwnerNotificationPage)
            ? $authenticatedBusiness
            : null);
    // Workspace controls remain available for business users, but the compact
    // profile control must always identify the authenticated person.
    $canBusinessSettings = (bool) $businessWorkspace
        && \Illuminate\Support\Facades\Route::has('business.settings')
        && app(\App\Services\CompanyPermissionService::class)->allowsUser($user, 'settings.view', $businessWorkspace);
    $usesBusinessBranding = (bool) $businessWorkspace
        && ($isBusinessContext || $user?->role === 'business_owner' || $canBusinessSettings);
    $displayName = $user?->name ?: 'User';
    $navbarDisplayName = $displayName;
    $avatarInitials = str($displayName)->trim()->explode(' ')->filter()->take(2)->map(fn ($part) => str($part)->substr(0, 1)->upper())->implode('');
    $displayRole = str($user?->role ?: 'user')->replace('_', ' ')->title();
    $displayImage = $user?->profile_image;
    $displayUpdatedAt = $user?->updated_at;
    // Database values are stored as relative public-disk paths. Normalising
    // legacy public/storage prefixes prevents valid uploads from becoming a
    // broken header image on routes outside the business URL prefix.
    $displayImagePath = ltrim((string) $displayImage, '/');
    $displayImagePath = preg_replace('#^(?:public/|storage/)#', '', $displayImagePath);
    $settingsRoute = route('profile.edit');
    $hasProfileImage = filled($displayImagePath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($displayImagePath);
    $displayImageUrl = $hasProfileImage
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($displayImagePath).'?v='.($displayUpdatedAt?->timestamp ?? '')
        : null;
    $hidePasswordOption = in_array($user?->role, ['super_admin', 'business_owner'], true);
    $showAccountSettings = false;
    $showBusinessSettings = $canBusinessSettings;

    if (!$isBusinessContext && $user?->role === 'super_admin' && \Illuminate\Support\Facades\Route::has('admin.settings')) {
        $settingsRoute = route('admin.settings');
        $showAccountSettings = true;
    }
@endphp
<div class="dropdown">
    <button class="btn btn-light border d-flex align-items-center gap-2 tf-user-dropdown-toggle" data-bs-toggle="dropdown">
        @if($hasProfileImage)
            <img src="{{ $displayImageUrl }}" class="navbar-avatar" alt="{{ $navbarDisplayName }}">
        @else
            <span class="navbar-avatar tf-avatar-empty" aria-hidden="true">{{ $avatarInitials ?: 'U' }}</span>
        @endif
        <span class="d-none d-md-inline">{{ $navbarDisplayName }}</span>
        <i class="bi bi-chevron-down small text-muted d-none d-md-inline" aria-hidden="true"></i>
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow-sm tf-user-menu">
        <div class="px-3 py-3 text-center border-bottom">
            @if($hasProfileImage)
                <img src="{{ $displayImageUrl }}" class="tf-avatar tf-avatar-lg mb-2" alt="{{ $displayName }}">
            @else
                <span class="tf-avatar tf-avatar-lg tf-avatar-empty mb-2">{{ $avatarInitials ?: 'U' }}</span>
            @endif
            <div class="fw-bold">{{ $displayName }}</div>
            <small class="tf-muted">{{ $displayRole }}</small>
            @if($businessWorkspace)
                <small class="tf-muted d-block">{{ $businessWorkspace->business_name }}</small>
            @endif
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
