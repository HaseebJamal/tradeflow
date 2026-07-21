<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($receiptNumber = $order->invoice?->invoice_number ?? $order->order_number)
    <title>Receipt {{ $receiptNumber }}</title>
    <style>
        body { color: #111827; background: #fff; font-family: Arial, sans-serif; font-size: 13px; }
        .tf-pos-receipt { max-width: 760px; margin: 24px auto; padding: 20px; }
        .logo { width: 56px; height: 56px; object-fit: contain; display: block; margin: 0 auto 8px; }
        .header, footer { text-align: center; } .header { border-bottom: 1px solid #d1d5db; padding-bottom: 12px; margin-bottom: 14px; }
        .meta, .summary { display: flex; justify-content: space-between; gap: 12px; } .summary { max-width: 320px; margin: 16px 0 0 auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; } th, td { border-bottom: 1px solid #e5e7eb; padding: 7px; text-align: left; } th:last-child, td:last-child { text-align: right; }
        .total { border-top: 1px solid #111827; font-size: 16px; font-weight: 700; margin-top: 8px; padding-top: 8px; } footer { border-top: 1px solid #e5e7eb; margin-top: 20px; padding-top: 12px; color: #6b7280; }
        @media print { @page { margin: 8mm; } .tf-pos-receipt { margin: 0; max-width: none; padding: 0; } }
    </style>
    <script src="{{ asset('js/pos-print.js') }}?v={{ filemtime(public_path('js/pos-print.js')) }}" defer></script>
</head>
<body data-pos-print="1">
<main class="tf-pos-receipt">
    <header class="header">
        @if($order->business?->logo)<img class="logo" src="{{ asset('storage/'.$order->business->logo) }}" alt="">@endif
        <h1>{{ $order->business?->business_name ?? $order->business?->name }}</h1>
        <div>Receipt {{ $receiptNumber }}</div>
        <small>{{ $order->order_date?->format('d M Y g:i A') }} | {{ $order->creator?->name }}</small>
    </header>
    <div class="meta"><span>Customer: <strong>{{ $order->customer?->name ?? 'Walk-in Customer' }}</strong></span><span>Payment Method: <strong>{{ $order->payment_method ?? $order->payment_type }}</strong></span></div>
    <table><thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Discount</th><th>Tax</th><th>Total</th></tr></thead><tbody>@foreach($order->items as $item)<tr><td>{{ $item->product_name_snapshot }}</td><td>{{ $item->quantity }}</td><td>Rs {{ number_format($item->unit_price ?: $item->price) }}</td><td>{{ $item->discount_rate ?? 0 }}%</td><td>{{ $item->tax_rate ?? 0 }}%</td><td>Rs {{ number_format($item->line_total ?: $item->total) }}</td></tr>@endforeach</tbody></table>
    <div class="summary"><span>Subtotal</span><strong>Rs {{ number_format($order->subtotal) }}</strong></div><div class="summary"><span>Discount</span><strong>Rs {{ number_format($order->discount_amount) }}</strong></div><div class="summary"><span>Tax</span><strong>Rs {{ number_format($order->tax_amount) }}</strong></div>@if($order->cash_received !== null)<div class="summary"><span>Cash Received</span><strong>Rs {{ number_format($order->cash_received, 2) }}</strong></div>@endif<div class="summary"><span>Paid</span><strong>Rs {{ number_format($order->paid_amount) }}</strong></div><div class="summary"><span>Due</span><strong>Rs {{ number_format($order->balance) }}</strong></div><div class="summary"><span>Change</span><strong>Rs {{ number_format($order->change_amount ?? 0) }}</strong></div><div class="summary total"><span>Grand Total</span><strong>Rs {{ number_format($order->grand_total ?: $order->total) }}</strong></div>
    <footer>Thank you for your business.</footer>
</main>
</body>
</html>
