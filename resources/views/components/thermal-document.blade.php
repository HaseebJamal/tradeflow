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
    $headerTitleSize = $paper === 58 ? '9px' : '11px';
    $contentWidth = $paper - 6;
@endphp
<style>
    @page { margin: 3mm; }
    html, body { margin: 0; padding: 0; }
    .tf-thermal-document { box-sizing: border-box; width: 100%; max-width: {{ $contentWidth }}mm; margin: {{ $pdf ? '0' : '1rem auto' }}; padding: 0; background: #fff; color: #111; font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: {{ $fontSize }}; line-height: 1.35; }
    .tf-thermal-document *, .tf-thermal-document *::before, .tf-thermal-document *::after { box-sizing: border-box; }
    .tf-thermal-document__header { text-align: center; }
    .tf-thermal-document__name { margin: 0; font-size: {{ $headerNameSize }}; font-weight: 700; overflow-wrap: anywhere; }
    .tf-thermal-document__title { margin: 1mm 0 0; font-size: {{ $headerTitleSize }}; font-weight: 700; text-transform: uppercase; }
    .tf-thermal-document__muted { color: #4b5563; overflow-wrap: anywhere; }
    .tf-thermal-document__rule { border: 0; border-top: 1px dashed #555; margin: 3mm 0; }
    .tf-thermal-document__row { display: table; width: 100%; table-layout: fixed; margin: 1mm 0; }
    .tf-thermal-document__label, .tf-thermal-document__value { display: table-cell; overflow-wrap: anywhere; vertical-align: top; word-wrap: break-word; }
    .tf-thermal-document__label { width: 38%; padding-right: 2mm; }
    .tf-thermal-document__value { overflow-wrap: anywhere; text-align: right; width: 62%; }
    .tf-thermal-document__items { width: 100%; }
    .tf-thermal-document__item { border-bottom: 1px dashed #9ca3af; padding: 1.5mm 0; }
    .tf-thermal-document__item-name { font-weight: 700; overflow-wrap: anywhere; }
    .tf-thermal-document__item-details { display: table; table-layout: fixed; width: 100%; color: #4b5563; font-size: {{ $paper === 58 ? '7px' : '8px' }}; }
    .tf-thermal-document__item-calculation, .tf-thermal-document__item-amount { display: table-cell; vertical-align: top; }
    .tf-thermal-document__item-calculation { overflow-wrap: anywhere; padding-right: 1mm; width: 66%; word-wrap: break-word; }
    .tf-thermal-document__item-amount { overflow-wrap: anywhere; text-align: right; width: 34%; word-wrap: break-word; }
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
    .tf-thermal-document__name { font-size: 1.15rem; }
    .tf-thermal-document__title { font-size: .8rem; }
    .tf-thermal-document__muted { font-size: .78rem; }
    .tf-thermal-document__rule { margin: .9rem 0; }
    .tf-thermal-document__row { margin: .4rem 0; }
    .tf-thermal-document__label { width: 42%; padding-right: .75rem; }
    .tf-thermal-document__value { width: 58%; }
    .tf-thermal-document__item { padding: .7rem 0; }
    .tf-thermal-document__item-details { font-size: .78rem; }
    .tf-thermal-document__item-calculation { width: 64%; padding-right: .5rem; }
    .tf-thermal-document__item-amount { width: 36%; }
    .tf-thermal-document__total { font-size: 1rem; margin-top: .75rem; padding-top: .75rem; }
    @media (max-width: 420px) {
        .tf-thermal-document { width: calc(100vw - 2rem); padding: 1.15rem 1rem; }
    }
</style>
<style media="print">
    @page { margin: 3mm; }
    body * { visibility: hidden; }
    .tf-thermal-document, .tf-thermal-document * { visibility: visible; }
    .tf-thermal-document { left: 0; margin: 0; max-width: none; padding: 0; position: absolute; top: 0; width: 100%; }
</style>
@endif
<section class="tf-thermal-document" aria-label="{{ $title }} {{ $number }}">
    <header class="tf-thermal-document__header">
        <h1 class="tf-thermal-document__name">{{ $businessName }}</h1>
        @if(filled($title))<div class="tf-thermal-document__title">{{ $title }}</div>@endif
        <div><strong>{{ $number }}</strong></div>
        @if($date)<div class="tf-thermal-document__muted">{{ $date }}</div>@endif
    </header>

    @if($partyLabel || $partyName || filled($metadata))
        <hr class="tf-thermal-document__rule">
        @if($partyLabel || $partyName)<div class="tf-thermal-document__row"><span class="tf-thermal-document__label">{{ $partyLabel }}</span><strong class="tf-thermal-document__value">{{ $partyName }}</strong></div>@endif
        @if($partyDetails)<div class="tf-thermal-document__muted">{{ $partyDetails }}</div>@endif
        @foreach($metadata as $label => $value)
            @if(filled($value))<div class="tf-thermal-document__row"><span class="tf-thermal-document__label">{{ $label }}</span><span class="tf-thermal-document__value">{{ $value }}</span></div>@endif
        @endforeach
    @endif

    <hr class="tf-thermal-document__rule">
    <div class="tf-thermal-document__items">
        @foreach($items as $item)
            <div class="tf-thermal-document__item">
                <div class="tf-thermal-document__item-name">{{ $item['name'] }}</div>
                <div class="tf-thermal-document__item-details">
                    <span class="tf-thermal-document__item-calculation">{{ $item['quantity'] }} &times; {{ $item['rate'] }}</span>
                    <strong class="tf-thermal-document__item-amount">{{ $item['amount'] }}</strong>
                </div>
            </div>
        @endforeach
    </div>

    <hr class="tf-thermal-document__rule">
    @foreach($totals as $total)
        @if($total['show'] ?? true)<div class="tf-thermal-document__row {{ ($total['emphasis'] ?? false) ? 'tf-thermal-document__total' : '' }}"><span class="tf-thermal-document__label">{{ $total['label'] }}</span><strong class="tf-thermal-document__value">{{ $total['amount'] }}</strong></div>@endif
    @endforeach

    <x-document-footer :business="$business" :footer="$footer" thermal />

</section>
