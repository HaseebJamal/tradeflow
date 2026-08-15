@extends('layouts.dashboard')
@section('page-title', 'Sales Invoices')
@section('page-subtitle', 'Generate, print, and download sales invoices')
@section('content')
<x-table>
    <thead><tr><th>Sale</th><th>Customer</th><th>Total</th><th>Paid</th><th>Balance</th><th>Invoice Status</th><th>Sale Status</th><th>Actions</th></tr></thead>
    <tbody>@forelse($orders as $order)
        <tr>
            <td>{{ $order->order_number }}</td><td>{{ $order->customer?->display_name }}</td><td>Rs {{ number_format($order->grand_total ?: $order->total) }}</td><td>Rs {{ number_format($order->paid_amount ?? 0) }}</td><td>Rs {{ number_format($order->balance ?? 0) }}</td><td>{{ $order->invoice?->status ?? 'Draft' }}</td><td>{{ $order->status }}</td>
            <td class="d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-primary tf-table-view-action" data-bs-toggle="modal" data-bs-target="#invoiceDetailsModal{{ $order->id }}">View</button>@companyCan('sales.invoice_export')<a href="{{ route('business.sales.invoices.pdf', $order) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">View PDF</a><a href="{{ route('business.sales.invoices.pdf.download', $order) }}" class="btn btn-sm btn-outline-secondary">Download</a>@endcompanyCan</td>
        </tr>
    @empty<tr><td colspan="8" class="text-center tf-muted py-4">No invoice-ready sales.</td></tr>@endforelse</tbody>
</x-table>
@foreach($orders as $order)
    <x-record-details-modal :id="'invoiceDetailsModal'.$order->id" :title="'Invoice '.($order->invoice?->invoice_number ?? $order->order_number)" :status="$order->invoice?->status ?? 'Draft'" :open-url="route('business.sales.invoices.show', $order)">
        <div class="tf-record-details-grid">
            <div><span>Sale</span><strong>{{ $order->order_number }}</strong></div><div><span>Customer</span><strong>{{ $order->customer?->display_name ?? 'Walk-in Customer' }}</strong></div>
            <div><span>Total</span><strong>Rs {{ number_format($order->grand_total ?: $order->total) }}</strong></div><div><span>Paid</span><strong>Rs {{ number_format($order->paid_amount ?? 0) }}</strong></div>
            <div><span>Balance</span><strong>Rs {{ number_format($order->balance ?? 0) }}</strong></div><div><span>Sale status</span><strong>{{ $order->status }}</strong></div>
        </div>
    </x-record-details-modal>
@endforeach
@endsection
