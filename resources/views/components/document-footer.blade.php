@props([
    'business',
    'footer' => null,
    'thermal' => false,
    'additionalLines' => [],
])
@php
    if (! $footer) {
        $footer = $business instanceof \App\Models\Business
            ? app(\App\Services\BusinessDocumentFooterService::class)->for($business)
            : new \App\Models\BusinessDocumentFooter([
                'footer_title' => data_get($business, 'business_name') ?: data_get($business, 'name'),
                'footer_message' => 'Thank you for your business!',
                'show_company_name' => true,
                'show_footer_title' => true,
                'show_footer_message' => true,
                'show_address' => true,
                'show_phone' => true,
                'show_email' => true,
                'show_website' => true,
                'show_tax_number' => true,
                'show_powered_by' => true,
            ]);
    }
    $businessName = data_get($business, 'business_name') ?: data_get($business, 'name');
    $address = trim(implode(', ', array_filter([data_get($business, 'address'), data_get($business, 'city')])));
    $email = data_get($business, 'email') ?: data_get($business, 'owner.email');
    $website = data_get($business, 'website');
    $taxNumber = data_get($business, 'tax_number') ?: data_get($business, 'ntn_number');
    $footerTitle = trim((string) $footer->footer_title);
    $showCompanyName = $footer->show_company_name && filled($businessName);
    $showFooterTitle = ($footer->show_footer_title ?? true) && filled($footerTitle)
        && (! $showCompanyName || strcasecmp($footerTitle, (string) $businessName) !== 0);
    $showFooterMessage = ($footer->show_footer_message ?? true) && filled($footer->footer_message);
@endphp
<footer class="tf-document-footer {{ $thermal ? 'tf-document-footer--thermal' : '' }}" style="margin-top: 1rem; text-align: center; color: #4b5563; font-size: {{ $thermal ? '9px' : '.875rem' }}; line-height: 1.45;">
    @if($showCompanyName)<div class="tf-document-footer__title" style="font-weight: 700; color: #111827;">{{ $businessName }}</div>@endif
    @if($showFooterTitle)<div class="tf-document-footer__title" style="font-weight: 700; color: #111827;">{{ $footerTitle }}</div>@endif
    @if($showFooterMessage)<div>{{ $footer->footer_message }}</div>@endif
    @if($footer->show_address && filled($address))<div>{{ $address }}</div>@endif
    @if($footer->show_phone && filled(data_get($business, 'phone')))<div>{{ data_get($business, 'phone') }}</div>@endif
    @if($footer->show_email && filled($email))<div>{{ $email }}</div>@endif
    @if($footer->show_website && filled($website))<div>{{ $website }}</div>@endif
    @if($footer->show_tax_number && filled($taxNumber))<div>Tax / NTN: {{ $taxNumber }}</div>@endif
    @foreach($additionalLines as $line)
        @if(filled($line))<div>{{ $line }}</div>@endif
    @endforeach
    @if($showCompanyName)<div>&copy; {{ now()->year }} {{ $businessName }}</div>@endif
    @if($footer->show_powered_by)<div>{{ $footer->powered_by_text ?: 'Powered by TradeFlow' }}</div>@endif
</footer>
