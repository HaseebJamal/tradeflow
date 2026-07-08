@extends('layouts.public')
@section('title', 'Register Business | TradeFlow')
@section('content')
<section class="tf-section" style="padding-top:130px">
    <div class="container">
        <div class="mb-4">
            <span class="tf-badge tf-badge-info mb-2">Business onboarding</span>
            <h1 class="fw-bold">Register Business</h1>
            <p class="tf-muted">Complete each step and submit your business for approval.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-3">
                <div class="tf-card p-3 d-grid gap-2 tf-wizard-menu">
                    @foreach(['Owner Information','Business Type','Business Information','Verification Upload'] as $step)
                    <button type="button" class="tf-step-tab {{ $loop->first ? 'active' : '' }}" data-tf-step-tab="{{ $loop->index }}"><span>{{ $loop->iteration }}</span>{{ $step }}</button>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-9">
                <div class="tf-card p-4 p-lg-5">
                    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
                    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                    <div class="alert alert-success d-none" data-tf-register-success>Your business registration has been submitted for approval.</div>
                    <form method="POST" action="{{ route('register.business.store') }}" enctype="multipart/form-data" data-tf-register-form>@csrf
                        <div class="tf-step-panel active" data-tf-step-panel="0">
                            <h2 class="h4 fw-bold">Owner Information</h2>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name') }}" required></div>
                                <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone') }}" required></div>
                                <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="{{ old('email') }}" required></div>
                                <div class="col-md-6"><label class="form-label">Password</label><div class="input-group"><input id="ownerPassword" name="password" type="password" class="form-control" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#ownerPassword" data-tf-password-icon="#ownerPasswordIcon"><i id="ownerPasswordIcon" class="bi bi-eye"></i></button></div></div>
                                <div class="col-md-6"><label class="form-label">Confirm Password</label><div class="input-group"><input id="ownerPasswordConfirm" name="password_confirmation" type="password" class="form-control" required><button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#ownerPasswordConfirm" data-tf-password-icon="#ownerPasswordConfirmIcon"><i id="ownerPasswordConfirmIcon" class="bi bi-eye"></i></button></div></div>
                            </div>
                        </div>
                        <div class="tf-step-panel" data-tf-step-panel="1">
                            <h2 class="h4 fw-bold">Business Type</h2>
                            <div class="alert alert-warning d-none mt-3 mb-0" data-tf-business-type-alert>
                                Please select one business type before continuing.
                            </div>
                            <div class="row g-3 mt-1">
                                @foreach(['Manufacturer'=>'bi-gear-wide-connected','Distributor'=>'bi-diagram-3','Wholesaler'=>'bi-boxes','Retail Shop'=>'bi-shop'] as $type=>$icon)
                                <div class="col-md-6 col-xl-3"><label class="tf-business-type-card"><input type="radio" name="business_type" value="{{ $type }}" @checked(old('business_type') === $type) required><i class="bi {{ $icon }}"></i><strong>{{ $type }}</strong></label></div>
                                @endforeach
                            </div>
                        </div>
                        <div class="tf-step-panel" data-tf-step-panel="2">
                            <h2 class="h4 fw-bold">Business Information</h2>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6"><label class="form-label">Business Name</label><input name="business_name" class="form-control" value="{{ old('business_name') }}" required></div>
                                <div class="col-md-6"><label class="form-label">Owner Name</label><input class="form-control" value="{{ old('name') }}" data-tf-owner-copy></div>
                                <div class="col-md-12"><label class="form-label">Address</label><input name="address" class="form-control" value="{{ old('address') }}"></div>
                                <div class="col-md-6"><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city') }}" required></div>
                                <div class="col-md-6"><label class="form-label">Category</label><input name="category" class="form-control" value="{{ old('category') }}"></div>
                                <div class="col-md-6"><label class="form-label">Registration Number</label><input name="registration_number" class="form-control" value="{{ old('registration_number') }}"></div>
                                <div class="col-md-6"><label class="form-label">Tax Number Optional</label><input name="tax_number" class="form-control" value="{{ old('tax_number') }}"></div>
                            </div>
                        </div>
                        <div class="tf-step-panel" data-tf-step-panel="3">
                            <h2 class="h4 fw-bold">Verification Upload</h2>
                            <div class="row g-3 mt-1">
                                <div class="col-md-4"><div class="tf-upload"><i class="bi bi-card-image h2 text-blue"></i><h3 class="h6">CNIC Upload</h3><input name="cnic_image" class="form-control" type="file"></div></div>
                                <div class="col-md-4"><div class="tf-upload"><i class="bi bi-file-earmark-arrow-up h2 text-blue"></i><h3 class="h6">Business Document Upload</h3><input name="business_document" class="form-control" type="file"></div></div>
                                <div class="col-md-4"><div class="tf-upload"><i class="bi bi-shop h2 text-blue"></i><h3 class="h6">Shop Image Upload</h3><input name="shop_image" class="form-control" type="file"></div></div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
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
