@php
    $editing = isset($company);
    $required = '<span class="text-danger" aria-hidden="true">*</span>';
@endphp

<form method="POST" action="{{ $editing ? route('admin.companies.update', $company) : route('admin.companies.store') }}" enctype="multipart/form-data" class="row g-3" @if(!$editing) data-company-create-form @endif novalidate>
    @csrf
    @if($editing) @method('PUT') @endif

    @if(!$editing)
        <div class="col-12"><p class="small tf-muted mb-0">Fields marked with <span class="text-danger">*</span> are required.</p></div>
        <div class="col-12 d-none" data-company-draft-alert><div class="alert alert-info d-flex justify-content-between align-items-center mb-0">Your unfinished company draft has been restored.<button class="btn btn-sm btn-outline-secondary" type="button" data-clear-company-draft>Clear Saved Draft</button></div></div>
    @endif

    <div class="col-12"><h2 class="h5 mb-0">Company Information</h2></div>
    <div class="col-md-6"><label class="form-label" for="businessName">Company Name {!! $editing ? '' : $required !!}</label><input id="businessName" name="business_name" class="form-control @error('business_name') is-invalid @enderror" value="{{ old('business_name', $company->business_name ?? '') }}" required>@error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label class="form-label" for="businessType">Business Type {!! $editing ? '' : $required !!}</label><select id="businessType" name="business_type" class="form-select @error('business_type') is-invalid @enderror" required><option value="">Select type</option>@foreach(['Manufacturer','Distributor','Wholesaler','Retail Shop'] as $type)<option value="{{ $type }}" @selected(old('business_type', $company->business_type ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label" for="companyCategory">Category</label><input id="companyCategory" name="category" class="form-control" value="{{ old('category', $company->category ?? '') }}"></div>
    <div class="col-md-4"><label class="form-label" for="companyPhone">Company Phone {!! $editing ? '' : $required !!}</label><input id="companyPhone" name="{{ $editing ? 'phone' : 'company_phone' }}" class="form-control @error('company_phone') is-invalid @enderror" value="{{ old($editing ? 'phone' : 'company_phone', $company->phone ?? '') }}" required>@error('company_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label class="form-label" for="companyCity">City {!! $editing ? '' : $required !!}</label><input id="companyCity" name="city" class="form-control" value="{{ old('city', $company->city ?? '') }}" required></div>
    <div class="col-md-4"><label class="form-label" for="registrationNumber">Registration Number <span class="tf-muted">Optional</span></label><input id="registrationNumber" name="registration_number" class="form-control" value="{{ old('registration_number', $company->registration_number ?? '') }}"></div>
    <div class="col-md-8"><label class="form-label" for="companyAddress">Address {!! $editing ? '' : $required !!}</label><textarea id="companyAddress" name="address" class="form-control" rows="2" required>{{ old('address', $company->address ?? '') }}</textarea></div>
    <div class="col-md-4"><label class="form-label" for="taxNumber">Tax / NTN Number <span class="tf-muted">Optional</span></label><input id="taxNumber" name="tax_number" class="form-control" value="{{ old('tax_number', $company->tax_number ?? '') }}"></div>
    @if(!$editing)
        <div class="col-md-4"><label class="form-label" for="companyLogo">Company Logo <span class="tf-muted">Optional</span></label><input id="companyLogo" name="company_logo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control"></div>
        <div class="col-md-4"><label class="form-label" for="companyDocument">Business Document <span class="tf-muted">Optional</span></label><input id="companyDocument" name="business_document" type="file" accept=".pdf,image/jpeg,image/png" class="form-control"></div>
        <div class="col-md-4"><label class="form-label" for="templateSelect">Initial Permission Template <span class="tf-muted">Optional</span></label><select id="templateSelect" name="permission_template_id" class="form-select"><option value="">Use existing default access</option>@foreach(($templates ?? collect()) as $template)<option value="{{ $template->id }}" @selected(old('permission_template_id') == $template->id)>{{ $template->name }}</option>@endforeach</select></div>
    @endif

    <div class="col-12 mt-3"><h2 class="h5 mb-0">Owner Account</h2></div>
    <div class="col-md-4"><label class="form-label" for="ownerName">Owner Name {!! $editing ? '' : $required !!}</label><input id="ownerName" name="owner_name" class="form-control" value="{{ old('owner_name', $company->owner?->name ?? '') }}" required></div>
    <div class="col-md-4"><label class="form-label" for="ownerEmail">Owner Email {!! $editing ? '' : $required !!}</label><input id="ownerEmail" name="owner_email" type="email" class="form-control @error('owner_email') is-invalid @enderror" value="{{ old('owner_email', $company->owner?->email ?? '') }}" required>@error('owner_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-4"><label class="form-label" for="ownerPhone">Owner Phone {!! $editing ? '' : $required !!}</label><input id="ownerPhone" name="owner_phone" class="form-control" value="{{ old('owner_phone', $company->owner?->phone ?? '') }}" required></div>

    @if(!$editing)
        <div class="col-md-4"><label class="form-label" for="temporaryPassword">Temporary Password {!! $required !!}</label><div class="input-group"><input id="temporaryPassword" name="temporary_password" type="password" class="form-control @error('temporary_password') is-invalid @enderror" autocomplete="new-password" required data-company-password><button type="button" class="btn btn-outline-secondary tf-password-toggle" data-tf-password-toggle="#temporaryPassword" data-tf-password-icon="#temporaryPasswordIcon" aria-label="Show temporary password"><i id="temporaryPasswordIcon" class="bi bi-eye"></i></button></div><small class="tf-muted">8+ characters with uppercase, lowercase, number, and special character.</small>@error('temporary_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label class="form-label" for="temporaryPasswordConfirmation">Confirm Password {!! $required !!}</label><div class="input-group"><input id="temporaryPasswordConfirmation" name="temporary_password_confirmation" type="password" class="form-control" autocomplete="new-password" required data-company-password-confirmation><button type="button" class="btn btn-outline-secondary tf-password-toggle" data-tf-password-toggle="#temporaryPasswordConfirmation" data-tf-password-icon="#temporaryPasswordConfirmationIcon" aria-label="Show confirm password"><i id="temporaryPasswordConfirmationIcon" class="bi bi-eye"></i></button></div><div class="invalid-feedback" data-company-password-error>Password and confirm password do not match.</div></div>
        <div class="col-md-4"><label class="form-label" for="initialStatus">Initial Status {!! $required !!}</label><select id="initialStatus" name="initial_status" class="form-select" required>@foreach(['Pending','Approved','Rejected','Suspended'] as $status)<option value="{{ $status }}" @selected(old('initial_status', 'Pending') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div class="col-12"><label class="form-label" for="companyNotes">Notes <span class="tf-muted">Optional</span></label><textarea id="companyNotes" name="notes" class="form-control" rows="2" placeholder="Internal creation notes">{{ old('notes') }}</textarea></div>
    @endif

    <div class="col-12"><button class="btn btn-tf-primary" type="submit" @if(!$editing) data-company-create-submit disabled @endif>{{ $editing ? 'Save Company Changes' : 'Create Company' }}</button><a href="{{ $editing ? route('admin.companies.show', $company) : route('admin.companies.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
</form>
