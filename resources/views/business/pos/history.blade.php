@extends('layouts.dashboard')
@section('page-title', 'POS Sales History')
@section('page-subtitle', 'Completed counter sales')
@section('content')
<div class="d-flex justify-content-end mb-3"><a class="btn btn-tf-primary" href="{{ route('business.pos.index') }}"><i class="bi bi-calculator me-1"></i>Open POS</a></div>
<x-table>
    <thead><tr><th>Invoice</th><th>Date & Time</th><th>Customer</th><th>Total</th><th>Paid</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($orders as $order)
        @php($receiptNumber = $order->invoice?->invoice_number ?? $order->order_number)
        <tr>
            <td>{{ $receiptNumber }}</td>
            <td>{{ $order->order_date?->format('n/j/Y, g:i A') }}</td>
            <td>{{ $order->customer?->name ?? 'Walk-in Customer' }}</td>
            <td>Rs {{ number_format($order->grand_total ?: $order->total) }}</td>
            <td>Rs {{ number_format($order->paid_amount) }}</td>
            <td>
                <div class="d-flex flex-wrap gap-1">
                    @companyCan('pos.print_receipt')
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('business.pos.receipt.view', ['invoice' => $receiptNumber]) }}" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>View</a>
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('business.pos.receipt.print', ['invoice' => $receiptNumber]) }}" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>
                        <a class="btn btn-sm btn-outline-success" href="{{ route('business.pos.receipt.download', ['invoice' => $receiptNumber]) }}"><i class="bi bi-download me-1"></i>Download</a>
                    @endcompanyCan
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center text-muted py-5">No POS sales yet.</td></tr>
    @endforelse
    </tbody>
</x-table>
<div class="mt-3">{{ $orders->links() }}</div>

@endsection
