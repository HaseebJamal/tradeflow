<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delivery Sheet {{ 'DEL-'.$delivery->id }}</title>
    <style>
        @page { margin: 16mm; }
        :root { color: #172033; font-family: Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { background: #eef2f7; margin: 0; padding: 28px 16px; }
        .sheet { background: #fff; box-shadow: 0 12px 34px rgba(15, 30, 55, .12); margin: 0 auto; max-width: 210mm; min-height: 267mm; padding: 16mm; }
        .screen-actions { display: flex; justify-content: flex-end; margin: 0 auto 12px; max-width: 210mm; }
        .print-button { background: #2563eb; border: 0; border-radius: 8px; color: #fff; cursor: pointer; font-size: 13px; font-weight: 700; padding: 10px 16px; }
        .header { border-bottom: 2px solid #2563eb; display: table; padding-bottom: 13px; width: 100%; }
        .header > div { display: table-cell; vertical-align: top; width: 50%; }
        .business-name { color: #0b1f3a; font-size: 22px; font-weight: 700; margin: 0 0 4px; }
        .muted { color: #60708a; font-size: 10px; line-height: 1.5; margin: 0; }
        .document-label { color: #2563eb; font-size: 11px; font-weight: 700; letter-spacing: 1.2px; margin: 0 0 5px; text-align: right; }
        .document-number { color: #0b1f3a; font-size: 17px; font-weight: 700; margin: 0; text-align: right; }
        .document-meta { color: #60708a; font-size: 10px; line-height: 1.6; margin: 4px 0 0; text-align: right; }
        .status { background: #eef3ff; border: 1px solid #c6d6ff; border-radius: 12px; color: #174eb5; display: inline-block; font-size: 9px; font-weight: 700; padding: 3px 8px; }
        .status.is-delivered { background: #e8f8ef; border-color: #b7e6c9; color: #167244; }.status.is-cancelled { background: #fff0f1; border-color: #f5c7cd; color: #b62d40; }
        .info-grid { display: table; margin: 16px 0; table-layout: fixed; width: 100%; }
        .info-card { display: table-cell; padding: 0 12px 0 0; vertical-align: top; width: 50%; }.info-card + .info-card { border-left: 1px solid #dce4ef; padding-left: 16px; padding-right: 0; }
        .info-title { color: #0b1f3a; font-size: 11px; font-weight: 700; letter-spacing: .4px; margin: 0 0 7px; text-transform: uppercase; }
        .info-row { display: table; font-size: 10px; line-height: 1.55; width: 100%; }.info-label { color: #60708a; display: table-cell; width: 40%; }.info-value { display: table-cell; overflow-wrap: anywhere; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; } thead { display: table-header-group; } tr { page-break-inside: avoid; }
        th { background: #eff4fb; border-bottom: 1px solid #cfdbea; color: #385172; font-size: 9px; letter-spacing: .35px; padding: 8px 7px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #e2e8f0; font-size: 10px; overflow-wrap: anywhere; padding: 8px 7px; vertical-align: top; }.qty,.amount { text-align: right; white-space: nowrap; }.unit { text-align: center; }
        .summary { margin-left: auto; margin-top: 14px; width: 48%; }.summary td { border: 0; padding: 4px 0; }.summary td:last-child { text-align: right; }.summary .total td { border-top: 1px solid #9aaac0; color: #0b1f3a; font-size: 13px; font-weight: 700; padding-top: 8px; }
        .confirmation { border-top: 1px solid #dce4ef; margin-top: 28px; padding-top: 14px; }.confirmation h2 { color: #0b1f3a; font-size: 11px; letter-spacing: .4px; margin: 0 0 14px; text-transform: uppercase; }.signature-grid { display: table; table-layout: fixed; width: 100%; }.signature { display: table-cell; padding-right: 16px; vertical-align: top; width: 50%; }.signature:nth-child(even) { padding-left: 16px; padding-right: 0; }.signature span { color: #60708a; display: block; font-size: 9px; margin-bottom: 19px; }.signature b { border-bottom: 1px solid #8fa0b6; display: block; height: 1px; }
        .footer { border-top: 1px solid #dce4ef; color: #60708a; font-size: 9px; margin-top: 26px; padding-top: 10px; text-align: center; }.footer strong { color: #30425e; display: block; margin-bottom: 3px; }
        @media print { @page { margin: 13mm; } body { background: #fff; padding: 0; } .screen-actions { display: none !important; } .sheet { box-shadow: none; margin: 0; max-width: none; min-height: 0; padding: 0; width: auto; } }
    </style>
</head>
<body>
@php
    $order = $delivery->sourceOrder();
    $invoice = $delivery->sourceInvoice();
    $customer = $delivery->customer ?? $order?->customer;
    $items = $order?->items ?? collect();
    $business = $delivery->business;
    $footer = $business?->documentFooter;
    $total = (float) ($order?->grand_total ?: $order?->total ?: $delivery->amount ?: 0);
    $statusClass = match($delivery->status) { 'Delivered' => 'is-delivered', 'Cancelled' => 'is-cancelled', default => '' };
    $quantity = static fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
@endphp
<div class="screen-actions"><button class="print-button" type="button" onclick="window.print()">Print</button></div>
<main class="sheet">
    <header class="header">
        <div><h1 class="business-name">{{ $business?->business_name }}</h1><p class="muted">@if($business?->phone){{ $business->phone }}@endif @if($business?->owner?->email)&middot; {{ $business->owner->email }}@endif</p></div>
        <div><p class="document-label">Delivery Sheet</p><p class="document-number">DEL-{{ $delivery->id }}</p><p class="document-meta">Invoice: {{ $invoice?->invoice_number ?? $order?->order_number ?? 'Not available' }}<br>{{ ($delivery->assigned_at ?: $delivery->created_at)?->format('n/j/Y, g:i A') }}<br><span class="status {{ $statusClass }}">{{ $delivery->status }}</span></p></div>
    </header>
    <section class="info-grid">
        <div class="info-card"><h2 class="info-title">Delivery Information</h2><div class="info-row"><span class="info-label">Delivery number</span><span class="info-value">DEL-{{ $delivery->id }}</span></div><div class="info-row"><span class="info-label">Invoice</span><span class="info-value">{{ $invoice?->invoice_number ?? $order?->order_number ?? 'Not available' }}</span></div><div class="info-row"><span class="info-label">Assigned staff</span><span class="info-value">{{ $delivery->staff?->name ?? 'Not Assigned' }}</span></div><div class="info-row"><span class="info-label">Status</span><span class="info-value">{{ $delivery->status }}</span></div></div>
        <div class="info-card"><h2 class="info-title">Customer Information</h2><div class="info-row"><span class="info-label">Customer</span><span class="info-value">{{ $customer?->display_name ?? 'Walk-in Customer' }}</span></div><div class="info-row"><span class="info-label">Phone</span><span class="info-value">{{ $customer?->phone ?: 'Not provided' }}</span></div><div class="info-row"><span class="info-label">Address</span><span class="info-value">{{ $delivery->address ?: $customer?->address ?: 'Not provided' }}</span></div></div>
    </section>
    <table><thead><tr><th style="width:47%">Product</th><th class="qty" style="width:12%">Qty</th><th class="unit" style="width:11%">Unit</th><th class="amount" style="width:15%">Rate</th><th class="amount" style="width:15%">Total</th></tr></thead><tbody>@forelse($items as $item)<tr><td>{{ $item->product_name_snapshot ?: $item->product?->name ?: 'Product' }}</td><td class="qty">{{ $quantity($item->quantity) }}</td><td class="unit">{{ $item->unit ?: $item->product?->unit ?: '—' }}</td><td class="amount">Rs {{ number_format($item->unit_price ?: $item->price ?: 0) }}</td><td class="amount">Rs {{ number_format($item->line_total ?: $item->total ?: 0) }}</td></tr>@empty<tr><td colspan="5" style="color:#60708a;text-align:center;padding:16px">No delivery items available.</td></tr>@endforelse</tbody></table>
    <table class="summary"><tr><td>Subtotal</td><td>Rs {{ number_format($total) }}</td></tr><tr class="total"><td>Total</td><td>Rs {{ number_format($total) }}</td></tr></table>
    <section class="confirmation"><h2>Delivery Confirmation</h2><div class="signature-grid"><div class="signature"><span>Delivered By</span><b></b></div><div class="signature"><span>Received By</span><b></b></div></div><div class="signature" style="padding:18px 0 0"><span>Customer Signature</span><b></b></div><div class="signature-grid" style="margin-top:18px"><div class="signature"><span>Date / Time</span><b></b></div><div class="signature"><span>Remarks</span><b></b></div></div></section>
    <footer class="footer">
        <strong>{{ $business?->business_name }}</strong>
        @if($footer?->show_footer_title && $footer?->footer_title)
            {{ $footer->footer_title }}<br>
        @endif
        @if($footer?->show_footer_message && $footer?->footer_message)
            {{ $footer->footer_message }}<br>
        @endif
        Powered by Profit Point
    </footer>
</main>
</body>
</html>
