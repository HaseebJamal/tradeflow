@props(['alert'])

@php
    $contactMessage = 'Hello, my Profit Point '.$alert['kind'].' is ending soon. I would like to continue my access.';
    $whatsAppUrl = app(\App\Services\PlatformSettingsService::class)->whatsAppUrl($contactMessage);
@endphp

<section class="tf-access-expiry-alert mb-4" role="alert" data-tf-access-expiry-alert data-dismiss-key="{{ hash('sha256', session()->getId().'|'.$alert['dismiss_key']) }}">
    <div class="tf-access-expiry-alert__content">
        <span class="tf-access-expiry-alert__icon" aria-hidden="true"><i class="bi bi-exclamation-triangle-fill"></i></span>
        <div>
            <strong>{{ $alert['title'] }}</strong>
            <p class="mb-0">{{ $alert['message'] }} Ends on {{ $alert['ends_at']->format('n/j/Y') }}.</p>
        </div>
    </div>
    <div class="tf-access-expiry-alert__actions">
        @if($whatsAppUrl)
            <a class="btn btn-sm btn-outline-warning tf-access-expiry-alert__contact" href="{{ $whatsAppUrl }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Contact Now</a>
        @endif
        <button class="btn btn-sm btn-link" type="button" data-tf-dismiss-access-expiry-alert>Dismiss</button>
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-tf-access-expiry-alert]').forEach(function (alert) {
        var key = 'tradeflow_access_expiry_alert:' + alert.dataset.dismissKey;
        if (sessionStorage.getItem(key) === 'dismissed') {
            alert.remove();
            return;
        }

        alert.querySelector('[data-tf-dismiss-access-expiry-alert]')?.addEventListener('click', function () {
            sessionStorage.setItem(key, 'dismissed');
            alert.remove();
        });
    });
});
</script>
@endpush
