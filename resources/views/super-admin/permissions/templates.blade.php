@extends('layouts.dashboard')

@section('page-title', 'Permission Templates')
@section('page-subtitle', 'Create reusable access packages for company modules, features, and actions')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ route('admin.permissions.templates.store') }}" data-company-permission-form>
    @csrf
    <div class="row g-4 align-items-start">
        <div class="col-xl-8 order-2 order-xl-1">
            <div class="tf-card p-4">
                <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
                    <div><h2 class="h5 mb-1">Permission Selection</h2><p class="tf-muted mb-0">Select complete modules or only the individual controls your package needs.</p></div>
                    <label class="tf-permission-master" for="permission-template-master"><input id="permission-template-master" class="form-check-input" type="checkbox" data-permission-master> Select All Permissions</label>
                </div>
                <div class="row g-3 tf-permission-grid">
                    @forelse($definitions->groupBy('module') as $module => $permissions)
                        <div class="col-md-6"><x-admin.permission-group :module="$module" :label="ucwords(str_replace('_', ' ', $module))" :permissions="$permissions" :selected-permissions="old('permissions', [])" /></div>
                    @empty
                        <div class="col-12"><div class="alert alert-warning mb-0">No active permission definitions are available yet.</div></div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-xl-4 order-1 order-xl-2">
            <div class="tf-card p-4 tf-template-details-card">
                <div class="d-flex align-items-center gap-2 mb-3"><span class="tf-brand-mark bg-blue"><i class="bi bi-shield-plus"></i></span><div><h2 class="h5 mb-0">New Template</h2><small class="tf-muted">Reusable company access package</small></div></div>
                <div class="mb-3"><label class="form-label" for="template-name">Template Name</label><input id="template-name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Basic, Standard, Premium" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="mb-4"><label class="form-label" for="template-description">Description <span class="tf-muted">Optional</span></label><textarea id="template-description" name="description" class="form-control" rows="4" placeholder="Describe who should use this package.">{{ old('description') }}</textarea></div>
                <button class="btn btn-tf-primary w-100"><i class="bi bi-save me-1"></i>Create Permission Template</button>
            </div>
        </div>
    </div>
</form>

<section class="mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-1">Available Templates</h2><p class="tf-muted mb-0">Apply a package to a company, then fine-tune its permissions where needed.</p></div><span class="tf-muted small">{{ $templates->count() }} templates</span></div>
    <div class="row g-3">
        @forelse($templates as $template)
            <div class="col-md-6 col-xl-4">
                <article class="tf-card p-4 h-100 tf-template-card">
                    <div class="d-flex justify-content-between gap-2 align-items-start"><div><h3 class="h6 mb-1">{{ $template->name }}</h3><p class="tf-muted small mb-0">{{ $template->description ?: 'No description provided.' }}</p></div><span class="badge text-bg-primary">{{ $template->items->where('allowed', true)->count() }}</span></div>
                    <div class="small tf-muted mt-3"><i class="bi bi-shield-check me-1"></i>Enabled permissions <span class="mx-1">•</span><x-date-time :value="$template->created_at" /></div>
                    <form method="POST" action="{{ route('admin.permissions.templates.apply', $template) }}" class="mt-3">@csrf<label class="form-label small" for="apply-company-{{ $template->id }}">Apply to Company</label><div class="input-group"><select id="apply-company-{{ $template->id }}" name="company_id" class="form-select" required><option value="">Choose company</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->business_name }}</option>@endforeach</select><button class="btn btn-outline-primary">Apply</button></div></form>
                </article>
            </div>
        @empty
            <div class="col-12"><div class="tf-card p-5 text-center tf-muted"><i class="bi bi-shield-plus fs-2 d-block mb-2"></i>No permission templates have been created yet.</div></div>
        @endforelse
    </div>
</section>
@endsection
