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
        <div class="col-md-6"><label class="form-label" for="platformSupportPhone">Support Phone</label><x-phone-input id="platformSupportPhone" name="support_phone" :value="old('support_phone', $supportPhone)" :error="$errors->first('support_phone')" placeholder="Support phone number" helper-text="Choose the country, then enter the phone number." /></div>
        <div class="col-md-6"><label class="form-label" for="platformTrialDays">Default Trial Days</label><input id="platformTrialDays" name="trial_days" type="number" min="1" max="365" step="1" class="form-control @error('trial_days') is-invalid @enderror" value="{{ old('trial_days', $settings->trial_days) }}" required><small class="tf-muted">Used only when a new business registers. Existing trials keep their stored end date.</small>@error('trial_days')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </form>
    <form id="platformLogoRestoreForm" method="POST" action="{{ route('admin.settings.restore-default-logo') }}" class="d-none" data-tf-confirm-message="Your custom platform logo will be removed and the default TradeFlow logo will be used." data-tf-confirm-title="Restore default logo?" data-tf-confirm-button="Restore Logo" data-tf-confirm-color="#0d6efd" data-tf-confirm-saving-text="Restoring...">
        @csrf
        @method('PUT')
    </form>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button form="platformSettingsForm" type="submit" class="btn btn-tf-primary" data-platform-settings-submit>Save Settings</button>
        <form method="POST" action="{{ route('admin.settings.reset-defaults') }}" data-tf-confirm-message="This will reset platform branding, support contact details, and optional public demo and WhatsApp settings to their defaults." data-tf-confirm-title="Reset platform settings?" data-tf-confirm-button="Reset Settings" data-tf-confirm-color="#dc3545" data-tf-confirm-saving-text="Resetting...">
            @csrf
            @method('PUT')
            <button type="submit" class="btn btn-outline-danger">Reset to Default</button>
        </form>
    </div>
</div>

@php
    $storedDemoVideo = (string) ($settings->demo_video_url ?? '');
    $storedDemoPoster = (string) ($settings->demo_poster ?? '');
    $storedDemoPosterPath = preg_replace('#^(?:public/|storage/)#', '', ltrim($storedDemoPoster, '/'));
    $hasStoredDemo = filled($storedDemoVideo);
    $hasStoredPoster = filled($storedDemoPosterPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($storedDemoPosterPath);
@endphp
<form id="publicControlsForm" method="POST" action="{{ route('admin.settings.demo-video.update') }}" enctype="multipart/form-data" class="tf-settings-public-controls">
    @csrf
    @method('PUT')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div><span class="tf-eyebrow">PUBLIC EXPERIENCE</span><h2 class="h4 mb-1">Demo video &amp; WhatsApp</h2><p class="tf-muted mb-0">Optional landing-page controls. They stay hidden until enabled with a valid configuration.</p></div>
    </div>
    <div class="tf-demo-contact-grid">
        <section class="tf-card tf-settings-feature-card p-4">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                <div><span class="tf-settings-feature-icon is-blue"><i class="bi bi-play-circle-fill"></i></span><h3 class="h5 mt-3 mb-1">Landing page demo</h3><p class="tf-muted small mb-0">Give visitors a short, product-focused walkthrough.</p></div>
                <div class="form-check form-switch mb-0"><input type="hidden" name="demo_is_active" value="0"><input class="form-check-input" type="checkbox" role="switch" id="demoIsActive" name="demo_is_active" value="1" @checked(old('demo_is_active', $settings->demo_is_active))><label class="form-check-label small fw-semibold" for="demoIsActive">Active</label></div>
            </div>
            <div class="row g-3">
                <div class="col-12"><label class="form-label" for="demoTitle">Title</label><input id="demoTitle" name="demo_title" class="form-control @error('demo_title') is-invalid @enderror" maxlength="120" value="{{ old('demo_title', $settings->demo_title ?: 'See Profit Point in action') }}">@error('demo_title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label" for="demoSubtitle">Supporting text <span class="tf-muted fw-normal">(optional)</span></label><textarea id="demoSubtitle" name="demo_subtitle" rows="2" maxlength="500" class="form-control @error('demo_subtitle') is-invalid @enderror">{{ old('demo_subtitle', $settings->demo_subtitle) }}</textarea>@error('demo_subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-sm-5"><label class="form-label" for="demoSource">Video source</label><select id="demoSource" name="demo_video_type" class="form-select" data-demo-source><option value="external" @selected(old('demo_video_type', $settings->demo_video_type ?: 'external') === 'external')>Secure video URL</option><option value="upload" @selected(old('demo_video_type', $settings->demo_video_type) === 'upload')>Upload video</option></select></div>
                <div class="col-sm-7" data-demo-source-panel="external"><label class="form-label" for="demoVideoUrl">Direct HTTPS video URL</label><input id="demoVideoUrl" name="demo_video_url" type="url" class="form-control @error('demo_video_url') is-invalid @enderror" placeholder="https://example.com/demo.mp4" value="{{ old('demo_video_url', $settings->demo_video_type === 'external' ? $settings->demo_video_url : '') }}"><small class="tf-muted">MP4, WebM, or OGV only.</small>@error('demo_video_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <div class="col-sm-7 d-none" data-demo-source-panel="upload"><label class="form-label" for="demoVideoFile">Upload demo video</label><input id="demoVideoFile" name="demo_video_file" type="file" class="form-control @error('demo_video_file') is-invalid @enderror" accept="video/mp4,video/webm,video/ogg,.mp4,.webm,.ogv"><small class="tf-muted">MP4, WebM, or OGV up to 40MB.</small>@error('demo_video_file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label" for="demoPosterFile">Poster image <span class="tf-muted fw-normal">(optional)</span></label><div class="d-flex gap-3 align-items-center"><input id="demoPosterFile" name="demo_poster_file" type="file" class="form-control @error('demo_poster_file') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp"><small class="tf-muted text-nowrap">Max 2MB</small></div>@error('demo_poster_file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @if($hasStoredPoster)<div class="tf-settings-poster-preview mt-3"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($storedDemoPosterPath) }}?v={{ $settings->updated_at?->timestamp }}" alt="Current demo poster"><span>Current poster</span><label class="form-check ms-auto mb-0"><input class="form-check-input" type="checkbox" name="remove_demo_poster" value="1"><span class="form-check-label">Remove poster</span></label></div>@endif</div>
                @if($hasStoredDemo)<div class="col-12"><div class="tf-setting-current-file"><i class="bi bi-check-circle-fill"></i><span>Demo source configured{{ $settings->demo_is_active ? ' and visible on the landing page.' : ', currently hidden.' }}</span></div></div>@endif
            </div>
        </section>

        <section class="tf-card tf-settings-feature-card p-4">
            <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                <div><span class="tf-settings-feature-icon is-green"><i class="bi bi-whatsapp"></i></span><h3 class="h5 mt-3 mb-1">Floating WhatsApp</h3><p class="tf-muted small mb-0">Offer a direct, pre-filled conversation from the landing page.</p></div>
                <div class="form-check form-switch mb-0"><input type="hidden" name="whatsapp_is_active" value="0"><input class="form-check-input" type="checkbox" role="switch" id="whatsAppIsActive" name="whatsapp_is_active" value="1" @checked(old('whatsapp_is_active', $settings->whatsapp_is_active))><label class="form-check-label small fw-semibold" for="whatsAppIsActive">Active</label></div>
            </div>
            <div class="row g-3">
                <div class="col-12"><label class="form-label" for="whatsAppNumber">WhatsApp number</label><x-phone-input id="whatsAppNumber" name="whatsapp_number" :value="old('whatsapp_number', $settings->whatsapp_number ? '+'.$settings->whatsapp_number : '')" :error="$errors->first('whatsapp_number')" placeholder="WhatsApp phone number" helper-text="Choose the country, then enter the phone number. The public link uses a safe normalized format." /></div>
                <div class="col-12"><label class="form-label" for="whatsAppMessage">Pre-filled message <span class="tf-muted fw-normal">(optional)</span></label><textarea id="whatsAppMessage" name="whatsapp_message" rows="3" maxlength="500" class="form-control @error('whatsapp_message') is-invalid @enderror" placeholder="Hello, I would like to know more about Profit Point.">{{ old('whatsapp_message', $settings->whatsapp_message) }}</textarea>@error('whatsapp_message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label" for="whatsAppTooltip">Tooltip <span class="tf-muted fw-normal">(optional)</span></label><input id="whatsAppTooltip" name="whatsapp_tooltip" class="form-control @error('whatsapp_tooltip') is-invalid @enderror" maxlength="100" placeholder="Chat with us on WhatsApp" value="{{ old('whatsapp_tooltip', $settings->whatsapp_tooltip) }}">@error('whatsapp_tooltip')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @if($settings->whatsapp_number)<div class="col-12"><div class="tf-setting-current-file"><i class="bi bi-check-circle-fill"></i><span>{{ $settings->whatsapp_is_active ? 'WhatsApp button is visible on the landing page.' : 'WhatsApp contact saved but currently hidden.' }}</span></div></div>@endif
            </div>
        </section>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3"><button type="submit" class="btn btn-tf-primary"><i class="bi bi-play-circle me-1"></i>Save demo video</button><button type="submit" formaction="{{ route('admin.settings.whatsapp.update') }}" class="btn btn-outline-primary"><i class="bi bi-whatsapp me-1"></i>Save WhatsApp contact</button></div>
</form>
@if($hasStoredDemo || $settings->demo_poster)
    <form method="POST" action="{{ route('admin.settings.demo-video.destroy') }}" class="d-inline-block mt-2" data-tf-confirm-message="This removes the demo video and poster from the landing page. This cannot be undone." data-tf-confirm-title="Remove demo video?" data-tf-confirm-button="Remove demo" data-tf-confirm-color="#dc3545"><input type="hidden" name="_token" value="{{ csrf_token() }}">@method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Remove demo video</button></form>
@endif
@if($settings->whatsapp_number)
    <form method="POST" action="{{ route('admin.settings.whatsapp.destroy') }}" class="d-inline-block mt-2 ms-1" data-tf-confirm-message="This removes the WhatsApp contact and hides the floating landing-page button." data-tf-confirm-title="Remove WhatsApp contact?" data-tf-confirm-button="Remove contact" data-tf-confirm-color="#dc3545"><input type="hidden" name="_token" value="{{ csrf_token() }}">@method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Remove WhatsApp contact</button></form>
@endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const source = document.querySelector('[data-demo-source]');
    const syncDemoSource = () => document.querySelectorAll('[data-demo-source-panel]').forEach(panel => panel.classList.toggle('d-none', panel.dataset.demoSourcePanel !== source?.value));
    source?.addEventListener('change', syncDemoSource);
    syncDemoSource();
    const videoFile = document.getElementById('demoVideoFile');
    videoFile?.addEventListener('change', () => {
        const tooLarge = videoFile.files[0] && videoFile.files[0].size > 40 * 1024 * 1024;
        videoFile.setCustomValidity(tooLarge ? 'Demo video must not exceed 40MB.' : '');
        if (tooLarge) videoFile.reportValidity();
    });
});
</script>
@endpush
