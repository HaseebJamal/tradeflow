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
                        @foreach (['Owner Information', 'Business Type', 'Business Information', 'Choose Plan', 'Verification Upload'] as $step)
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
                        <div class="alert alert-info d-none" data-tf-register-restored data-tf-auto-dismiss>
                            Your saved registration draft has been restored. For security, please re-enter your password and select the verification files again.
                        </div>

                        <form method="POST" action="{{ route('register.business.store') }}" enctype="multipart/form-data"
                            data-tf-register-form data-registration-step="{{ session('registration_step', 1) }}" data-registration-has-errors="{{ $errors->any() ? '1' : '0' }}" data-tf-tab-order novalidate>
                            @csrf

                            <div class="tf-step-panel" data-tf-step-panel="0">
                                <h2 class="h4 fw-bold">Owner Information</h2>
                                <div class="row g-3 mt-1">
                                    <div class="col-md-6">
                                        <label class="form-label" for="business-owner-name">Owner Name <span class="text-danger" aria-hidden="true">*</span></label>
                                        <input id="business-owner-name" name="name" type="text" autocomplete="name" maxlength="255" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required data-tf-name-only>
                                        <div class="invalid-feedback" data-register-error="name">@error('name'){{ $message }}@enderror</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="business-owner-phone">Phone <span class="text-danger" aria-hidden="true">*</span></label>
                                        <x-phone-input name="phone" id="business-owner-phone" :value="old('phone')" :required="true" :error="$errors->first('phone')" />
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
                                    @foreach (['Manufacturer' => 'bi-gear-wide-connected', 'Distributor' => 'bi-diagram-3', 'Wholesaler' => 'bi-boxes', 'Retail Shop' => 'bi-shop', 'Other' => 'bi-grid-3x3-gap'] as $type => $icon)
                                        <div class="col-md-6 col-xl-3">
                                            <label class="tf-business-type-card">
                                                <input type="radio" name="business_type" value="{{ $type }}" @checked(old('business_type') === $type) required>
                                                <i class="bi {{ $icon }}"></i><strong>{{ $type }}</strong>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="invalid-feedback d-block" data-register-error="business_type">@error('business_type'){{ $message }}@enderror</div>
                                <div class="row g-3 mt-3 d-none" data-tf-other-business-type>
                                    <div class="col-md-6">
                                        <label class="form-label" for="other_business_type">Specify your business type <span class="text-danger" aria-hidden="true">*</span></label>
                                        <input id="other_business_type" name="other_business_type" maxlength="255" class="form-control @error('other_business_type') is-invalid @enderror" value="{{ old('other_business_type') }}" placeholder="For example, Pharmacy">
                                        <div class="invalid-feedback d-block" data-register-error="other_business_type">@error('other_business_type'){{ $message }}@enderror</div>
                                    </div>
                                </div>
                            </div>

                            <div class="tf-step-panel" data-tf-step-panel="2">
                                <h2 class="h4 fw-bold">Business Information</h2>
                                <div class="row g-3 mt-1">
                                    <div class="col-md-6">
                                        <label class="form-label" for="business_name">Business Name <span class="text-danger" aria-hidden="true">*</span></label>
                                        <input id="business_name" name="business_name" maxlength="255" class="form-control @error('business_name') is-invalid @enderror" value="{{ old('business_name') }}" required data-tf-name-only>
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
                                        <input id="business_city" name="city" maxlength="100" class="form-control @error('city') is-invalid @enderror" value="{{ old('city') }}" required data-tf-name-only>
                                        <div class="invalid-feedback" data-register-error="city">@error('city'){{ $message }}@enderror</div>
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
                                <h2 class="h4 fw-bold">Choose Plan <span class="text-danger" aria-hidden="true">*</span></h2>
                                @php
                                    $pricingLocked = is_array($pricingSelection ?? null);
                                @endphp
                                @if($pricingLocked)
                                    @php
                                        $lockedPlan = $plans->firstWhere('id', $pricingSelection['plan_id']);
                                        $lockedCycle = $pricingSelection['billing_cycle'];
                                        $lockedPrice = $lockedPlan?->priceFor($lockedCycle) ?? 0;
                                    @endphp
                                    <input type="hidden" name="selected_plan_id" value="{{ $pricingSelection['plan_id'] }}" required>
                                    <input type="hidden" name="billing_cycle" value="{{ $lockedCycle }}" required>
                                    <input type="hidden" name="plan_selection_source" value="pricing">
                                    <p class="tf-muted small">You selected this plan from the pricing page.</p>
                                    <div class="border rounded p-4 bg-light">
                                        <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                                            <div><h3 class="h5 mb-1">{{ $lockedPlan?->name }}</h3><div class="tf-muted">{{ $lockedCycle }} - Rs {{ number_format($lockedPrice) }}</div></div>
                                            @if($lockedPlan && $lockedPlan->is_recommended)
                                                <span class="tf-badge tf-badge-info">Recommended</span>
                                            @endif
                                        </div>
                                        <div class="mt-3 small"><strong>{{ $lockedPlan?->trial_days }}-day free trial</strong><span class="tf-muted"> · {{ number_format((int) $lockedPlan?->product_limit) }} products · {{ number_format((int) $lockedPlan?->staff_limit) }} staff · {{ number_format((int) $lockedPlan?->order_limit) }} orders</span></div>
                                        <a href="{{ route('public.pricing') }}" class="btn btn-outline-primary btn-sm mt-3">Back to Pricing</a>
                                    </div>
                                @else
                                <input type="hidden" name="plan_selection_source" value="direct">
                                <p class="tf-muted small">Start with a free trial. Your trial begins after Super Admin approves your business.</p>
                                <div class="btn-group mb-3" role="group" aria-label="Billing cycle">
                                    <input type="radio" class="btn-check" name="billing_cycle" id="registrationMonthly" value="Monthly" data-registration-billing-cycle @checked(old('billing_cycle', $selectedBillingCycle ?? 'Monthly') === 'Monthly')>
                                    <label class="btn btn-outline-primary" for="registrationMonthly">Monthly</label>
                                    <input type="radio" class="btn-check" name="billing_cycle" id="registrationYearly" value="Yearly" data-registration-billing-cycle @checked(old('billing_cycle', $selectedBillingCycle ?? 'Monthly') === 'Yearly')>
                                    <label class="btn btn-outline-primary" for="registrationYearly">Yearly</label>
                                </div>
                                <div class="row g-3">
                                    @forelse($plans as $plan)
                                        @php
                                            $selectedPlan = (string) old('selected_plan_id', $selectedPlanId ?? null) === (string) $plan->id;
                                            $monthlyPrice = $plan->priceFor('Monthly');
                                            $yearlyPrice = $plan->priceFor('Yearly');
                                            $yearlySaving = $monthlyPrice > 0 && $yearlyPrice > 0 && $yearlyPrice < ($monthlyPrice * 12)
                                                ? (int) round((1 - ($yearlyPrice / ($monthlyPrice * 12))) * 100)
                                                : 0;
                                        @endphp
                                        <div class="col-md-6">
                                            <label class="tf-plan-option" data-registration-plan-option>
                                                <input type="radio" name="selected_plan_id" value="{{ $plan->id }}" required data-registration-plan-input @checked($selectedPlan)>
                                                <div class="d-flex justify-content-between gap-2"><strong>{{ $plan->name }}</strong><span>@if($yearlySaving > 0)<span class="tf-badge tf-badge-info">Save {{ $yearlySaving }}%</span>@elseif($plan->is_recommended)<span class="tf-badge tf-badge-info">Recommended</span>@endif</span></div>
                                                <div class="h5 mt-2 mb-1" data-registration-monthly-price>Rs {{ number_format($monthlyPrice) }} <small class="tf-muted">/ month</small></div>
                                                <div class="h5 mt-2 mb-1 d-none" data-registration-yearly-price>Rs {{ number_format($yearlyPrice) }} <small class="tf-muted">/ year</small></div>
                                                <small class="tf-muted d-block">{{ $plan->trial_days }}-day trial · {{ number_format($plan->product_limit) }} products · {{ number_format($plan->staff_limit) }} staff</small>
                                            </label>
                                        </div>
                                    @empty
                                        <div class="col-12"><div class="alert alert-warning mb-0">No public plan is available right now. Please contact {{ $platformSettings->company_name }} support.</div></div>
                                    @endforelse
                                </div>
                                @endif
                                <div class="invalid-feedback d-block" data-register-error="selected_plan_id">@error('selected_plan_id'){{ $message }}@enderror</div>
                            </div>

                            <div class="tf-step-panel" data-tf-step-panel="4">
                                <h2 class="h4 fw-bold">Verification Upload</h2>
                                <p class="tf-muted small">Files are checked for their real type and then manually verified by Super Admin. Each upload must be a separate, readable file up to 5 MB.</p>
                                <div class="row g-3 mt-1">
                                    <div class="col-md-4">
                                        <div class="tf-upload">
                                            <i class="bi bi-card-image h2 text-blue"></i>
                                            <label class="h6 d-block" for="cnic_image">CNIC Upload <span class="text-danger" aria-hidden="true">*</span></label>
                                            <input id="cnic_image" name="cnic_image" class="form-control @error('cnic_image') is-invalid @enderror" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required data-register-file data-document-purpose="cnic_image">
                                            <small class="tf-muted d-block mt-1">CNIC front/back image or CNIC PDF only.</small>
                                            <small class="tf-muted" data-file-name="cnic_image">No file selected.</small>
                                            <div class="invalid-feedback d-block" data-register-error="cnic_image">@error('cnic_image'){{ $message }}@enderror</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="tf-upload">
                                            <i class="bi bi-file-earmark-arrow-up h2 text-blue"></i>
                                            <label class="h6 d-block" for="business_document">Business Document <span class="text-danger" aria-hidden="true">*</span></label>
                                            <input id="business_document" name="business_document" class="form-control @error('business_document') is-invalid @enderror" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required data-register-file data-document-purpose="business_document">
                                            <small class="tf-muted d-block mt-1">Registration, NTN/tax, license, partnership, or company proof.</small>
                                            <small class="tf-muted" data-file-name="business_document">No file selected.</small>
                                            <div class="invalid-feedback d-block" data-register-error="business_document">@error('business_document'){{ $message }}@enderror</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="tf-upload">
                                            <i class="bi bi-shop h2 text-blue"></i>
                                            <label class="h6 d-block" for="shop_image">Shop / Business Image <span class="text-danger" aria-hidden="true">*</span></label>
                                            <input id="shop_image" name="shop_image" class="form-control @error('shop_image') is-invalid @enderror" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required data-register-file data-document-purpose="shop_image">
                                            <small class="tf-muted d-block mt-1">A clear photo of the shop, office, warehouse, or other business premises.</small>
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
