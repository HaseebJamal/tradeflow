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
@php
    $footerService = app(\App\Services\BusinessDocumentFooterService::class);
    $platformPoweredByText = $footerService->displayedPoweredByText($footer);
    $isBusinessFooterPage = ! $adminMode && ! $lockedCompany;
    $footerPreviewAddress = trim(implode(', ', array_filter([$business->address, $business->city])));
    $footerPreviewTitle = trim((string) old('footer_title', $footer->footer_title));
    $footerPreviewMessage = trim((string) old('footer_message', $footer->footer_message));
    $footerPreviewVisibility = collect(['show_company_name', 'show_footer_title', 'show_footer_message', 'show_phone', 'show_email', 'show_address', 'show_website'])
        ->mapWithKeys(fn (string $field) => [$field => (bool) old($field, $footer->{$field})])
        ->all();
    $footerPreviewVisibility['show_powered_by'] = true;
    $footerPreviewShowsTitle = $footerPreviewVisibility['show_footer_title']
        && filled($footerPreviewTitle)
        && (! $footerPreviewVisibility['show_company_name'] || strcasecmp($footerPreviewTitle, (string) $business->business_name) !== 0);
@endphp
<div @class(['tf-card p-4', 'tf-business-receipt-footer' => $isBusinessFooterPage])>
    <div class="row g-4">
        <div class="col-lg-7">
            <form method="POST" action="{{ $action }}" class="row g-3" data-tf-confirm-message="These changes will update the footer shown on your business documents." data-tf-confirm-title="Save footer settings?" data-tf-confirm-button="Save Changes" data-tf-confirm-icon="question" data-tf-confirm-color="#2563eb" data-tf-confirm-saving-text="Saving...">
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
                    <div class="col-md-6"><label class="form-label" for="poweredByText">Platform Footer Text</label><input id="poweredByText" name="powered_by_text" class="form-control @error('powered_by_text') is-invalid @enderror" maxlength="100" value="{{ old('powered_by_text', $platformPoweredByText) }}">@error('powered_by_text')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @else
                    <div class="col-12"><h2 class="h6 mb-0">Company Details Used by Footer</h2></div>
                    <div class="col-md-6"><label class="form-label">Company Name</label><input class="form-control tf-footer-profile-field" value="{{ $business->business_name }}" readonly aria-readonly="true"><small class="tf-muted"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i>Managed from company profile.</small></div>
                    <div class="col-md-6"><label class="form-label">Business Email</label><input class="form-control tf-footer-profile-field" value="{{ $business->owner?->email }}" readonly aria-readonly="true"><small class="tf-muted"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i>Managed from company profile.</small></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><x-phone-input name="phone" :value="old('phone', $business->phone)" :error="$errors->first('phone')" /></div>
                    <div class="col-md-6"><label class="form-label" for="footerWebsite">Website</label><input id="footerWebsite" name="website" type="url" class="form-control @error('website') is-invalid @enderror" maxlength="255" value="{{ old('website', $business->website) }}" placeholder="https://example.com">@error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label class="form-label" for="footerAddress">Address</label><input id="footerAddress" name="address" class="form-control @error('address') is-invalid @enderror" maxlength="1000" value="{{ old('address', $business->address) }}">@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label class="form-label">{{ $platformPoweredByText }}</label><input class="form-control tf-footer-profile-field" value="{{ $platformPoweredByText }}" readonly aria-readonly="true"><small class="tf-muted"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i>Managed by platform branding.</small></div>
                @endif

                <div class="col-12"><h2 class="h6 mb-0">Document Footer</h2></div>
                <div class="col-md-6"><label class="form-label" for="footerTitle">Footer Title</label><input id="footerTitle" name="footer_title" class="form-control @error('footer_title') is-invalid @enderror" maxlength="255" value="{{ old('footer_title', $footer->footer_title) }}">@error('footer_title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="footerMessage">Footer Message</label><input id="footerMessage" name="footer_message" class="form-control @error('footer_message') is-invalid @enderror" maxlength="500" value="{{ old('footer_message', $footer->footer_message) }}">@error('footer_message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><h2 class="h6 mb-2">Show on Documents</h2><div class="row g-2">
                    @foreach(['show_company_name' => 'Business Name', 'show_footer_title' => 'Footer Title', 'show_footer_message' => 'Footer Message', 'show_phone' => 'Phone', 'show_email' => 'Business Email', 'show_address' => 'Address', 'show_website' => 'Website'] as $field => $label)
                        @php($checkboxId = 'footer_visibility_'.$field)
                        <div class="col-md-6">
                            <input type="hidden" name="{{ $field }}" value="0">
                            <div class="form-check tf-footer-visibility-option p-2 h-100 d-flex align-items-center">
                                <input id="{{ $checkboxId }}" class="form-check-input flex-shrink-0 m-0 me-2" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $footer->{$field}))>
                                <label class="form-check-label flex-grow-1" for="{{ $checkboxId }}">{{ $label }}</label>
                            </div>
                        </div>
                    @endforeach
                    <div class="col-md-6">
                        <input type="hidden" name="show_powered_by" value="1">
                        <div class="form-check tf-footer-visibility-option p-2 h-100 d-flex align-items-center">
                            <input id="footer_visibility_show_powered_by" class="form-check-input flex-shrink-0 m-0 me-2" type="checkbox" checked disabled aria-describedby="footerPoweredByLock">
                            <label class="form-check-label flex-grow-1" for="footer_visibility_show_powered_by">{{ $platformPoweredByText }}</label>
                        </div>
                        <small id="footerPoweredByLock" class="tf-muted d-block mt-1">Always shown on documents.</small>
                    </div>
                </div></div>
                <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-tf-primary">Save Footer Settings</button><a class="btn btn-outline-secondary" href="{{ $backRoute }}">Back</a></div>
            </form>

            @if($adminMode && $resetAction)
                <form method="POST" action="{{ $resetAction }}" class="mt-3" onsubmit="return confirm('Reset this company footer to its default values?')">@csrf @method('PATCH')<button class="btn btn-outline-warning btn-sm">Reset to Company Defaults</button></form>
            @endif

        </div>
        <div @class(['col-lg-5', 'tf-footer-preview-column' => $isBusinessFooterPage])>
            @if($isBusinessFooterPage)
                <aside class="tf-footer-preview-card" data-footer-preview>
                    <h2 class="h6 mb-3">Footer Preview</h2>
                    <div class="tf-footer-preview-sheet">
                        <div class="tf-footer-preview-content">
                            <div data-footer-preview-field="show_company_name" @if(! $footerPreviewVisibility['show_company_name'] || blank($business->business_name)) hidden @endif class="tf-footer-preview-title">{{ $business->business_name }}</div>
                            <div data-footer-preview-field="show_footer_title" @if(! $footerPreviewShowsTitle) hidden @endif data-footer-preview-title class="tf-footer-preview-title">{{ $footerPreviewTitle }}</div>
                            <div data-footer-preview-field="show_footer_message" @if(! $footerPreviewVisibility['show_footer_message'] || blank($footerPreviewMessage)) hidden @endif data-footer-preview-message>{{ $footerPreviewMessage }}</div>
                            <div data-footer-preview-field="show_address" data-footer-preview-city="{{ $business->city }}" @if(! $footerPreviewVisibility['show_address'] || blank($footerPreviewAddress)) hidden @endif>{{ $footerPreviewAddress }}</div>
                            <div data-footer-preview-field="show_phone" @if(! $footerPreviewVisibility['show_phone'] || blank($business->phone)) hidden @endif>{{ $business->phone }}</div>
                            <div data-footer-preview-field="show_email" @if(! $footerPreviewVisibility['show_email'] || blank($business->owner?->email)) hidden @endif>{{ $business->owner?->email }}</div>
                            <div data-footer-preview-field="show_website" @if(! $footerPreviewVisibility['show_website'] || blank($business->website)) hidden @endif>{{ $business->website }}</div>
                            <div data-footer-preview-field="show_powered_by" class="tf-footer-preview-powered">{{ $platformPoweredByText }}</div>
                        </div>
                    </div>
                </aside>
            @else
                <div class="border rounded p-3 bg-light"><h2 class="h6 mb-3">Footer Preview</h2><x-document-footer :business="$business" :footer="$footer" /></div>
            @endif
        </div>
    </div>
</div>

@if($isBusinessFooterPage)
    @push('scripts')
    <script>
    (() => {
        const initFooterPreview = (form) => {
            if (!form || form.dataset.footerPreviewReady === '1') return;
            const preview = form.closest('.tf-business-receipt-footer')?.querySelector('[data-footer-preview]');
            if (!preview) return;
            form.dataset.footerPreviewReady = '1';

            const title = form.querySelector('[name="footer_title"]');
            const message = form.querySelector('[name="footer_message"]');
            const phone = form.querySelector('[name="phone"]');
            const website = form.querySelector('[name="website"]');
            const address = form.querySelector('[name="address"]');
            const companyName = preview.querySelector('[data-footer-preview-field="show_company_name"]')?.textContent.trim() || '';
            const field = (name) => preview.querySelector(`[data-footer-preview-field="${name}"]`);
            const isEnabled = (name) => Boolean(form.querySelector(`[name="${name}"][type="checkbox"]`)?.checked);
            const setVisible = (name, visible) => {
                const target = field(name);
                if (target) target.hidden = !visible;
            };

            const sync = () => {
                const titleValue = title?.value.trim() || '';
                const messageValue = message?.value.trim() || '';
                const titleTarget = preview.querySelector('[data-footer-preview-title]');
                const messageTarget = preview.querySelector('[data-footer-preview-message]');
                if (titleTarget) titleTarget.textContent = titleValue;
                if (messageTarget) messageTarget.textContent = messageValue;
                const addressTarget = field('show_address');
                const addressValue = address?.value.trim() || '';
                const city = addressTarget?.dataset.footerPreviewCity?.trim() || '';
                if (addressTarget) addressTarget.textContent = [addressValue, city].filter(Boolean).join(', ');
                const phoneTarget = field('show_phone');
                if (phoneTarget) phoneTarget.textContent = phone?.value.trim() || '';
                const websiteTarget = field('show_website');
                if (websiteTarget) websiteTarget.textContent = website?.value.trim() || '';

                setVisible('show_company_name', isEnabled('show_company_name') && companyName !== '');
                setVisible('show_footer_title', isEnabled('show_footer_title') && titleValue !== '' && titleValue.toLocaleLowerCase() !== companyName.toLocaleLowerCase());
                setVisible('show_footer_message', isEnabled('show_footer_message') && messageValue !== '');
                ['show_address', 'show_phone', 'show_email', 'show_website'].forEach((name) => {
                    const target = field(name);
                    setVisible(name, isEnabled(name) && Boolean(target?.textContent.trim()));
                });
                setVisible('show_powered_by', true);
            };

            form.addEventListener('input', (event) => {
                if (event.target === title || event.target === message || event.target === website || event.target === address || event.target.closest?.('[data-tf-phone-field]')) sync();
            });
            form.addEventListener('change', (event) => {
                if (event.target.matches('[name^="show_"]')) sync();
            });
            sync();
        };

        document.querySelectorAll('.tf-business-receipt-footer form').forEach(initFooterPreview);
    })();
    </script>
    @endpush
@endif
