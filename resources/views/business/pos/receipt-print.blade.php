<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->invoice?->invoice_number ?? $order->order_number }}</title>
    <script src="{{ asset('js/pos-print.js') }}?v={{ filemtime(public_path('js/pos-print.js')) }}" defer></script>
</head>
<body data-pos-print="1">
    @include('business.pos._thermal-receipt', ['order' => $order, 'paper' => $paper ?? 80])
</body>
</html>
