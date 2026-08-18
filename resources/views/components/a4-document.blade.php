@props([
    'business' => null,
    'footer' => null,
    'title',
    'reference' => null,
    'date' => null,
    'status' => null,
    'subtitle' => null,
    'compact' => false,
])
@php
    $businessName = data_get($business, 'business_name') ?: data_get($business, 'name') ?: 'Profit Point';
    $businessPhone = data_get($business, 'phone');
    $businessEmail = data_get($business, 'email') ?: data_get($business, 'owner.email');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: {{ $compact ? '13mm 14mm 13mm' : '18mm 14mm 20mm' }}; }
        * { box-sizing: border-box; }
        body { color: #172033; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; line-height: 1.45; margin: 0; }
        .tf-a4-document { width: 100%; }
        .tf-a4-document__header { border-bottom: 2px solid #2563eb; margin-bottom: 14px; padding-bottom: 10px; }
        .tf-a4-document__header-table { border-collapse: collapse; width: 100%; }
        .tf-a4-document__header-table td { border: 0; padding: 0; vertical-align: top; }
        .tf-a4-document__meta { text-align: right; width: 42%; }
        .tf-a4-document__business { color: #0b1f3a; font-size: 20px; font-weight: 700; margin: 0 0 3px; overflow-wrap: anywhere; }
        .tf-a4-document__document-type { color: #1f5eff; font-size: 10px; font-weight: 700; letter-spacing: 1px; margin: 0 0 3px; text-transform: uppercase; }
        .tf-a4-document__muted { color: #64748b; }
        .tf-a4-document__subtitle { color: #52627a; font-size: 9px; margin: 6px 0 0; }
        .tf-a4-document__status { border: 1px solid #bfd2ff; border-radius: 8px; color: #1749bf; display: inline-block; font-size: 8px; font-weight: 700; margin-top: 3px; padding: 2px 6px; }
        .tf-a4-document__summary { border-collapse: separate; border-spacing: 5px; margin: 0 -5px 14px; table-layout: fixed; width: calc(100% + 10px); }
        .tf-a4-document__summary td { background: #f7f9fc; border: 1px solid #dce4ef; border-radius: 5px; padding: 8px; vertical-align: top; }
        .tf-a4-document__summary-label { color: #64748b; display: block; font-size: 8px; margin-bottom: 3px; }
        .tf-a4-document__summary-value { color: #0b1f3a; display: block; font-size: 12px; font-weight: 700; }
        .tf-a4-document__table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .tf-a4-document__table thead { display: table-header-group; }
        .tf-a4-document__table th { background: #edf3ff; border-bottom: 1px solid #bfd2ff; color: #1749bf; font-size: 7.5px; font-weight: 700; letter-spacing: .25px; padding: 6px 5px; text-align: left; text-transform: uppercase; }
        .tf-a4-document__table td { border-bottom: 1px solid #e2e8f0; overflow-wrap: anywhere; padding: 6px 5px; vertical-align: top; word-wrap: break-word; }
        .tf-a4-document__table tr { page-break-inside: avoid; }
        .tf-a4-document__money, .tf-a4-document__quantity { text-align: right !important; white-space: nowrap; }
        .tf-a4-document__empty { color: #64748b; padding: 16px !important; text-align: center; }
        .tf-a4-document__negative { color: #b4233c; }
        .tf-a4-document__positive { color: #177245; }
        .tf-a4-document__warning { color: #9a5c00; }
        .tf-a4-document__footer { border-top: 1px solid #dce4ef; margin-top: 16px; padding-top: 5px; page-break-inside: avoid; }
        .tf-a4-document--compact .tf-a4-document__header { margin-bottom: 9px; padding-bottom: 7px; }
        .tf-a4-document--compact .tf-a4-document__summary { border-spacing: 3px; margin: 0 -3px 9px; width: calc(100% + 6px); }
        .tf-a4-document--compact .tf-a4-document__summary td { padding: 5px; }
        .tf-a4-document--compact h2 { margin-bottom: 4px !important; margin-top: 10px !important; }
        .tf-a4-document--compact .tf-a4-document__table th,
        .tf-a4-document--compact .tf-a4-document__table td { padding-bottom: 4px; padding-top: 4px; }
        .tf-a4-document--compact .tf-a4-document__footer { margin-top: 8px; padding-top: 3px; }
    </style>
</head>
<body>
<main class="tf-a4-document {{ $compact ? 'tf-a4-document--compact' : '' }}">
    <header class="tf-a4-document__header">
        <table class="tf-a4-document__header-table"><tr>
            <td>
                <h1 class="tf-a4-document__business">{{ $businessName }}</h1>
                @if($businessPhone || $businessEmail)
                    <p class="tf-a4-document__muted" style="margin:0; font-size:8.5px;">
                        @if($businessPhone){{ $businessPhone }}@endif
                        @if($businessPhone && $businessEmail)&middot; @endif
                        @if($businessEmail){{ $businessEmail }}@endif
                    </p>
                @endif
            </td>
            <td class="tf-a4-document__meta">
                <p class="tf-a4-document__document-type">{{ $title }}</p>
                @if(filled($reference))<div>{{ $reference }}</div>@endif
                @if(filled($date))<div class="tf-a4-document__muted">{{ $date }}</div>@endif
                @if(filled($status))<span class="tf-a4-document__status">{{ $status }}</span>@endif
            </td>
        </tr></table>
        @if(filled($subtitle))<p class="tf-a4-document__subtitle">{{ $subtitle }}</p>@endif
    </header>

    {{ $slot }}

    <div class="tf-a4-document__footer">
        <x-document-footer :business="$business" :footer="$footer" :compact="$compact" />
    </div>
</main>
</body>
</html>
