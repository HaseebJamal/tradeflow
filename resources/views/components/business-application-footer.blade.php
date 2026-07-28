@props(['business'])

@php
    $businessName = data_get($business, 'business_name') ?: data_get($business, 'name');
    $documentFooter = $business instanceof \App\Models\Business
        ? app(\App\Services\BusinessDocumentFooterService::class)->for($business)
        : null;
    $showPoweredBy = $documentFooter?->show_powered_by ?? true;
    $poweredByText = $documentFooter?->powered_by_text ?: 'Powered by TradeFlow';
@endphp

@if(filled($businessName))
    <footer class="tf-business-application-footer d-print-none" aria-label="Business application footer">
        <span>&copy; {{ now()->year }} {{ $businessName }}</span>
        @if($showPoweredBy)
            <span class="tf-business-application-footer__separator" aria-hidden="true">&middot;</span>
            <span>{{ $poweredByText }}</span>
        @endif
    </footer>
@endif
