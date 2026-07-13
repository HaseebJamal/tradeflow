@extends('layouts.public')

@section('title', 'Register Business | TradeFlow')

@section('content')
    <section class="tf-section" style="padding-top:130px">
        <div class="container">
            <div class="mb-4">
                <span class="tf-badge tf-badge-info mb-2">Business onboarding</span>
                <h1 class="fw-bold">Register Business</h1>
                <p class="tf-muted mb-0">Complete each step and submit your business for approval.</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-3">
                    <div class="tf-card p-3 d-grid gap-2 tf-wizard-menu">
                        @foreach (['Owner Information', 'Business Type', 'Business Information', 'Verification Upload'] as $step)
                            <button type="button" class="tf-step-tab {{ $loop->index + 1 === session('registration_step', 1) ? 'active' : '' }}" data-tf-step-tab="{{ $loop->index }}">
                                <span>{{ $loop->iteration }}</span>{{ $step }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="col-lg-9">
                    <div class="tf-card p-4 p-lg-5">
                        @if ($errors->any())
                            <div class="alert alert-danger">Please correct the highlighted fields and continue from this step.</div>
                        @endif
                        @if (session('success'))
                            <div class="alert alert-success" data-tf-registration-complete>{{ session('success') }}</div>
                        @endif
                        <div class="alert alert-info d-none" data-tf-register-restored data-tf-auto-dismiss>
                            Your saved registration draft has been restored. For security, please re-enter your password and select the verification files again.
                        </div>

                        <form method="POST" action="{{ route('register.business.store') }}" enctype="multipart/form-data"
                            data-tf-register-form data-registration-step="{{ session('registration_step', 1) }}" data-tf-tab-order novalidate>
                            @csrf

                            <div class="tf-step-panel" data-tf-step-panel="0">
                                <h2 class="h4 fw-bold">Owner Information</h2>
                                <div class="row g-3 mt-1">
                                    <div class="col-md-6">
                                        <label class="form-label" for="business-owner-name">Owner Name <span class="text-danger" aria-hidden="true">*</span></label>
                                        <input id="business-owner-name" name="name" type="text" autocomplete="name" maxlength="255" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                                        <div class="invalid-feedback" data-register-error="name">@error('name'){{ $message }}@enderror</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="business-owner-phone">Phone <span class="text-danger" aria-hidden="true">*</span></label>
                                        <input id="business-owner-phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="15" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="03001234567" required>
                                        <div class="invalid-feedback" data-register-error="phone">@error('phone'){{ $message }}@enderror</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="business-owner-email">Email <span class="text-danger" aria-hidden="true">*</span></label>
                                        <input id="business-owner-email" name="email" type="email" autocomplete="email" inputmode="email" maxlength="255" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                                        <div class="invalid-feedback" data-register-error="email">@error('email'){{ $message }}@enderror</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="ownerPassword">Password <span class="text-danger" aria-hidden="true">*</span></label>
                                        <div class="input-group">
                                            <input id="ownerPassword" name="password" type="password" autocomplete="new-password" minlength="8" class="form-control @error('password') is-invalid @enderror" required>
                                            <button class="btn btn-outline-secondary tf-password-toggle" type="button" aria-label="Show password" data-tf-password-toggle="#ownerPassword" data-tf-password-icon="#ownerPasswordIcon"><i id="ownerPasswordIcon" class="bi bi-eye"></i></button>
                                        </div>
                                        <small class="tf-muted">At least 8 characters with uppercase, lowercase, number, and special character.</small>
                                        <div class="invalid-feedback d-block" data-register-error="password">@error('password'){{ $message }}@enderror</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="ownerPasswordConfirm">Confirm Password <span class="text-danger" aria-hidden="true">*</span></label>
                                        <div class="input-group">
                                            <input id="ownerPasswordConfirm" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" class="form-control @error('password_confirmation') is-invalid @enderror" required>
                                            <button class="btn btn-outline-secondary tf-password-toggle" type="button" aria-label="Show confirm password" data-tf-password-toggle="#ownerPasswordConfirm" data-tf-password-icon="#ownerPasswordConfirmIcon"><i id="ownerPasswordConfirmIcon" class="bi bi-eye"></i></button>
                                        </div>
                                        <div class="invalid-feedback d-block" data-register-error="password_confirmation">@error('password_confirmation'){{ $message }}@enderror</div>
                                    </div>
                                </div>
                            </div>

                            <div class="tf-step-panel" data-tf-step-panel="1">
                                <h2 class="h4 fw-bold">Business Type <span class="text-danger" aria-hidden="true">*</span></h2>
                                <div class="row g-3 mt-1">
                                    @foreach (['Manufacturer' => 'bi-gear-wide-connected', 'Distributor' => 'bi-diagram-3', 'Wholesaler' => 'bi-boxes', 'Retail Shop' => 'bi-shop'] as $type => $icon)
                                        <div class="col-md-6 col-xl-3">
                                            <label class="tf-business-type-card">
                                                <input type="radio" name="business_type" value="{{ $type }}" @checked(old('business_type') === $type) required>
                                                <i class="bi {{ $icon }}"></i><strong>{{ $type }}</strong>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="invalid-feedback d-block" data-register-error="business_type">@error('business_type'){{ $message }}@enderror</div>
                            </div>

                            <div class="tf-step-panel" data-tf-step-panel="2">
                                <h2 class="h4 fw-bold">Business Information</h2>
                                <div class="row g-3 mt-1">
                                    <div class="col-md-6">
                                        <label class="form-label" for="business_name">Business Name <span class="text-danger" aria-hidden="true">*</span></label>
                                        <input id="business_name" name="business_name" maxlength="255" class="form-control @error('business_name') is-invalid @enderror" value="{{ old('business_name') }}" required>
                                        <div class="invalid-feedback" data-register-error="business_name">@error('business_name'){{ $message }}@enderror</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="business_owner_copy">Owner Name</label>
                                        <input id="business_owner_copy" class="form-control" data-tf-owner-copy readonly tabindex="-1">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="business_address">Business Address <span class="text-danger" aria-hidden="true">*</span></label>
                                        <input id="business_address" name="address" maxlength="1000" class="form-control @error('address') is-invalid @enderror" value="{{ old('address') }}" required>
                                        <div class="invalid-feedback" data-register-error="address">@error('address'){{ $message }}@enderror</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="business_city">City <span class="text-danger" aria-hidden="true">*</span></label>
                                        <input id="business_city" name="city" maxlength="100" class="form-control @error('city') is-invalid @enderror" value="{{ old('city') }}" required>
                                        <div class="invalid-feedback" data-register-error="city">@error('city'){{ $message }}@enderror</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="business_category">Category <span class="text-danger" aria-hidden="true">*</span></label>
                                        <input id="business_category" name="category" maxlength="100" class="form-control @error('category') is-invalid @enderror" value="{{ old('category') }}" required>
                                        <div class="invalid-feedback" data-register-error="category">@error('category'){{ $message }}@enderror</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="registration_number">Registration Number <span class="tf-muted">Optional</span></label>
                                        <input id="registration_number" name="registration_number" maxlength="100" class="form-control" value="{{ old('registration_number') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="tax_number">Tax / NTN Number <span class="tf-muted">Optional</span></label>
                                        <input id="tax_number" name="tax_number" maxlength="100" class="form-control" value="{{ old('tax_number') }}">
                                    </div>
                                </div>
                            </div>

                            <div class="tf-step-panel" data-tf-step-panel="3">
                                <h2 class="h4 fw-bold">Verification Upload</h2>
                                <p class="tf-muted small">Upload PDF, JPG, JPEG, or PNG files up to 5 MB. Shop image accepts JPG, JPEG, or PNG.</p>
                                <div class="row g-3 mt-1">
                                    <div class="col-md-4">
                                        <div class="tf-upload">
                                            <i class="bi bi-card-image h2 text-blue"></i>
                                            <label class="h6 d-block" for="cnic_image">CNIC Upload <span class="text-danger" aria-hidden="true">*</span></label>
                                            <input id="cnic_image" name="cnic_image" class="form-control @error('cnic_image') is-invalid @enderror" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required data-register-file>
                                            <small class="tf-muted" data-file-name="cnic_image">No file selected.</small>
                                            <div class="invalid-feedback d-block" data-register-error="cnic_image">@error('cnic_image'){{ $message }}@enderror</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="tf-upload">
                                            <i class="bi bi-file-earmark-arrow-up h2 text-blue"></i>
                                            <label class="h6 d-block" for="business_document">Business Document <span class="text-danger" aria-hidden="true">*</span></label>
                                            <input id="business_document" name="business_document" class="form-control @error('business_document') is-invalid @enderror" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required data-register-file>
                                            <small class="tf-muted" data-file-name="business_document">No file selected.</small>
                                            <div class="invalid-feedback d-block" data-register-error="business_document">@error('business_document'){{ $message }}@enderror</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="tf-upload">
                                            <i class="bi bi-shop h2 text-blue"></i>
                                            <label class="h6 d-block" for="shop_image">Shop / Business Image <span class="text-danger" aria-hidden="true">*</span></label>
                                            <input id="shop_image" name="shop_image" class="form-control @error('shop_image') is-invalid @enderror" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required data-register-file>
                                            <small class="tf-muted" data-file-name="shop_image">No file selected.</small>
                                            <div class="invalid-feedback d-block" data-register-error="shop_image">@error('shop_image'){{ $message }}@enderror</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4 gap-2">
                                <button type="button" class="btn btn-outline-primary" data-tf-step-back disabled>Back</button>
                                <button type="button" class="btn btn-tf-primary" data-tf-step-next>Next</button>
                                <button class="btn btn-tf-primary d-none" data-tf-step-submit>Submit Business</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/register-business.js') }}?v={{ filemtime(public_path('js/register-business.js')) }}"></script>
@endpush
