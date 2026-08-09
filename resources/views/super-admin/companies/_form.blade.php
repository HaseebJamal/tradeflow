@php
    $editing = isset($company);
    $required = '<span class="text-danger" aria-hidden="true">*</span>';
    $existingVerificationDocuments = $editing
        ? $company->documents()->whereNotNull('file_path')->latest('id')->get()->keyBy('document_type')
        : collect();
@endphp

<form method="POST" action="{{ $editing ? route('admin.companies.update', $company) : route('admin.companies.store') }}" enctype="multipart/form-data" class="row g-3" @if(!$editing) data-company-create-form data-company-permission-form @endif>
    @csrf
    @if($editing) @method('PUT') @endif

    @php($firstNonPermissionError = collect($errors->getMessages())->except('permissions')->flatten()->first())
    @if($firstNonPermissionError)
        <div class="col-12"><div class="alert alert-danger mb-0"><strong>Company could not be saved.</strong> {{ $firstNonPermissionError }}</div></div>
    @endif

    @if(!$editing)
        <div class="col-12"><p class="small tf-muted mb-0">Fields marked with <span class="text-danger">*</span> are required.</p></div>
    @endif

    <div class="col-12"><h2 class="h5 mb-0">Company Information</h2></div>
    <div class="col-md-6"><label class="form-label" for="businessName">Company Name {!! $required !!}</label><input id="businessName" name="business_name" class="form-control @error('business_name') is-invalid @enderror" value="{{ old('business_name', $company->business_name ?? '') }}" required autofocus data-tf-name-only>@error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-6"><label class="form-label" for="businessType">Business Type {!! $required !!}</label><select id="businessType" name="business_type" class="form-select js-select2 @error('business_type') is-invalid @enderror" required data-tf-business-type><option value="">Select type</option>@foreach(['Manufacturer','Distributor','Wholesaler','Retail Shop','Other'] as $type)<option value="{{ $type }}" @selected(old('business_type', $company->business_type ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
    <div class="col-12 d-none" data-tf-other-business-description><label class="form-label" for="businessDescription">Describe Your Business {!! $required !!}</label><textarea id="businessDescription" name="business_description" class="form-control @error('business_description') is-invalid @enderror" rows="2" maxlength="1000" placeholder="Briefly describe the type of business you operate.">{{ old('business_description', $company->business_description ?? '') }}</textarea><small class="tf-muted">This is required only when Business Type is Other.</small>@error('business_description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label class="form-label" for="companyPhone">Company Phone {!! $required !!}</label><x-phone-input :name="$editing ? 'phone' : 'company_phone'" id="companyPhone" :value="old($editing ? 'phone' : 'company_phone', $company->phone ?? '')" :required="true" :error="$errors->first($editing ? 'phone' : 'company_phone')" /></div>
    <div class="col-md-4"><label class="form-label" for="companyCity">City {!! $required !!}</label><input id="companyCity" name="city" class="form-control" value="{{ old('city', $company->city ?? '') }}" required data-tf-name-only></div>
    <div class="col-md-4"><label class="form-label" for="registrationNumber">Registration Number <span class="tf-muted">Optional</span></label><input id="registrationNumber" name="registration_number" class="form-control" value="{{ old('registration_number', $company->registration_number ?? '') }}"></div>
    <div class="col-md-8"><label class="form-label" for="companyAddress">Address {!! $required !!}</label><textarea id="companyAddress" name="address" class="form-control" rows="2" required>{{ old('address', $company->address ?? '') }}</textarea></div>
    <div class="col-md-4"><label class="form-label" for="taxNumber">Tax / NTN Number <span class="tf-muted">Optional</span></label><input id="taxNumber" name="tax_number" class="form-control" value="{{ old('tax_number', $company->tax_number ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label" for="companyLogo">Company Logo <span class="tf-muted">Used as the business dashboard profile icon</span></label><input id="companyLogo" name="company_logo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control" data-tf-image-upload><div class="invalid-feedback" data-tf-image-error></div><small class="tf-muted d-block mt-1">JPG, JPEG, PNG, or WebP. Max 2MB.</small><small class="tf-muted d-block mt-1" data-tf-image-file-status></small>@if($editing && $company->logo)<div class="d-flex align-items-center gap-2 mt-2"><img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($company->logo) }}" class="navbar-avatar" alt="Current company logo"><label class="form-check mb-0"><input class="form-check-input" name="remove_company_logo" value="1" type="checkbox"> Remove current logo</label></div>@endif</div>
    @foreach(['cnic_image' => ['CNIC', 'cnicImage', '.pdf,image/jpeg,image/png', 'PDF, JPG, or PNG. Max 5MB.'], 'business_document' => ['Business Document', 'companyDocument', '.pdf,image/jpeg,image/png', 'PDF, JPG, or PNG. Max 5MB.'], 'shop_image' => ['Shop Image', 'shopImage', 'image/jpeg,image/png', 'JPG or PNG. Max 5MB.']] as $documentType => [$documentLabel, $fieldId, $accept, $help])
        <div class="col-md-4"><label class="form-label" for="{{ $fieldId }}">{{ $documentLabel }} <span class="tf-muted">Optional</span></label>@if($existingVerificationDocuments->has($documentType))<div class="form-control bg-light text-muted d-flex align-items-center"><i class="bi bi-lock me-2"></i>Already uploaded</div><small class="tf-muted">Uploaded verification documents cannot be replaced.</small>@else<input id="{{ $fieldId }}" name="{{ $documentType }}" type="file" accept="{{ $accept }}" class="form-control"><small class="tf-muted">{{ $help }}</small>@endif</div>
    @endforeach

    <div class="col-12 mt-3"><h2 class="h5 mb-0">Owner Account</h2></div>
    <div class="col-md-4"><label class="form-label" for="ownerName">Owner Name {!! $required !!}</label><input id="ownerName" name="owner_name" class="form-control" value="{{ old('owner_name', $company->owner?->name ?? '') }}" required data-tf-name-only></div>
    @if(!$editing)
        <div class="col-md-4"><label class="form-label" for="ownerEmail">Owner Email {!! $required !!}</label><input id="ownerEmail" name="owner_email" type="email" class="form-control @error('owner_email') is-invalid @enderror" value="{{ old('owner_email') }}" required>@error('owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    @else
        <div class="col-md-4"><label class="form-label" for="ownerLoginEmail">Owner Login Email</label><input id="ownerLoginEmail" type="email" class="form-control bg-light text-muted" value="{{ $company->owner?->email ?? '' }}" readonly aria-readonly="true"><small class="tf-muted">The Business Owner controls this login email. Super Admin can view it but cannot change it.</small></div>
    @endif
    <div class="col-md-4"><label class="form-label" for="ownerPhone">Owner Phone {!! $required !!}</label><x-phone-input name="owner_phone" id="ownerPhone" :value="old('owner_phone', $company->owner?->phone ?? '')" :required="true" :error="$errors->first('owner_phone')" /><small class="tf-muted">Private owner contact; reports use the company phone only.</small></div>
    <div class="col-md-4">
        <label class="form-label" for="ownerProfileImage">Owner Profile Image <span class="tf-muted">Optional</span></label>
        <input id="ownerProfileImage" name="owner_profile_image" type="file" accept="image/jpeg,image/png,image/webp" class="form-control @error('owner_profile_image') is-invalid @enderror" data-tf-image-upload>
        <small class="tf-muted">JPG, JPEG, PNG, or WebP. Max 2MB. This is separate from the company logo.</small>
        <div class="invalid-feedback" data-tf-image-error></div>
        <small class="tf-muted d-block mt-1" data-tf-image-file-status></small>
        @error('owner_profile_image')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @if($editing && $company->owner?->profile_image)
            <div class="d-flex align-items-center gap-2 mt-2">
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($company->owner->profile_image) }}?v={{ $company->owner->updated_at?->timestamp }}" class="navbar-avatar" alt="Current owner profile image">
                <label class="form-check mb-0"><input class="form-check-input" name="remove_owner_profile_image" value="1" type="checkbox"> Remove and restore default avatar</label>
            </div>
        @endif
    </div>

    @if(!$editing)
        <div class="col-md-4"><label class="form-label" for="temporaryPassword">Temporary Password {!! $required !!}</label><div class="input-group"><input id="temporaryPassword" name="temporary_password" type="password" class="form-control @error('temporary_password') is-invalid @enderror" autocomplete="new-password" required data-company-password><button type="button" class="btn btn-outline-secondary tf-password-toggle" data-tf-password-toggle="#temporaryPassword" data-tf-password-icon="#temporaryPasswordIcon" aria-label="Show temporary password"><i id="temporaryPasswordIcon" class="bi bi-eye"></i></button></div><small class="tf-muted">8+ characters with uppercase, lowercase, number, and special character.</small>@error('temporary_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label class="form-label" for="temporaryPasswordConfirmation">Confirm Password {!! $required !!}</label><div class="input-group"><input id="temporaryPasswordConfirmation" name="temporary_password_confirmation" type="password" class="form-control" autocomplete="new-password" required data-company-password-confirmation data-tf-manual-confirmation><button type="button" class="btn btn-outline-secondary tf-password-toggle" data-tf-password-toggle="#temporaryPasswordConfirmation" data-tf-password-icon="#temporaryPasswordConfirmationIcon" aria-label="Show confirm password"><i id="temporaryPasswordConfirmationIcon" class="bi bi-eye"></i></button></div><small class="tf-muted">Re-enter the password manually. Paste is disabled for this field.</small><div class="invalid-feedback" data-company-password-error>Password and confirm password do not match.</div></div>
        <div class="col-md-4"><label class="form-label">Initial Status</label><div class="form-control bg-light">Approved</div><small class="tf-muted">Companies created by Super Admin are approved automatically.</small></div>
        <div class="col-12"><label class="form-label" for="companyNotes">Notes <span class="tf-muted">Optional</span></label><textarea id="companyNotes" name="notes" class="form-control" rows="2" placeholder="Internal creation notes">{{ old('notes') }}</textarea></div>
        <div class="col-12 mt-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div><h2 class="h5 mb-1">Company Panel Permissions</h2><p class="tf-muted mb-0">Enable a module, then select the features and actions available to this new company.</p></div><label class="form-check border rounded px-3 py-2 mb-0"><input class="form-check-input me-2" type="checkbox" data-permission-master> Select All <span class="tf-muted" data-permission-total-selected></span></label></div></div>
        <div class="col-12"><div class="row g-3">@foreach(($definitions ?? collect())->groupBy('module') as $module => $permissions)<div class="col-md-6 col-xl-4"><x-admin.permission-group :module="$module" :label="ucwords(str_replace('_', ' ', $module))" :permissions="$permissions" :selected-permissions="old('permissions', [])" /></div>@endforeach</div></div>
    @endif

    <div class="col-12"><button class="btn btn-tf-primary" type="submit" @if(!$editing) data-company-create-submit disabled @endif>{{ $editing ? 'Save Company Changes' : 'Create Company' }}</button><a href="{{ $editing ? route('admin.companies.show', $company) : route('admin.companies.index') }}" class="btn btn-outline-secondary">Cancel</a>@if(!$editing)<div class="small text-danger mt-2 d-none" data-company-create-status></div>@endif</div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const type = document.getElementById('businessType');
    const container = document.querySelector('[data-tf-other-business-description]');
    const description = container?.querySelector('[name="business_description"]');
    if (!type || !container || !description) return;

    const syncOtherDescription = () => {
        const isOther = type.value === 'Other';
        container.classList.toggle('d-none', !isOther);
        description.disabled = !isOther;
        description.required = isOther;
    };

    type.addEventListener('change', syncOtherDescription);
    syncOtherDescription();
});
</script>
@endpush
