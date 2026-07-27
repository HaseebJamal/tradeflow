@extends('layouts.dashboard')
@section('page-title', 'POS Receipt')
@section('page-subtitle', $order->order_number)
@section('content')
@php
    $receiptNumber = $order->invoice?->invoice_number ?? $order->order_number;
    $businessName = $order->business?->business_name ?? $order->business?->name;
@endphp
<div class="d-flex flex-wrap justify-content-end gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="{{ route('business.pos.history') }}"><i class="bi bi-arrow-left me-1"></i>Back to Sales History</a>
    <a class="btn btn-outline-secondary" href="{{ route('business.pos.index') }}"><i class="bi bi-plus-circle me-1"></i>New Sale</a>
    <a class="btn btn-outline-success" href="{{ route('business.pos.receipt.download', ['invoice' => $receiptNumber]) }}"><i class="bi bi-download me-1"></i>Download PDF</a>
    <a class="btn btn-tf-primary" href="{{ route('business.pos.receipt.print', ['invoice' => $receiptNumber]) }}" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print Receipt</a>
</div>

<section class="tf-card p-4 mx-auto" style="max-width:760px">
    <header class="text-center border-bottom pb-3 mb-3">
        @if($order->business?->logo)
            <img src="{{ asset('storage/'.$order->business->logo) }}" alt="{{ $businessName }} logo" style="width:56px;height:56px;object-fit:contain" class="mb-2">
        @endif
        <h2 class="h4 mb-1">{{ $businessName }}</h2>
        <div class="text-muted">Receipt {{ $receiptNumber }}</div>
        <small>{{ $order->order_date?->format('d M Y g:i A') }} | {{ $order->creator?->name }}</small>
    </header>

    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <span>Customer: <strong>{{ $order->customer?->name ?? 'Walk-in Customer' }}</strong></span>
        <span>Payment Method: <strong>{{ $order->payment_method ?? $order->payment_type }}</strong></span>
    </div>

    <x-table><thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Discount</th><th>Tax</th><th>Total</th></tr></thead><tbody>
        @foreach($order->items as $item)
            <tr><td>{{ $item->product_name_snapshot }}</td><td><x-quantity :value="$item->quantity" /></td><td>Rs {{ number_format($item->unit_price ?: $item->price) }}</td><td>{{ $item->discount_rate ?? 0 }}%</td><td>{{ $item->tax_rate ?? 0 }}%</td><td>Rs {{ number_format($item->line_total ?: $item->total) }}</td></tr>
        @endforeach
    </tbody></x-table>

    <div class="ms-auto mt-3" style="max-width:320px">
        <div class="d-flex justify-content-between"><span>Subtotal</span><strong>Rs {{ number_format($order->subtotal) }}</strong></div>
        <div class="d-flex justify-content-between"><span>Discount</span><strong>Rs {{ number_format($order->discount_amount) }}</strong></div>
        <div class="d-flex justify-content-between"><span>Tax</span><strong>Rs {{ number_format($order->tax_amount) }}</strong></div>
        @if($order->cash_received !== null)<div class="d-flex justify-content-between"><span>Cash Received</span><strong>Rs {{ number_format($order->cash_received, 2) }}</strong></div>@endif
        <div class="d-flex justify-content-between"><span>Paid</span><strong>Rs {{ number_format($order->paid_amount) }}</strong></div>
        <div class="d-flex justify-content-between"><span>Due</span><strong>Rs {{ number_format($order->balance) }}</strong></div>
        <div class="d-flex justify-content-between"><span>Change</span><strong>Rs {{ number_format($order->change_amount ?? 0) }}</strong></div>
        <div class="d-flex justify-content-between border-top mt-2 pt-2 h5"><span>Grand Total</span><strong>Rs {{ number_format($order->grand_total ?: $order->total) }}</strong></div>
    </div>
    <footer class="text-center text-muted small border-top mt-4 pt-3">Thank you for your business.</footer>
</section>
@endsection
