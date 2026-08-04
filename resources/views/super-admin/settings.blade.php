@extends('layouts.dashboard')
@section('page-title', 'Platform Settings')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="tf-card p-4">
    <form id="platformSettingsForm" method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="row g-3" data-platform-settings-form>
        @csrf
        @method('PUT')
        <div class="col-md-6"><label class="form-label">Platform Name</label><input name="company_name" class="form-control @error('company_name') is-invalid @enderror" value="{{ old('company_name', $settings->company_name) }}" required>@error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6">
            <label class="form-label" for="platformLogo">Platform Logo</label>
            <input id="platformLogo" name="logo" type="file" class="form-control @error('logo') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp" data-tf-image-upload>
            <div class="invalid-feedback" data-tf-image-error></div>
            @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <small class="tf-muted d-block mt-1">JPG, JPEG, PNG, or WebP. Max 2MB.</small>
            <small class="tf-muted d-block mt-1" data-tf-image-file-status>@if($settings->logo)A logo is currently configured.@endif</small>
            @if($settings->logo)
                <button form="platformLogoRestoreForm" type="submit" class="btn btn-sm btn-outline-secondary mt-2">Restore Default Logo</button>
            @else
                <small class="tf-muted d-block mt-1">The default TradeFlow logo is in use.</small>
            @endif
        </div>
        <div class="col-md-6"><label class="form-label">Support Email</label><input name="support_email" type="email" class="form-control @error('support_email') is-invalid @enderror" value="{{ old('support_email', $settings->support_email) }}">@error('support_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label">Support Phone</label><input name="support_phone" class="form-control @error('support_phone') is-invalid @enderror" value="{{ old('support_phone', $settings->support_phone) }}">@error('support_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </form>
    <form id="platformLogoRestoreForm" method="POST" action="{{ route('admin.settings.restore-default-logo') }}" class="d-none" data-tf-confirm-message="Your custom platform logo will be removed and the default TradeFlow logo will be used." data-tf-confirm-title="Restore default logo?" data-tf-confirm-button="Restore Logo" data-tf-confirm-color="#0d6efd" data-tf-confirm-saving-text="Restoring...">
        @csrf
        @method('PUT')
    </form>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button form="platformSettingsForm" type="submit" class="btn btn-tf-primary" data-platform-settings-submit>Save Settings</button>
        <form method="POST" action="{{ route('admin.settings.reset-defaults') }}" data-tf-confirm-message="This will reset the platform name, logo, support email, and support phone to their defaults." data-tf-confirm-title="Reset platform settings?" data-tf-confirm-button="Reset Settings" data-tf-confirm-color="#dc3545" data-tf-confirm-saving-text="Resetting...">
            @csrf
            @method('PUT')
            <button type="submit" class="btn btn-outline-danger">Reset to Default</button>
        </form>
    </div>
</div>

@endsection
