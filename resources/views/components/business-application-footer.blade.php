@props(['business'])

@php($businessName = data_get($business, 'business_name') ?: data_get($business, 'name'))

@if(filled($businessName))
    <footer class="tf-business-application-footer d-print-none" aria-label="Business application footer">
        <span>&copy; {{ now()->year }} {{ $businessName }}</span>
        <span class="tf-business-application-footer__separator" aria-hidden="true">&middot;</span>
        <span>Powered by TradeFlow</span>
    </footer>
@endif
