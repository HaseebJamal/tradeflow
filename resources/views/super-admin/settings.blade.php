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
            <input id="platformLogo" name="logo" type="file" class="form-control @error('logo') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp" data-platform-logo-input>
            <div class="invalid-feedback" data-platform-logo-client-error></div>
            @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <small class="tf-muted d-block mt-1" data-platform-logo-status>@if($settings->logo)A logo is currently configured.@else Optional. JPG, JPEG, PNG, or WebP up to 2MB.@endif</small>
        </div>
        <div class="col-md-6"><label class="form-label">Support Email</label><input name="support_email" type="email" class="form-control @error('support_email') is-invalid @enderror" value="{{ old('support_email', $settings->support_email) }}">@error('support_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label">Support Phone</label><input name="support_phone" class="form-control @error('support_phone') is-invalid @enderror" value="{{ old('support_phone', $settings->support_phone) }}">@error('support_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12"><button class="btn btn-tf-primary" data-platform-settings-submit>Save Settings</button></div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-platform-settings-form]');
    const input = form?.querySelector('[data-platform-logo-input]');
    const clientError = form?.querySelector('[data-platform-logo-client-error]');
    const status = form?.querySelector('[data-platform-logo-status]');
    const submit = form?.querySelector('[data-platform-settings-submit]');
    if (!form || !input || !clientError || !status || !submit) return;

    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    const validateLogo = () => {
        const file = input.files?.[0];
        let message = '';
        if (file && !allowed.includes(file.type)) message = 'Please upload a JPG, JPEG, PNG, or WebP image.';
        if (file && file.size > 2 * 1024 * 1024) message = 'Platform logo must not exceed 2MB.';
        input.classList.toggle('is-invalid', Boolean(message));
        clientError.textContent = message;
        submit.disabled = Boolean(message);
        if (message) {
            status.textContent = '';
            input.value = '';
        } else if (file) {
            status.textContent = `${file.name} selected.`;
        }
        return !message;
    };

    input.addEventListener('change', validateLogo);
    form.addEventListener('submit', event => { if (!validateLogo()) event.preventDefault(); });
});
</script>
@endpush
@endsection
