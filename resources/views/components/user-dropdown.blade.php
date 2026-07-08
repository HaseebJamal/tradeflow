@php
    $user = auth()->user();
    $settingsRoute = route('profile.edit');
    $hasProfileImage = $user?->profile_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_image);

    if ($user?->role === 'super_admin' && \Illuminate\Support\Facades\Route::has('admin.settings')) {
        $settingsRoute = route('admin.settings');
    } elseif (in_array($user?->role, ['business_owner', 'manager', 'sales_staff', 'inventory_staff', 'accountant', 'delivery_staff'], true) && \Illuminate\Support\Facades\Route::has('business.settings')) {
        $settingsRoute = route('business.settings');
    }
@endphp
<div class="dropdown">
    <button class="btn btn-light border d-flex align-items-center gap-2 tf-user-dropdown-toggle" data-bs-toggle="dropdown">
        @if($hasProfileImage)
            <img src="{{ asset('storage/'.$user->profile_image) }}?v={{ $user->updated_at?->timestamp }}" class="navbar-avatar" alt="{{ $user->name }}">
        @else
            <span class="navbar-avatar tf-avatar-empty"><i class="bi bi-person"></i></span>
        @endif
        <span class="d-none d-md-inline">{{ $user?->name }}</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end shadow-sm tf-user-menu">
        <div class="px-3 py-3 text-center border-bottom">
            @if($hasProfileImage)
                <img src="{{ asset('storage/'.$user->profile_image) }}?v={{ $user->updated_at?->timestamp }}" class="tf-avatar tf-avatar-lg mb-2" alt="{{ $user->name }}">
            @else
                <span class="tf-avatar tf-avatar-lg tf-avatar-empty mb-2"><i class="bi bi-person"></i></span>
            @endif
            <div class="fw-bold">{{ $user?->name }}</div>
            <small class="tf-muted">{{ $user?->role }}</small>
        </div>
        <a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-gear me-2"></i>View Profile</a>
        <a class="dropdown-item" href="{{ $settingsRoute }}"><i class="bi bi-gear me-2"></i>Account Settings</a>
        <a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-key me-2"></i>Change Password</a>
        <div class="dropdown-divider"></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Logout</button></form>
    </div>
</div>
