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
                    <div class="col-12"><div class="alert alert-info mb-0">You can update the footer title and message directly. Company details and visibility settings require Super Admin approval.</div></div>
                    <div class="col-12"><h2 class="h6 mb-0">Restricted Footer Details</h2></div>
                    <div class="col-md-6"><label class="form-label">Company Name</label><input class="form-control" value="{{ $business->business_name }}" readonly></div>
                    <div class="col-md-6"><label class="form-label">Business Email</label><input class="form-control" value="{{ $business->owner?->email }}" readonly></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" value="{{ $business->phone }}" readonly></div>
                    <div class="col-md-6"><label class="form-label">Website</label><input class="form-control" value="{{ $business->website }}" readonly></div>
                    <div class="col-12"><label class="form-label">Address</label><input class="form-control" value="{{ $business->address }}" readonly></div>
                    <div class="col-md-6"><label class="form-label">NTN / Tax Number</label><input class="form-control" value="{{ $business->tax_number }}" readonly></div>
                    <div class="col-md-6"><label class="form-label">Powered by TradeFlow</label><input class="form-control" value="{{ $footer->show_powered_by ? ($footer->powered_by_text ?: 'Powered by TradeFlow') : 'Hidden' }}" readonly></div>
                @endif

                <div class="col-12"><h2 class="h6 mb-0">Document Footer</h2></div>
                <div class="col-md-6"><label class="form-label" for="footerTitle">Footer Title</label><input id="footerTitle" name="footer_title" class="form-control @error('footer_title') is-invalid @enderror" maxlength="255" value="{{ old('footer_title', $footer->footer_title) }}">@error('footer_title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="footerMessage">Footer Message</label><input id="footerMessage" name="footer_message" class="form-control @error('footer_message') is-invalid @enderror" maxlength="500" value="{{ old('footer_message', $footer->footer_message) }}">@error('footer_message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @if($adminMode)
                    <div class="col-12"><h2 class="h6 mb-2">Show on Documents</h2><div class="row g-2">
                        @foreach(['show_company_name' => 'Company Name', 'show_address' => 'Address', 'show_phone' => 'Phone', 'show_email' => 'Business Email', 'show_website' => 'Website', 'show_tax_number' => 'NTN / Tax Number', 'show_powered_by' => 'Powered by TradeFlow'] as $field => $label)
                            <div class="col-md-6"><input type="hidden" name="{{ $field }}" value="0"><label class="form-check border rounded p-2 h-100"><input class="form-check-input me-2" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $footer->{$field}))> {{ $label }}</label></div>
                        @endforeach
                    </div></div>
                @endif
                <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-tf-primary">Save Footer Settings</button><a class="btn btn-outline-secondary" href="{{ $backRoute }}">Back</a></div>
            </form>

            @if($adminMode && $resetAction)
                <form method="POST" action="{{ $resetAction }}" class="mt-3" onsubmit="return confirm('Reset this company footer to its default values?')">@csrf @method('PATCH')<button class="btn btn-outline-warning btn-sm">Reset to Company Defaults</button></form>
            @endif

            @if(! $adminMode && $changeRequestAction)
                <div class="border-top mt-4 pt-4">
                    <h2 class="h6 mb-1">Request Footer Detail Change</h2>
                    <p class="tf-muted small">Requested values remain inactive until Super Admin approves them.</p>
                    <form method="POST" action="{{ $changeRequestAction }}" class="row g-3">@csrf
                        <div class="col-md-6"><label class="form-label" for="footerRequestField">Detail</label><select id="footerRequestField" name="field" class="form-select @error('field') is-invalid @enderror" required><option value="">Select detail</option>@foreach($changeFields as $field => $label)<option value="{{ $field }}" @selected(old('field') === $field)>{{ $label }}</option>@endforeach</select>@error('field')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-md-6"><label class="form-label" for="footerRequestedValue">Requested Value</label><input id="footerRequestedValue" name="requested_value" class="form-control @error('requested_value') is-invalid @enderror" maxlength="1000" value="{{ old('requested_value') }}" required><small class="tf-muted">Use 1 for visible or 0 for hidden when requesting Powered by visibility.</small>@error('requested_value')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><label class="form-label" for="footerRequestReason">Reason for Change</label><textarea id="footerRequestReason" name="reason" class="form-control @error('reason') is-invalid @enderror" rows="3" minlength="5" maxlength="2000" required>{{ old('reason') }}</textarea>@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><button class="btn btn-outline-primary">Request Footer Detail Change</button></div>
                    </form>
                    @if($pendingRequests->isNotEmpty())
                        <div class="mt-3"><h3 class="h6">Pending Requests</h3>@foreach($pendingRequests as $pendingRequest)<div class="border rounded p-2 mb-2 d-flex flex-wrap justify-content-between gap-2"><span><strong>{{ $changeFields[$pendingRequest->field] ?? $pendingRequest->field }}</strong><span class="tf-muted">: {{ $pendingRequest->requested_value }}</span></span><form method="POST" action="{{ route($cancelRequestRoute, $pendingRequest) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger">Cancel</button></form></div>@endforeach</div>
                    @endif
                </div>
            @endif
        </div>
        <div class="col-lg-5"><div class="border rounded p-3 bg-light h-100"><h2 class="h6 mb-3">Footer Preview</h2><x-document-footer :business="$business" :footer="$footer" /></div></div>
    </div>
</div>
