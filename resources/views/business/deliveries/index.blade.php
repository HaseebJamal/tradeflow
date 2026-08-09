@extends('layouts.dashboard')
@section('page-title', 'Deliveries')
@section('page-subtitle', 'Delivery queue and assigned delivery workflow')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="row g-3 tf-delivery-kpis mb-4">
    @foreach([
        ['Today Deliveries', $stats['today'] ?? 0, 'bi-calendar-day', 'bg-blue'],
        ['Pending Deliveries', $stats['pending'] ?? 0, 'bi-hourglass', 'bg-amber'],
        ['Out For Delivery', $stats['out'] ?? 0, 'bi-truck', 'bg-blue'],
        ['Delivered', $stats['delivered'] ?? 0, 'bi-check-circle', 'bg-green'],
        ['Failed', $stats['failed'] ?? 0, 'bi-x-circle', 'bg-red'],
        ['Cash To Collect', 'Rs '.number_format((float) ($stats['cash_to_collect'] ?? 0), 2), 'bi-cash-stack', 'bg-blue'],
    ] as [$label, $value, $icon, $color])
        <div @class(['col-md-6', 'col-xl-2', 'tf-delivery-cash-card' => $label === 'Cash To Collect'])>
            @include('components.card', ['label' => $label, 'value' => $value, 'icon' => $icon, 'color' => $color, 'note' => ''])
        </div>
    @endforeach
</div>

<form method="GET" action="{{ route('business.deliveries') }}" class="tf-card tf-delivery-filter-card mb-3">
    <div class="tf-delivery-filter-grid">
        <div>
            <label class="form-label" for="deliveryInvoiceFilter">Invoice</label>
            <select name="order_number" id="deliveryInvoiceFilter" class="form-select">
                <option value="">All invoices</option>
                @foreach($invoiceOptions ?? [] as $invoiceOption)
                    @php
                        $invoiceValue = is_array($invoiceOption) ? ($invoiceOption['value'] ?? '') : ($invoiceOption->value ?? '');
                        $invoiceLabel = is_array($invoiceOption) ? ($invoiceOption['label'] ?? $invoiceValue) : ($invoiceOption->label ?? $invoiceValue);
                    @endphp
                    @if($invoiceValue !== '')<option value="{{ $invoiceValue }}" @selected((string) request('order_number') === (string) $invoiceValue)>{{ $invoiceLabel }}</option>@endif
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="deliveryCustomerFilter">Customer</label>
            <select name="customer_id" id="deliveryCustomerFilter" class="form-select">
                <option value="">All customers</option>
                @foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((string) request('customer_id') === (string) $customer->id)>{{ $customer->display_name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="deliveryStaffFilter">Delivery Staff</label>
            <select name="delivery_staff_id" id="deliveryStaffFilter" class="form-select">
                <option value="">All staff</option>
                @foreach($staff as $member)<option value="{{ $member->id }}" @selected((string) request('delivery_staff_id') === (string) $member->id)>{{ $member->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="deliveryStatusFilter">Status</label>
            <select name="status" id="deliveryStatusFilter" class="form-select">
                <option value="">All statuses</option>
                @foreach(['Pending', 'Assigned', 'Picked Up', 'Out For Delivery', 'Delivered', 'Failed', 'Returned', 'Cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="deliveryPaymentStatusFilter">Payment</label>
            <select name="payment_status" id="deliveryPaymentStatusFilter" class="form-select">
                <option value="">Payment statuses</option>
                @foreach(['Pending', 'Partial', 'Paid'] as $status)<option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ $status }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="deliveryDateTypeFilter">Date Type</label>
            <select name="date_type" id="deliveryDateTypeFilter" class="form-select">
                @foreach(['created_at' => 'Created Date', 'assigned_at' => 'Assigned Date', 'started_at' => 'Started Date', 'delivered_at' => 'Delivered Date', 'failed_at' => 'Failed Date'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('date_type', $dateType) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="deliveryDateFromFilter">Date From</label>
            <input type="date" name="date_from" id="deliveryDateFromFilter" value="{{ request('date_from', $dateFrom) }}" class="form-control">
        </div>
        <div>
            <label class="form-label" for="deliveryDateToFilter">Date To</label>
            <input type="date" name="date_to" id="deliveryDateToFilter" value="{{ request('date_to', $dateTo) }}" class="form-control">
        </div>
        <div class="tf-delivery-filter-actions">
            <button type="submit" class="btn btn-tf-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="{{ route('business.deliveries', ['clear' => 1]) }}" class="btn btn-outline-secondary">Clear Filters</a>
        </div>
    </div>
</form>

<x-table class="tf-business-data-table tf-delivery-table">
    <thead>
        <tr>
            <th>Delivery</th>
            <th>Customer</th>
            <th>Delivery Staff</th>
            <th>Status</th>
            <th>Payment</th>
            <th class="text-end">Amount</th>
            <th>Timeline</th>
            <th class="text-end">Actions</th>
        </tr>
    </thead>
    <tbody>
    @forelse($deliveries ?? [] as $delivery)
        @php
            $sale = $delivery->sourceOrder();
            $invoice = $delivery->sourceInvoice();
            $orderTotal = (float) ($sale?->grand_total ?: $sale?->total ?: $delivery->amount ?: 0);
            $paid = (float) ($sale?->paid_amount ?? $sale?->payments?->sum('amount') ?? 0);
            $remaining = $sale ? (float) ($sale->balance ?? max(0, $orderTotal - $paid)) : max(0, (float) ($delivery->amount ?? 0) - $paid);
            if ($remaining <= 0) {
                $paymentStatus = 'Paid';
                $paymentBadge = 'text-bg-success';
            } elseif ($paid > 0) {
                $paymentStatus = 'Partial';
                $paymentBadge = 'text-bg-warning';
            } else {
                $paymentStatus = 'Due';
                $paymentBadge = 'text-bg-danger';
            }
            $deliveryStatusBadge = match ($delivery->status) {
                'Delivered' => 'text-bg-success',
                'Failed', 'Cancelled', 'Returned' => 'text-bg-danger',
                'Out For Delivery', 'Picked Up' => 'text-bg-primary',
                default => 'text-bg-warning',
            };
            [$timelineLabel, $timelineAt] = match ($delivery->status) {
                'Delivered' => ['Delivered', $delivery->delivered_at],
                'Failed' => ['Failed', $delivery->failed_at],
                'Out For Delivery', 'Picked Up' => ['Started', $delivery->started_at],
                'Assigned' => ['Assigned', $delivery->assigned_at],
                default => ['Created', $delivery->created_at],
            };
            $customer = $delivery->customer ?? $sale?->customer;
            $invoiceNumber = $invoice?->invoice_number ?? $sale?->order_number ?? ('DEL-'.$delivery->id);
        @endphp
        <tr>
            <td>
                <div class="tf-delivery-cell">
                    <strong>{{ $invoiceNumber }}</strong>
                    <small>#DEL-{{ $delivery->id }} · <x-date-time :value="$delivery->created_at" /></small>
                </div>
            </td>
            <td>
                <div class="tf-delivery-cell">
                    <strong>{{ $customer?->display_name ?? '-' }}</strong>
                    <small>{{ $customer?->phone ?? 'No contact' }}</small>
                </div>
            </td>
            <td><span class="tf-delivery-staff">{{ $delivery->staff?->name ?? 'Unassigned' }}</span></td>
            <td><span class="badge {{ $deliveryStatusBadge }}">{{ $delivery->status }}</span></td>
            <td>
                <span class="badge {{ $paymentBadge }}">{{ $paymentStatus }}</span>
                @if($remaining > 0)<small class="d-block tf-muted mt-1">Rs {{ number_format($remaining, 2) }} due</small>@endif
            </td>
            <td class="text-end text-nowrap"><strong>Rs {{ number_format($orderTotal, 2) }}</strong></td>
            <td>
                <div class="tf-delivery-timeline">
                    <strong>{{ $timelineLabel }}</strong>
                    <small><x-date-time :value="$timelineAt" /></small>
                </div>
            </td>
            <td>
                <div class="tf-delivery-actions">
                    <a href="{{ route('business.deliveries.show', $delivery) }}" class="btn btn-sm btn-tf-primary">Manage</a>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary tf-table-more-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions for {{ $invoiceNumber }}"><i class="bi bi-three-dots"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <button
                                    class="dropdown-item"
                                    type="button"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deliveryQuickView"
                                    data-delivery-quick-view
                                    data-delivery-invoice="{{ $invoiceNumber }}"
                                    data-delivery-id="#DEL-{{ $delivery->id }}"
                                    data-delivery-status="{{ $delivery->status }}"
                                    data-delivery-customer="{{ $customer?->display_name ?? '-' }}"
                                    data-delivery-contact="{{ $customer?->phone ?? 'No contact' }}"
                                    data-delivery-staff="{{ $delivery->staff?->name ?? 'Unassigned' }}"
                                    data-delivery-amount="Rs {{ number_format($orderTotal, 2) }}"
                                    data-delivery-payment="{{ $paymentStatus }}"
                                    data-delivery-address="{{ $delivery->address ?: $sale?->delivery_address ?: '-' }}"
                                    data-delivery-timeline-label="{{ $timelineLabel }}"
                                    data-delivery-timeline-at="{{ $timelineAt?->format('d M Y, h:i A') ?? '-' }}"
                                    data-delivery-manage-url="{{ route('business.deliveries.show', $delivery) }}"
                                ><i class="bi bi-eye me-2"></i>Quick View</button>
                            </li>
                            <li><a class="dropdown-item" href="{{ route('business.deliveries.sheet', $delivery) }}" target="_blank" rel="noopener"><i class="bi bi-printer me-2"></i>Delivery Sheet</a></li>
                            @companyCan('deliveries.edit')
                                @if($delivery->status === 'Failed')
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('business.deliveries.reopen', $delivery) }}" data-tf-confirm-message="Reassign {{ $invoiceNumber }} for delivery?">
                                            @csrf @method('PATCH')
                                            <button class="dropdown-item text-success" type="submit"><i class="bi bi-arrow-repeat me-2"></i>Reassign / Retry</button>
                                        </form>
                                    </li>
                                @endif
                            @endcompanyCan
                        </ul>
                    </div>
                </div>
            </td>
        </tr>

    @empty
        <tr><td colspan="8" class="text-center tf-muted py-5">No deliveries found for the selected filters.</td></tr>
    @endforelse
    </tbody>
</x-table>

<div class="modal fade tf-delivery-quick-modal" id="deliveryQuickView" tabindex="-1" aria-labelledby="deliveryQuickViewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div><h2 class="modal-title h5 mb-1" id="deliveryQuickViewTitle" data-delivery-quick-invoice>Delivery</h2><p class="mb-0 text-muted small"><span data-delivery-quick-id></span> · <span data-delivery-quick-status></span></p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="tf-delivery-quick-grid">
                    <div><span>Customer</span><strong data-delivery-quick-customer>-</strong><small data-delivery-quick-contact></small></div>
                    <div><span>Delivery Staff</span><strong data-delivery-quick-staff>Unassigned</strong></div>
                    <div><span>Amount</span><strong data-delivery-quick-amount>-</strong></div>
                    <div><span>Payment</span><strong data-delivery-quick-payment>-</strong></div>
                    <div class="tf-delivery-quick-wide"><span>Address</span><strong data-delivery-quick-address>-</strong></div>
                </div>
                <div class="tf-delivery-quick-timeline mt-4">
                    <h3>Current timeline</h3>
                    <div><span></span><p><strong data-delivery-quick-timeline-label>Created</strong><small data-delivery-quick-timeline-at>-</small></p></div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" data-delivery-quick-manage class="btn btn-tf-primary">Manage Delivery</a>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@if(isset($deliveries) && method_exists($deliveries, 'links'))
    <div class="tf-delivery-pagination mt-3">
        <x-table-result-summary :paginator="$deliveries" />
        {{ $deliveries->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const invoiceSelect = document.getElementById('deliveryInvoiceFilter');
    if (invoiceSelect && typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 !== 'undefined') {
        const $invoiceSelect = window.jQuery(invoiceSelect);
        if ($invoiceSelect.hasClass('select2-hidden-accessible')) $invoiceSelect.select2('destroy');
        $invoiceSelect.select2({ width: '100%', placeholder: 'Select or search invoice', allowClear: true });
    }

    document.querySelectorAll('[data-delivery-quick-view]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const set = (name) => {
                const target = document.querySelector('[data-delivery-quick-' + name + ']');
                if (target) target.textContent = trigger.dataset['delivery' + name.charAt(0).toUpperCase() + name.slice(1)] || '-';
            };
            ['invoice', 'id', 'status', 'customer', 'contact', 'staff', 'amount', 'payment', 'address', 'timelineLabel', 'timelineAt'].forEach(set);
            const manage = document.querySelector('[data-delivery-quick-manage]');
            if (manage) manage.href = trigger.dataset.deliveryManageUrl || '#';
        });
    });
});
</script>
@endpush
