@extends('layouts.dashboard')
@section('page-title', 'Invoice')
@section('page-subtitle', $invoice->invoice_number ?? 'Invoice')
@section('content')
<div class="mb-3 text-end d-flex gap-2 justify-content-end"><button onclick="window.print()" class="btn btn-tf-primary"><i class="bi bi-printer me-1"></i>Print</button><a href="{{ route('business.invoices.pdf', $order) }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="bi bi-filetype-pdf me-1"></i>View PDF</a><button type="button" class="btn btn-outline-secondary">Share Manually</button></div>
<div class="tf-invoice">
    <div class="d-flex justify-content-between border-bottom pb-3 mb-4"><div><h2 class="tf-brand">TradeFlow</h2><p class="tf-muted mb-0">{{ $order->business?->business_name }}<br>{{ $order->business?->address }}</p></div><div class="text-end"><h1 class="h4">Invoice {{ $invoice->invoice_number }}</h1><p class="tf-muted">{{ $invoice->created_at->format('M d, Y') }}</p></div></div>
    <div class="row mb-4"><div class="col"><strong>Bill To</strong><p class="tf-muted">{{ $order->customer?->business_name ?? $order->customer?->name }}<br>{{ $order->customer?->address }}</p></div><div class="col text-end"><strong>Status</strong><p><span class="tf-badge tf-badge-warning">{{ $order->status }}</span></p></div></div>
    <x-table><thead><tr><th>Item</th><th>Qty</th><th>Rate</th><th>Total</th></tr></thead><tbody>@foreach($order->items as $item)<tr><td>{{ $item->product?->name }}</td><td>{{ $item->quantity }}</td><td>Rs {{ number_format($item->price) }}</td><td>Rs {{ number_format($item->total) }}</td></tr>@endforeach</tbody></x-table>
    <div class="text-end mt-4">
        <div>Subtotal: Rs {{ number_format($order->subtotal) }}</div>
        <div>Discount: {{ number_format($order->discount_percentage ?? $order->discount ?? 0, 2) }}%</div>
        <div>Discount Amount: Rs {{ number_format($order->discount_amount ?? 0) }}</div>
        <div>Paid: Rs {{ number_format($invoice->paid_amount) }}</div>
        <div>Balance: Rs {{ number_format($invoice->balance) }}</div>
        <div class="h3">Grand Total: Rs {{ number_format($order->grand_total ?: $order->total) }}</div>
    </div>
</div>
@endsection
