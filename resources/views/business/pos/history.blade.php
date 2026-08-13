@extends('layouts.dashboard')
@section('page-title', 'POS Sales History')
@section('page-subtitle', 'Completed counter sales')
@section('content')
<div class="d-flex justify-content-end mb-3"><a class="btn btn-tf-primary" href="{{ route('business.pos.index') }}"><i class="bi bi-calculator me-1"></i>Open POS</a></div>
<x-table class="tf-pos-history-table">
    <thead><tr><th>Invoice</th><th>Date & Time</th><th>Customer</th><th>Total</th><th>Paid</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($orders as $order)
        @php($receiptNumber = $order->invoice?->invoice_number ?? $order->order_number)
        <tr>
            <td><strong>{{ $receiptNumber }}</strong></td>
            <td>{{ $order->order_date?->format('n/j/Y, g:i A') }}</td>
            <td>{{ $order->customer?->name ?? 'Walk-in Customer' }}</td>
            <td>Rs {{ number_format($order->grand_total ?: $order->total) }}</td>
            <td>Rs {{ number_format($order->paid_amount) }}</td>
            <td class="text-end text-nowrap">
                <div class="btn-group tf-pos-history-actions">
                    @companyCan('pos.print_receipt')
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('business.pos.receipt.view', ['invoice' => $receiptNumber]) }}" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>View</a>
                        <button class="btn btn-sm btn-outline-secondary tf-table-more-action" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="More actions for {{ $receiptNumber }}"><i class="bi bi-three-dots"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li><a class="dropdown-item" href="{{ route('business.pos.receipt.print', ['invoice' => $receiptNumber]) }}" target="_blank" rel="noopener"><i class="bi bi-printer me-2"></i>Print</a></li>
                            <li><a class="dropdown-item" href="{{ route('business.pos.receipt.download', ['invoice' => $receiptNumber]) }}"><i class="bi bi-download me-2"></i>Download PDF</a></li>
                        </ul>
                    @endcompanyCan
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center text-muted py-4">No POS sales found.</td></tr>
    @endforelse
    </tbody>
</x-table>
@if($orders->total() > 0)
    <div class="tf-pos-history-pagination mt-3">
        <small class="tf-muted">Showing {{ $orders->firstItem() }} to {{ $orders->lastItem() }} of {{ $orders->total() }} results</small>
        {{ $orders->onEachSide(1)->links() }}
    </div>
@endif

@endsection
