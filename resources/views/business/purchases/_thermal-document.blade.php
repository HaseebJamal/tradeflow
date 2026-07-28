@php
    $documentDate = $purchase->purchase_date?->format('d M Y g:i A');
@endphp
<x-thermal-document
    :business="$business"
    title="Purchase"
    :number="$purchase->purchase_number"
    :date="$documentDate"
    party-label="Supplier"
    :party-name="$purchase->supplier?->supplier_name"
    :metadata="$metadata"
    :items="$items"
    :totals="$totals"
    :footer="$business->documentFooter"
    :paper="80"
    :pdf="$pdf ?? false"
/>
