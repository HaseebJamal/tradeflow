@props([
    'business',
    'footer',
    'action',
    'method' => 'PUT',
    'backRoute',
    'resetAction' => null,
    'lockedCompany' => false,
    'adminMode' => false,
    'changeRequestAction' => null,
    'cancelRequestRoute' => null,
    'pendingRequests' => collect(),
    'changeFields' => [],
])
<div class="tf-card p-4">
    <div class="row g-4">
        <div class="col-lg-7">
            <form method="POST" action="{{ $action }}" class="row g-3">
                @csrf
                @method($method)
                @if($lockedCompany)
                    <div class="col-12"><label class="form-label">Company</label><input class="form-control" value="{{ $business->business_name }}" readonly aria-readonly="true"><small class="tf-muted">This footer is locked to the selected company.</small></div>
                @endif

                @if($adminMode)
                    <div class="col-12"><h2 class="h6 mb-0">Company Details Used by Footer</h2></div>
                    <div class="col-md-6"><label class="form-label" for="footerBusinessName">Company Name</label><input id="footerBusinessName" name="business_name" class="form-control @error('business_name') is-invalid @enderror" maxlength="255" value="{{ old('business_name', $business->business_name) }}" required>@error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="footerBusinessEmail">Business Email</label><input id="footerBusinessEmail" name="business_email" type="email" class="form-control @error('business_email') is-invalid @enderror" maxlength="255" value="{{ old('business_email', $business->owner?->email) }}" required>@error('business_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label">Phone</label><x-phone-input name="phone" :value="old('phone', $business->phone)" :error="$errors->first('phone')" /></div>
                    <div class="col-md-6"><label class="form-label" for="footerWebsite">Website</label><input id="footerWebsite" name="website" type="url" class="form-control @error('website') is-invalid @enderror" maxlength="255" value="{{ old('website', $business->website) }}" placeholder="https://example.com">@error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label class="form-label" for="footerAddress">Address</label><input id="footerAddress" name="address" class="form-control @error('address') is-invalid @enderror" maxlength="1000" value="{{ old('address', $business->address) }}">@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="footerTaxNumber">NTN / Tax Number</label><input id="footerTaxNumber" name="tax_number" class="form-control @error('tax_number') is-invalid @enderror" maxlength="100" value="{{ old('tax_number', $business->tax_number) }}">@error('tax_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="poweredByText">Powered by Text</label><input id="poweredByText" name="powered_by_text" class="form-control @error('powered_by_text') is-invalid @enderror" maxlength="100" value="{{ old('powered_by_text', $footer->powered_by_text ?: 'Powered by TradeFlow') }}">@error('powered_by_text')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @else
                    <div class="col-12"><div class="alert alert-info mb-0">Company name, business email, and Powered by TradeFlow text are managed from the company profile. You can manage the remaining document-footer details here.</div></div>
                    <div class="col-12"><h2 class="h6 mb-0">Company Details Used by Footer</h2></div>
                    <div class="col-md-6"><label class="form-label">Company Name</label><input class="form-control" value="{{ $business->business_name }}" readonly aria-readonly="true"><small class="tf-muted">This value is managed from the company profile.</small></div>
                    <div class="col-md-6"><label class="form-label">Business Email</label><input class="form-control" value="{{ $business->owner?->email }}" readonly aria-readonly="true"><small class="tf-muted">This value is managed from the company profile.</small></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><x-phone-input name="phone" :value="old('phone', $business->phone)" :error="$errors->first('phone')" /></div>
                    <div class="col-md-6"><label class="form-label" for="footerWebsite">Website</label><input id="footerWebsite" name="website" type="url" class="form-control @error('website') is-invalid @enderror" maxlength="255" value="{{ old('website', $business->website) }}" placeholder="https://example.com">@error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label class="form-label" for="footerAddress">Address</label><input id="footerAddress" name="address" class="form-control @error('address') is-invalid @enderror" maxlength="1000" value="{{ old('address', $business->address) }}">@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="footerTaxNumber">NTN / Tax Number</label><input id="footerTaxNumber" name="tax_number" class="form-control @error('tax_number') is-invalid @enderror" maxlength="100" value="{{ old('tax_number', $business->tax_number) }}">@error('tax_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label">Powered by TradeFlow</label><input class="form-control" value="{{ $footer->powered_by_text ?: 'Powered by TradeFlow' }}" readonly aria-readonly="true"><small class="tf-muted">This value is managed from the company profile.</small></div>
                @endif

                <div class="col-12"><h2 class="h6 mb-0">Document Footer</h2></div>
                <div class="col-md-6"><label class="form-label" for="footerTitle">Footer Title</label><input id="footerTitle" name="footer_title" class="form-control @error('footer_title') is-invalid @enderror" maxlength="255" value="{{ old('footer_title', $footer->footer_title) }}">@error('footer_title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="footerMessage">Footer Message</label><input id="footerMessage" name="footer_message" class="form-control @error('footer_message') is-invalid @enderror" maxlength="500" value="{{ old('footer_message', $footer->footer_message) }}">@error('footer_message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><h2 class="h6 mb-2">Show on Documents</h2><div class="row g-2">
                    @foreach(['show_company_name' => 'Business Name', 'show_footer_title' => 'Footer Title', 'show_footer_message' => 'Footer Message', 'show_phone' => 'Phone', 'show_email' => 'Business Email', 'show_address' => 'Address', 'show_website' => 'Website', 'show_tax_number' => 'NTN / Tax Number', 'show_powered_by' => 'Powered by TradeFlow'] as $field => $label)
                        <div class="col-md-6"><input type="hidden" name="{{ $field }}" value="0"><label class="form-check border rounded p-2 h-100"><input class="form-check-input me-2" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $footer->{$field}))> {{ $label }}</label></div>
                    @endforeach
                </div></div>
                <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-tf-primary">Save Footer Settings</button><a class="btn btn-outline-secondary" href="{{ $backRoute }}">Back</a></div>
            </form>

            @if($adminMode && $resetAction)
                <form method="POST" action="{{ $resetAction }}" class="mt-3" onsubmit="return confirm('Reset this company footer to its default values?')">@csrf @method('PATCH')<button class="btn btn-outline-warning btn-sm">Reset to Company Defaults</button></form>
            @endif

        </div>
        <div class="col-lg-5"><div class="border rounded p-3 bg-light h-100"><h2 class="h6 mb-3">Footer Preview</h2><x-document-footer :business="$business" :footer="$footer" /></div></div>
    </div>
</div>
