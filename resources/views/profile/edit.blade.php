@extends('layouts.dashboard')
@section('page-title', 'Profile')
@section('page-subtitle', 'Update profile information and password')
@section('content')
@php($hasProfileImage = $user->profile_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_image))
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="row g-4">
    <div class="col-lg-7">
        <div class="tf-card p-4">
            <h2 class="h5">Profile Information</h2>
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="row g-3">@csrf @method('PUT')
                <div class="col-12 text-center">
                    @if($hasProfileImage)
                        <img src="{{ asset('storage/'.$user->profile_image) }}?v={{ $user->updated_at?->timestamp }}" class="profile-avatar mb-2" alt="{{ $user->name }}" data-tf-profile-preview>
                    @else
                        <span class="profile-avatar tf-avatar-empty mb-2" data-tf-profile-empty><i class="bi bi-person"></i></span>
                        <img src="" class="profile-avatar mb-2 d-none" alt="{{ $user->name }}" data-tf-profile-preview>
                    @endif
                    <div class="fw-bold">{{ $user->name }}</div>
                    <small class="tf-muted">{{ $user->role }}</small>
                </div>
                <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $user->name) }}"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone', $user->phone) }}"></div>
                <div class="col-md-6">
                    <label class="form-label">Upload New Image</label>
                    <input name="profile_image" type="file" class="form-control" accept="image/jpeg,image/png,image/webp" data-tf-profile-input>
                    <small class="tf-muted">JPG, PNG, or WebP. Max 2MB.</small>
                </div>
                @if($hasProfileImage)
                    <div class="col-12"><small class="tf-muted">Saved image: {{ $user->profile_image }}</small></div>
                    <div class="col-12"><label class="form-check"><input name="remove_image" value="1" type="checkbox" class="form-check-input" data-tf-profile-remove> Remove Image</label></div>
                @endif
                <div class="col-12"><button class="btn btn-tf-primary">Save Profile Changes</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-5"><div class="tf-card p-4"><h2 class="h5">Change Password</h2><form method="POST" action="{{ route('profile.password') }}" class="d-grid gap-3">@csrf @method('PUT')
        <div class="input-group"><input id="profileCurrentPassword" name="current_password" type="password" class="form-control" placeholder="Current password"><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#profileCurrentPassword" data-tf-password-icon="#profileCurrentPasswordIcon"><i id="profileCurrentPasswordIcon" class="bi bi-eye"></i></button></div>
        <div class="input-group"><input id="profileNewPassword" name="password" type="password" class="form-control" placeholder="New password"><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#profileNewPassword" data-tf-password-icon="#profileNewPasswordIcon"><i id="profileNewPasswordIcon" class="bi bi-eye"></i></button></div>
        <div class="input-group"><input id="profileConfirmPassword" name="password_confirmation" type="password" class="form-control" placeholder="Confirm password"><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#profileConfirmPassword" data-tf-password-icon="#profileConfirmPasswordIcon"><i id="profileConfirmPasswordIcon" class="bi bi-eye"></i></button></div>
        <button class="btn btn-outline-primary">Change Password</button></form></div></div>
</div>
@endsection
