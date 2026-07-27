@props([
    'business',
    'footer',
    'action',
    'method' => 'PUT',
    'backRoute',
    'resetAction' => null,
    'lockedCompany' => false,
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
                <div class="col-md-6"><label class="form-label" for="footerTitle">Footer Title</label><input id="footerTitle" name="footer_title" class="form-control @error('footer_title') is-invalid @enderror" maxlength="255" value="{{ old('footer_title', $footer->footer_title) }}">@error('footer_title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="footerMessage">Footer Message</label><input id="footerMessage" name="footer_message" class="form-control @error('footer_message') is-invalid @enderror" maxlength="500" value="{{ old('footer_message', $footer->footer_message) }}">@error('footer_message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><h2 class="h6 mb-2">Show on documents</h2><div class="row g-2">
                    @foreach(['show_company_name' => 'Company Name', 'show_address' => 'Address', 'show_phone' => 'Phone', 'show_email' => 'Business Email', 'show_website' => 'Website', 'show_tax_number' => 'NTN / Tax Number', 'show_powered_by' => 'Powered by TradeFlow'] as $field => $label)
                        <div class="col-md-6"><input type="hidden" name="{{ $field }}" value="0"><label class="form-check border rounded p-2 h-100"><input class="form-check-input me-2" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $footer->{$field}))> {{ $label }}</label></div>
                    @endforeach
                </div></div>
                <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-tf-primary">Save Footer Settings</button><a class="btn btn-outline-secondary" href="{{ $backRoute }}">Back</a></div>
            </form>
            @if($resetAction)
                <form method="POST" action="{{ $resetAction }}" class="mt-3" onsubmit="return confirm('Reset this company footer to its default values?')">@csrf @method('PATCH')<button class="btn btn-outline-warning btn-sm">Reset to Company Defaults</button></form>
            @endif
        </div>
        <div class="col-lg-5"><div class="border rounded p-3 bg-light h-100"><h2 class="h6 mb-3">Footer Preview</h2><x-document-footer :business="$business" :footer="$footer" /></div></div>
    </div>
</div>
