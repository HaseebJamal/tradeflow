@extends('layouts.dashboard')
@section('page-title', 'Platform Settings')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="tf-card p-4">
    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="row g-3" data-platform-settings-form>
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
        </div>
        <div class="col-md-6"><label class="form-label">Support Email</label><input name="support_email" type="email" class="form-control @error('support_email') is-invalid @enderror" value="{{ old('support_email', $settings->support_email) }}">@error('support_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label">Support Phone</label><input name="support_phone" class="form-control @error('support_phone') is-invalid @enderror" value="{{ old('support_phone', $settings->support_phone) }}">@error('support_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12"><button class="btn btn-tf-primary" data-platform-settings-submit>Save Settings</button></div>
    </form>
</div>

@endsection
