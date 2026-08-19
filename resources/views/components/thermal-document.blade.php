@props([
    'business',
    'title',
    'number',
    'date' => null,
    'cashier' => null,
    'partyLabel' => null,
    'partyName' => null,
    'partyDetails' => null,
    'metadata' => [],
    'items' => [],
    'totals' => [],
    'footerLines' => [],
    'footer' => null,
    'paper' => 80,
    'pdf' => false,
])
@php
    $paper = (int) $paper === 58 ? 58 : 80;
    $businessName = data_get($business, 'business_name') ?: data_get($business, 'name') ?: 'TradeFlow';
    $fontSize = $paper === 58 ? '8px' : '10px';
    $headerNameSize = $paper === 58 ? '11px' : '14px';
@endphp
<style>
    /*
     * The document itself owns the printable safe area.  Thermal printer
     * drivers do not consistently honour @page margins, so relying on page
     * margins can leave the first/last text flush against a receipt edge.
     */
    @page { margin: 0; }
    html, body { box-sizing: border-box; margin: 0; min-width: 0; padding: 0; width: 100%; }
    /*
     * DomPDF calculates a width:100% element before applying its padding.
     * Keep the paper wrapper unpadded and put the safe area in an automatic-
     * width child so both left and right margins are subtracted from the
     * configured 58mm/80mm paper width.
     */
    .tf-thermal-document { box-sizing: border-box; min-width: 0; overflow: visible !important; width: 100%; max-width: {{ $paper }}mm; margin: {{ $pdf ? '0 auto' : '1rem auto' }}; padding: 0; background: #fff; color: #111; font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: {{ $fontSize }}; line-height: 1.35; }
    .tf-thermal-document *, .tf-thermal-document *::before, .tf-thermal-document *::after { box-sizing: border-box; }
    .tf-thermal-document__content { box-sizing: border-box; margin: 3mm 3.5mm 4mm; max-width: 100%; min-width: 0; }
    .tf-thermal-document__header { text-align: center; }
    .tf-thermal-document__name { margin: 0; font-size: {{ $headerNameSize }}; font-weight: 700; overflow-wrap: anywhere; }
    .tf-thermal-document__muted { color: #4b5563; max-width: 100%; min-width: 0; overflow: visible !important; overflow-wrap: anywhere; white-space: normal; word-break: break-word; }
    .tf-thermal-document__rule { border: 0; border-top: 1px dashed #555; margin: 3mm 0; }
    /*
     * Keep percentages at exactly 100%. The visual gap lives in an inner
     * element, not as table-cell padding, which prevents it from widening
     * the printable row beyond the thermal paper.
     */
    .tf-thermal-document__row { box-sizing: border-box; display: table; max-width: 100%; min-width: 0; page-break-inside: avoid; table-layout: fixed; width: 100%; margin: 1mm 0; }
    .tf-thermal-document__label, .tf-thermal-document__value { box-sizing: border-box; display: table-cell; max-width: 100%; min-width: 0; overflow: visible !important; overflow-wrap: anywhere; vertical-align: top; white-space: normal; word-break: break-word; word-wrap: break-word; }
    .tf-thermal-document__label { padding: 0; width: 40%; }
    .tf-thermal-document__value { padding: 0; text-align: right; width: 60%; }
    .tf-thermal-document__label-content { display: block; margin-right: 3mm; min-width: 0; overflow-wrap: anywhere; }
    .tf-thermal-document__value-content { display: block; max-width: 100%; min-width: 0; overflow-wrap: anywhere; text-align: right; }
    /* Customer and supplier names need the full paper width. Keeping a long
       party name in the regular 60% value column makes it wrap into a tall,
       hard-to-read right-aligned stack on receipts and invoices. */
    .tf-thermal-document__party { display: block; }
    .tf-thermal-document__party .tf-thermal-document__label,
    .tf-thermal-document__party .tf-thermal-document__value { display: block; text-align: left; width: 100%; }
    .tf-thermal-document__party .tf-thermal-document__label-content { margin: 0 0 .75mm; }
    .tf-thermal-document__party .tf-thermal-document__value-content { text-align: left; }
    .tf-thermal-document__items { box-sizing: border-box; max-width: 100%; min-width: 0; width: 100%; }
    .tf-thermal-document__item { border-bottom: 1px dashed #9ca3af; overflow: visible !important; page-break-inside: avoid; padding: 1.5mm 0; }
    .tf-thermal-document__item-name { font-weight: 700; overflow-wrap: anywhere; white-space: normal; word-break: break-word; }
    .tf-thermal-document__item-meta { color: #4b5563; font-size: {{ $paper === 58 ? '7px' : '8px' }}; margin-top: .5mm; overflow-wrap: anywhere; white-space: normal; word-break: break-word; }
    .tf-thermal-document__item-details { box-sizing: border-box; display: table; max-width: 100%; min-width: 0; table-layout: fixed; width: 100%; color: #4b5563; font-size: {{ $paper === 58 ? '7px' : '8px' }}; }
    .tf-thermal-document__item-calculation, .tf-thermal-document__item-amount { box-sizing: border-box; display: table-cell; max-width: 100%; min-width: 0; overflow: visible !important; vertical-align: top; white-space: normal; word-break: break-word; }
    .tf-thermal-document__item-calculation { padding: 0; width: 58%; word-wrap: break-word; }
    .tf-thermal-document__item-amount { padding: 0; text-align: right; width: 42%; word-wrap: break-word; }
    .tf-thermal-document__item-calculation-content { display: block; margin-right: 2mm; min-width: 0; overflow-wrap: anywhere; }
    .tf-thermal-document__item-amount-content { display: block; max-width: 100%; min-width: 0; overflow-wrap: anywhere; text-align: right; }
    .tf-thermal-document__footer-lines { margin-top: 3mm; text-align: center; }
    .tf-thermal-document__footer-lines > div { max-width: 100%; overflow-wrap: anywhere; white-space: normal; word-break: break-word; }
    .tf-thermal-document .tf-document-footer { box-sizing: border-box; margin: 4mm 0 0 !important; max-width: 100%; min-width: 0; overflow-wrap: anywhere; }
    .tf-thermal-document__total { font-size: {{ $paper === 58 ? '10px' : '12px' }}; font-weight: 700; border-top: 1px solid #111; margin-top: 2mm; padding-top: 2mm; }
</style>
@if(! $pdf)
<style media="screen">
    /* Desktop preview only: keep printed and PDF thermal dimensions untouched. */
    .tf-thermal-document {
        width: min(calc(100vw - 3rem), 25rem);
        max-width: 25rem;
        margin: 0;
        padding: 1.5rem 1.35rem;
        border: 1px solid #dbe3ef;
        border-radius: .2rem;
        box-shadow: 0 .75rem 1.75rem rgba(15, 23, 42, .12);
        font-size: .875rem;
        line-height: 1.5;
    }
    .tf-thermal-document__content { margin: 0; }
    .tf-thermal-document__name { font-size: 1.15rem; }
    .tf-thermal-document__muted { font-size: .78rem; }
    .tf-thermal-document__rule { margin: .9rem 0; }
    .tf-thermal-document__row { margin: .4rem 0; }
    .tf-thermal-document__label { width: 40%; }
    .tf-thermal-document__value { width: 60%; }
    .tf-thermal-document__item { padding: .7rem 0; }
    .tf-thermal-document__item-meta { font-size: .78rem; margin-top: .2rem; }
    .tf-thermal-document__item-details { font-size: .78rem; }
    .tf-thermal-document__item-calculation { width: 58%; }
    .tf-thermal-document__item-amount { width: 42%; }
    .tf-thermal-document__total { font-size: 1rem; margin-top: .75rem; padding-top: .75rem; }
    @media (max-width: 420px) {
        .tf-thermal-document { width: calc(100vw - 2rem); padding: 1.15rem 1rem; }
    }
</style>
<style media="print">
    @page { margin: 0; }
    body * { visibility: hidden; }
    .tf-thermal-document, .tf-thermal-document * { visibility: visible; }
    html, body { height: auto !important; overflow: visible !important; }
    .tf-thermal-document { margin: 0 auto; max-width: {{ $paper }}mm; overflow: visible !important; padding: 0; position: static; width: 100%; }
</style>
@endif
<section class="tf-thermal-document" aria-label="{{ $title }} {{ $number }}">
    <div class="tf-thermal-document__content">
    <header class="tf-thermal-document__header">
        <h1 class="tf-thermal-document__name">{{ $businessName }}</h1>
        <div><strong>{{ $number }}</strong></div>
        @if($date)<div class="tf-thermal-document__muted">{{ $date }}</div>@endif
        @if(filled($cashier))<div class="tf-thermal-document__muted">Served by: {{ $cashier }}</div>@endif
    </header>

    @if($partyLabel || $partyName || filled($metadata))
        <hr class="tf-thermal-document__rule">
        @if($partyLabel || $partyName)<div class="tf-thermal-document__row tf-thermal-document__party"><span class="tf-thermal-document__label"><span class="tf-thermal-document__label-content">{{ $partyLabel }}</span></span><strong class="tf-thermal-document__value"><span class="tf-thermal-document__value-content">{{ $partyName }}</span></strong></div>@endif
        @if($partyDetails)<div class="tf-thermal-document__muted">{{ $partyDetails }}</div>@endif
        @foreach($metadata as $label => $value)
            @if(filled($value))<div class="tf-thermal-document__row"><span class="tf-thermal-document__label"><span class="tf-thermal-document__label-content">{{ $label }}</span></span><span class="tf-thermal-document__value"><span class="tf-thermal-document__value-content">{{ $value }}</span></span></div>@endif
        @endforeach
    @endif

    <hr class="tf-thermal-document__rule">
    <div class="tf-thermal-document__items">
        @foreach($items as $item)
            <div class="tf-thermal-document__item">
                <div class="tf-thermal-document__item-name">{{ $item['name'] }}</div>
                @if(filled($item['meta'] ?? null))<div class="tf-thermal-document__item-meta">{{ $item['meta'] }}</div>@endif
                <div class="tf-thermal-document__item-details">
                    <span class="tf-thermal-document__item-calculation"><span class="tf-thermal-document__item-calculation-content">{{ $item['quantity'] }} &times; {{ $item['rate'] }}</span></span>
                    <strong class="tf-thermal-document__item-amount"><span class="tf-thermal-document__item-amount-content">{{ $item['amount'] }}</span></strong>
                </div>
            </div>
        @endforeach
    </div>

    <hr class="tf-thermal-document__rule">
    @foreach($totals as $total)
        @if($total['show'] ?? true)<div class="tf-thermal-document__row {{ ($total['emphasis'] ?? false) ? 'tf-thermal-document__total' : '' }}"><span class="tf-thermal-document__label"><span class="tf-thermal-document__label-content">{{ $total['label'] }}</span></span><strong class="tf-thermal-document__value"><span class="tf-thermal-document__value-content">{{ $total['amount'] }}</span></strong></div>@endif
    @endforeach

    @if(filled($footerLines))
        <div class="tf-thermal-document__footer-lines">
            @foreach($footerLines as $line)
                @if(filled($line))<div>{{ $line }}</div>@endif
            @endforeach
        </div>
    @endif

    <x-document-footer :business="$business" :footer="$footer" thermal />

    </div>
</section>
