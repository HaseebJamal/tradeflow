<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Delivery Sheet #DEL-{{ $delivery->id }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="d-flex justify-content-between border-bottom pb-3 mb-4">
        <div><h1 class="h3">{{ $delivery->business?->business_name ?? 'TradeFlow' }} Delivery Sheet</h1><div>#DEL-{{ $delivery->id }}</div></div>
        <button class="btn btn-primary d-print-none" onclick="window.print()">Print</button>
    </div>
    <div class="row mb-4">
        <div class="col"><strong>Invoice:</strong> {{ $delivery->sourceInvoice()?->invoice_number ?? $delivery->sourceOrder()?->order_number }}<br><strong>Staff:</strong> {{ $delivery->staff?->name }}<br><strong>Status:</strong> {{ $delivery->status }}</div>
        <div class="col"><strong>Customer:</strong> {{ $delivery->customer?->display_name }}<br><strong>Phone:</strong> {{ $delivery->customer?->phone }}<br><strong>Address:</strong> {{ $delivery->address }}</div>
    </div>
    <table class="table table-bordered"><thead><tr><th>Product</th><th>Qty</th><th>Rate</th><th>Total</th></tr></thead><tbody>@foreach($delivery->sourceOrder()?->items ?? [] as $item)<tr><td>{{ $item->product_name_snapshot ?: $item->product?->name }}</td><td><x-quantity :value="$item->quantity" /> {{ $item->unit }}</td><td>Rs {{ number_format($item->unit_price ?: $item->price) }}</td><td>Rs {{ number_format($item->line_total ?: $item->total) }}</td></tr>@endforeach</tbody></table>
    <div class="text-end h4">Amount: Rs {{ number_format($delivery->sourceOrder()?->grand_total ?: $delivery->amount) }}</div>
    <x-document-footer :business="$delivery->business" :footer="$delivery->business?->documentFooter" />
</body>
</html>
