@php
    $user = auth()->user();
    $contextBusiness = request()->attributes->get('super_admin_business_context');
    $isBusinessContext = $user?->role === 'super_admin' && $contextBusiness;
    // Notifications use the shared dashboard header but are not nested under
    // /business. Keep an owner's business branding consistent on that page.
    $isBusinessOwnerNotificationPage = $user?->role === 'business_owner'
        && request()->routeIs('notifications.*');
    $businessWorkspace = $isBusinessContext
        ? $contextBusiness
        : ((request()->is('business/*') || request()->is('staff/*') || $isBusinessOwnerNotificationPage)
            ? ($user?->business ?? $user?->ownedBusiness)
            : null);
    // A business owner's dashboard keeps the company identity, while every
    // staff account must display the profile image saved on its own user row.
    // Super Admin business-context viewing remains branded as the selected
    // company so it never displays the Super Admin's personal image.
    $usesBusinessBranding = (bool) $businessWorkspace
        && ($isBusinessContext || $user?->role === 'business_owner');
    $displayName = $isBusinessContext ? $contextBusiness->business_name : $user?->name;
    // The shared business header represents the company workspace. Individual
    // user identity remains available inside the dropdown itself.
    $navbarDisplayName = $businessWorkspace?->business_name ?? $displayName;
    $displayRole = $isBusinessContext ? 'Business workspace' : $user?->role;
    // Keep the company logo for owner/context branding, but staff always see
    // their own uploaded profile image in the shared header and menu.
    $displayImage = $usesBusinessBranding ? $businessWorkspace->logo : $user?->profile_image;
    $displayUpdatedAt = $usesBusinessBranding ? $businessWorkspace->updated_at : $user?->updated_at;
    // Database values are stored as relative public-disk paths. Normalising
    // legacy public/storage prefixes prevents valid uploads from becoming a
    // broken header image on routes outside the business URL prefix.
    $displayImagePath = ltrim((string) $displayImage, '/');
    $displayImagePath = preg_replace('#^(?:public/|storage/)#', '', $displayImagePath);
    $settingsRoute = route('profile.edit');
    $hasProfileImage = filled($displayImagePath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($displayImagePath);
    // The application may run from a subdirectory (for example
    // /tradeflow/public). asset() keeps that base path, unlike a disk URL
    // beginning with /storage which would point at the web-server root.
    $displayImageUrl = $hasProfileImage
        ? asset('storage/'.$displayImagePath).'?v='.($displayUpdatedAt?->timestamp ?? '')
        : null;
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
            <img src="{{ $displayImageUrl }}" class="navbar-avatar" alt="{{ $navbarDisplayName }}">
        @else
            <span class="navbar-avatar tf-avatar-empty"><i class="bi {{ $usesBusinessBranding ? 'bi-building' : 'bi-person' }}"></i></span>
        @endif
        <span class="d-none d-md-inline">{{ $navbarDisplayName }}</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow-sm tf-user-menu">
        <div class="px-3 py-3 text-center border-bottom">
            @if($hasProfileImage)
                <img src="{{ $displayImageUrl }}" class="tf-avatar tf-avatar-lg mb-2" alt="{{ $displayName }}">
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
