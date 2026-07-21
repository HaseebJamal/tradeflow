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
            <td>{{ $order->order_date?->format('d M Y g:i A') }}</td>
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
                    @if($canAssignDelivery && $order->invoice)
                        <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#assignDelivery{{ $order->invoice->id }}"><i class="bi bi-truck me-1"></i>Assign Delivery</button>
                    @endif
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center text-muted py-5">No POS sales yet.</td></tr>
    @endforelse
    </tbody>
</x-table>
<div class="mt-3">{{ $orders->links() }}</div>

@if($canAssignDelivery)
    @foreach($orders as $order)
        @continue(! $order->invoice)
        <div class="modal fade" id="assignDelivery{{ $order->invoice->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('business.pos.delivery.assign', $order->invoice) }}">
                    @csrf
                    <div class="modal-header"><h2 class="modal-title h5">Assign Delivery</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="alert alert-light border py-2"><strong>{{ $order->invoice->invoice_number }}</strong><br><small>{{ $order->customer?->name ?? 'Walk-in Customer' }}</small></div>
                        <div class="mb-3"><label class="form-label">Delivery Staff</label><select name="delivery_staff_id" class="form-select" data-native-select required><option value="">Select delivery staff</option>@foreach($deliveryStaff as $staff)<option value="{{ $staff->id }}">{{ $staff->name }}</option>@endforeach</select></div>
                        <div class="mb-3"><label class="form-label">Delivery Address</label><textarea name="address" class="form-control" rows="3" required>{{ $order->customer?->address }}</textarea></div>
                        <div><label class="form-label">Delivery Notes <span class="text-muted">Optional</span></label><textarea name="note" class="form-control" rows="2"></textarea></div>
                        @if($deliveryStaff->isEmpty())<div class="alert alert-warning mt-3 mb-0">No active delivery staff are available for this business.</div>@endif
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tf-primary" @disabled($deliveryStaff->isEmpty())>Assign Delivery</button></div>
                </form>
            </div>
        </div>
    @endforeach
@endif
@endsection
