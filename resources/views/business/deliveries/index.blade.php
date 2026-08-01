@extends('layouts.dashboard') @section('page-title', 'Deliveries')
@section('page-subtitle', 'Assigned delivery workflow') @section('content') @if(session('success'))
<div class="alert alert-success"> {{ session('success') }} </div> @endif @if($errors->any())
<div class="alert alert-danger"> {{ $errors->first() }} </div> @endif {{-- Delivery statistics --}} <div
    class="row g-3 mb-4">
    @foreach([['Today Deliveries', $stats['today'] ?? 0, 'bi-calendar-day', 'bg-blue'], ['Pending Deliveries', $stats['pending'] ?? 0, 'bi-hourglass', 'bg-amber'], ['Out For Delivery', $stats['out'] ?? 0, 'bi-truck', 'bg-navy'], ['Delivered', $stats['delivered'] ?? 0, 'bi-check-circle', 'bg-green'], ['Failed', $stats['failed'] ?? 0, 'bi-x-circle', 'bg-red'], ['Cash To Collect', 'Rs ' . number_format((float) ($stats['cash_to_collect'] ?? 0), 2), 'bi-cash-stack', 'bg-blue'],] as [$label, $value, $icon, $color])
        <div class="col-md-6 col-xl-2">
            @include('components.card', ['label' => $label, 'value' => $value, 'icon' => $icon, 'color' => $color, 'note' => '',])
    </div> @endforeach
</div> {{-- Delivery filters --}} <form method="GET" action="{{ route('business.deliveries') }}"
    class="tf-card p-4 mb-3">
    <div class="row g-2 align-items-end"> {{-- Searchable invoice dropdown --}} <div class="col-md-4 col-lg-3"> <label
                for="deliveryInvoiceFilter" class="form-label"> Invoice Number </label> <select name="order_number"
                id="deliveryInvoiceFilter" class="form-select">
                <option value="">All invoices</option> @foreach($invoiceOptions ?? [] as $invoiceOption)
                    @php $invoiceValue = is_array($invoiceOption) ? ($invoiceOption['value'] ?? '') : ($invoiceOption->value ?? '');
                    $invoiceLabel = is_array($invoiceOption) ? ($invoiceOption['label'] ?? $invoiceValue) : ($invoiceOption->label ?? $invoiceValue); @endphp
                    @if($invoiceValue !== '')
                <option value="{{ $invoiceValue }}" @selected((string) request('order_number') === (string) $invoiceValue)> {{ $invoiceLabel }} </option> @endif @endforeach
            </select> </div> {{-- Customer --}} <div class="col-md-4 col-lg-3"> <label for="deliveryCustomerFilter"
                class="form-label"> Customer </label> <select name="customer_id" id="deliveryCustomerFilter"
                class="form-select">
                <option value="">All customers</option> @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>
                {{ $customer->display_name }} </option> @endforeach
            </select> </div> {{-- Delivery staff --}} <div class="col-md-4 col-lg-3"> <label for="deliveryStaffFilter"
                class="form-label"> Delivery Staff </label> <select name="delivery_staff_id" id="deliveryStaffFilter"
                class="form-select">
                <option value="">All staff</option> @foreach($staff as $member)
                <option value="{{ $member->id }}" @selected((string) request('delivery_staff_id') === (string) $member->id)> {{ $member->name }} </option> @endforeach
            </select> </div> {{-- Delivery status --}} <div class="col-md-4 col-lg-3"> <label for="deliveryStatusFilter"
                class="form-label"> Status </label> <select name="status" id="deliveryStatusFilter" class="form-select">
                <option value="">All statuses</option>
                @foreach(['Pending', 'Assigned', 'Picked Up', 'Out For Delivery', 'Delivered', 'Failed', 'Returned', 'Cancelled',] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)> {{ $status }} </option>
                @endforeach
            </select> </div> {{-- Payment status --}} <div class="col-md-4 col-lg-3"> <label
                for="deliveryPaymentStatusFilter" class="form-label"> Payment Status </label> <select
                name="payment_status" id="deliveryPaymentStatusFilter" class="form-select">
                <option value="">All payment statuses</option> @foreach(['Pending', 'Partial', 'Paid'] as $status)
                    <option value="{{ $status }}" @selected(request('payment_status') === $status)> {{ $status }} </option>
                @endforeach
            </select> </div> {{-- Date type --}} <div class="col-md-4 col-lg-3"> <label for="deliveryDateTypeFilter"
                class="form-label"> Date Type </label> <select name="date_type" id="deliveryDateTypeFilter"
                class="form-select">
                @foreach(['created_at' => 'Created Date', 'assigned_at' => 'Assigned Date', 'started_at' => 'Started Date', 'delivered_at' => 'Delivered Date', 'failed_at' => 'Failed Date',] as $value => $label)
                    <option value="{{ $value }}" @selected(request('date_type', $dateType) === $value)> {{ $label }} </option>
                @endforeach
            </select> </div> {{-- Date from --}} <div class="col-md-4 col-lg-3"> <label for="deliveryDateFromFilter"
                class="form-label"> Date From </label> <input type="date" name="date_from" id="deliveryDateFromFilter"
                value="{{ request('date_from', $dateFrom) }}" class="form-control"> </div> {{-- Date to --}} <div
            class="col-md-4 col-lg-3"> <label for="deliveryDateToFilter" class="form-label"> Date To </label> <input
                type="date" name="date_to" id="deliveryDateToFilter" value="{{ request('date_to', $dateTo) }}"
                class="form-control"> </div> {{-- Filter button --}} <div class="col-md-3 col-lg-2"> <button
                type="submit" class="btn btn-outline-primary w-100"> Filter </button> </div> {{-- Clear filters --}}
        <div class="col-md-3 col-lg-2"> <a href="{{ route('business.deliveries', ['clear' => 1]) }}"
                class="btn btn-outline-secondary w-100"> Clear Filters </a> </div>
    </div>
</form> {{-- Deliveries table --}} <x-table class="tf-business-data-table">
    <thead>
        <tr>
            <th>Delivery ID</th>
            <th>Invoice Number</th>
            <th>Customer</th>
            <th>Delivery Staff</th>
            <th>Status</th>
            <th>Payment Status</th>
            <th>Amount</th>
            <th>Assigned At</th>
            <th>Started At</th>
            <th>Delivered At</th>
            <th>Failed At</th>
            <th>Created At</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody> @forelse($deliveries ?? [] as $delivery)
        @php $sale = $delivery->sourceOrder();
            $invoice = $delivery->sourceInvoice();
            $orderTotal = (float) ($sale?->grand_total ?: $sale?->total ?: $delivery->amount ?: 0);
            $paid = (float) ($sale?->paid_amount ?? $sale?->payments?->sum('amount') ?? 0);
            $remaining = $sale ? (float) ($sale->balance ?? max(0, $orderTotal - $paid)) : max(0, (float) ($delivery->amount ?? 0) - $paid);
            if ($remaining <= 0) {
                $paymentStatus = 'Paid';
                $paymentBadge = 'text-bg-success';
            } elseif ($paid > 0) {
                $paymentStatus = 'Partial · Rs ' . number_format($remaining, 2);
                $paymentBadge = 'text-bg-warning';
            } else {
                $paymentStatus = 'Pending · Rs ' . number_format($remaining, 2);
                $paymentBadge = 'text-bg-danger';
            }
        $deliveryStatusBadge = match ($delivery->status) { 'Delivered' => 'text-bg-success', 'Failed', 'Cancelled', 'Returned' => 'text-bg-danger', 'Out For Delivery', 'Picked Up' => 'text-bg-primary', default => 'text-bg-warning', }; @endphp
        <tr>
            <td> #DEL-{{ $delivery->id }} </td>
            <td> {{ $invoice?->invoice_number ?? $sale?->order_number ?? '-' }} </td>
            <td> {{ $delivery->customer?->display_name ?? $sale?->customer?->display_name ?? '-' }} </td>
            <td> {{ $delivery->staff?->name ?? '-' }} </td>
            <td> <span class="badge {{ $deliveryStatusBadge }}"> {{ $delivery->status }} </span> </td>
            <td> <span class="badge {{ $paymentBadge }}"> {{ $paymentStatus }} </span> </td>
            <td> Rs {{ number_format($orderTotal, 2) }} </td>
            <td> <x-date-time :value="$delivery->assigned_at" /> </td>
            <td> <x-date-time :value="$delivery->started_at" /> </td>
            <td> <x-date-time :value="$delivery->delivered_at" /> </td>
            <td> <x-date-time :value="$delivery->failed_at" /> </td>
            <td> <x-date-time :value="$delivery->created_at" /> </td>
            <td>
                <div class="d-flex flex-wrap gap-2"> <a href="{{ route('business.deliveries.show', $delivery) }}"
                        class="btn btn-sm btn-outline-primary"> View </a> <a
                        href="{{ route('business.deliveries.sheet', $delivery) }}" target="_blank" rel="noopener"
                        class="btn btn-sm btn-outline-secondary"> Sheet </a> @companyCan('deliveries.update_status')
                    @if(in_array($delivery->status, ['Pending', 'Assigned', 'Picked Up'], true))
                        <form method="POST" action="{{ route('business.deliveries.start', $delivery) }}"> @csrf
                            @method('PATCH') <button type="submit" class="btn btn-sm btn-outline-success"> Start Delivery
                    </button> </form> @endif @if($delivery->status === 'Out For Delivery') <a
                            href="{{ route('business.deliveries.show', $delivery) }}"
                        class="btn btn-sm btn-outline-primary"> Update Status </a> @endif @endcompanyCan
                    @companyCan('deliveries.edit') @if($delivery->status === 'Failed')
                        <form method="POST" action="{{ route('business.deliveries.reopen', $delivery) }}"> @csrf
                            @method('PATCH') <button type="submit" class="btn btn-sm btn-outline-success"> Reopen </button>
                    </form> @endif @endcompanyCan
                </div>
            </td>
        </tr> @empty <tr>
            <td colspan="13" class="text-center tf-muted py-4"> No deliveries found for the selected filters. </td>
        </tr> @endforelse
    </tbody>
</x-table> {{-- Pagination --}} @if(isset($deliveries) && method_exists($deliveries, 'links'))
    <div class="mt-3"> <x-table-result-summary :paginator="$deliveries" />
{{ $deliveries->withQueryString()->links('pagination::bootstrap-5') }} </div> @endif @endsection @push('scripts')
        <script> document.addEventListener('DOMContentLoaded', function () { const invoiceSelect = document.getElementById('deliveryInvoiceFilter'); if (!invoiceSelect) { return; } /* * Initialize Select2 only when jQuery and Select2 * are already available in the dashboard layout. */ if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 !== 'undefined') { const $invoiceSelect = window.jQuery(invoiceSelect); if ($invoiceSelect.hasClass('select2-hidden-accessible')) { $invoiceSelect.select2('destroy'); } $invoiceSelect.select2({ width: '100%', placeholder: 'Select or search invoice', allowClear: true, }); } }); </script>
    @endpush