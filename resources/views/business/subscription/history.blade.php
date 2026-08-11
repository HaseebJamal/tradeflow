@extends('layouts.dashboard')

@section('page-title', 'Subscription History')
@section('page-subtitle', 'Review your billing records and subscription requests')

@section('content')
    @php
        $activeTab = in_array(request('tab'), ['billing', 'requests', 'renewals'], true) ? request('tab') : 'billing';
        $permissions = app(\App\Services\CompanyPermissionService::class);
        $statusBadge = fn(?string $status) => match (strtolower((string) $status)) {
            'active', 'approved', 'received', 'paid' => 'tf-badge-success',
            'pending', 'pending review', 'trial', 'expiring' => 'tf-badge-warning',
            'cancelled', 'rejected', 'suspended', 'expired', 'failed', 'overdue' => 'tf-badge-danger',
            default => 'tf-badge-info',
        };
        $billingSortUrl = function (string $column) use ($billingSort, $billingDirection) {
            return route('business.subscription.history', array_merge(request()->except('billing_page'), [
                'tab' => 'billing',
                'billing_sort' => $column,
                'billing_direction' => $billingSort === $column && $billingDirection === 'asc' ? 'desc' : 'asc',
            ]));
        };
        $requestSortUrl = function (string $column) use ($requestSort, $requestDirection) {
            return route('business.subscription.history', array_merge(request()->except('request_page'), [
                'tab' => 'requests',
                'request_sort' => $column,
                'request_direction' => $requestSort === $column && $requestDirection === 'asc' ? 'desc' : 'asc',
            ]));
        };
        $sortIcon = fn(string $column, string $active, string $direction) => $active === $column
            ? ($direction === 'asc' ? 'bi-sort-up' : 'bi-sort-down')
            : 'bi-arrow-down-up';
        $canCancelRequest = $permissions->allowsUser(auth()->user(), 'subscriptions.cancel', $business);
    @endphp

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <a class="btn btn-outline-secondary" href="{{ route('business.subscription.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Subscription
        </a>
        <span class="tf-muted small">All records shown belong to {{ $business->business_name }}.</span>
    </div>

    <div class="tf-card p-0 overflow-hidden">
        <nav class="border-bottom px-3 pt-3" aria-label="Subscription history sections">
            <div class="nav nav-tabs border-0">
                <a class="nav-link {{ $activeTab === 'billing' ? 'active' : '' }}" href="{{ route('business.subscription.history', array_merge(request()->except(['tab', 'request_page']), ['tab' => 'billing'])) }}">
                    <i class="bi bi-receipt me-1"></i>Billing History
                </a>
                <a class="nav-link {{ $activeTab === 'requests' ? 'active' : '' }}" href="{{ route('business.subscription.history', array_merge(request()->except(['tab', 'billing_page']), ['tab' => 'requests'])) }}">
                    <i class="bi bi-clock-history me-1"></i>Request History
                </a>
                <a class="nav-link {{ $activeTab === 'renewals' ? 'active' : '' }}" href="{{ route('business.subscription.history', array_merge(request()->except(['tab', 'billing_page', 'request_page']), ['tab' => 'renewals'])) }}">
                    <i class="bi bi-arrow-repeat me-1"></i>Renewal Invoices
                </a>
            </div>
        </nav>

        @if($activeTab === 'billing')
            <div class="p-3 border-bottom bg-light-subtle">
                <form method="GET" action="{{ route('business.subscription.history') }}" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="billing">
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label small mb-1" for="billingCycle">Billing Cycle</label>
                        <select class="form-select" id="billingCycle" name="billing_cycle">
                            <option value="">All cycles</option>
                            @foreach(['Monthly', 'Yearly'] as $cycle)
                                <option value="{{ $cycle }}" @selected(request('billing_cycle') === $cycle)>{{ $cycle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label small mb-1" for="paymentStatus">Payment Status</label>
                        <select class="form-select" id="paymentStatus" name="payment_status">
                            <option value="">All statuses</option>
                            @foreach(['Pending', 'Received', 'Approved', 'Rejected'] as $status)
                                <option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                   <div class="col-sm-6 col-lg-2">
    <label class="form-label small mb-1" for="billingDateFrom">Date From</label>
    <input
        class="form-control"
        id="billingDateFrom"
        type="date"
        name="date_from"
        value="{{ request('date_from', now()->format('Y-m-d')) }}"
    >
</div>

<div class="col-sm-6 col-lg-2">
    <label class="form-label small mb-1" for="billingDateTo">Date To</label>
    <input
        class="form-control"
        id="billingDateTo"
        type="date"
        name="date_to"
        value="{{ request('date_to', now()->format('Y-m-d')) }}"
    >
</div>
                    <div class="col-sm-6 col-lg-2 d-flex gap-2">
                        <button class="btn btn-tf-primary flex-fill" type="submit">Filter</button>
                        <a class="btn btn-outline-secondary" href="{{ route('business.subscription.history', ['tab' => 'billing']) }}">Clear</a>
                    </div>
                </form>
            </div>

            <x-table class="tf-business-data-table mb-0">
                <thead>
                    <tr>
                        <th><a class="text-decoration-none text-reset" href="{{ $billingSortUrl('paid_at') }}">Date &amp; Time <i class="bi {{ $sortIcon('paid_at', $billingSort, $billingDirection) }} small"></i></a></th>
                        <th>Plan</th><th>Billing Cycle</th>
                        <th><a class="text-decoration-none text-reset" href="{{ $billingSortUrl('amount') }}">Amount <i class="bi {{ $sortIcon('amount', $billingSort, $billingDirection) }} small"></i></a></th>
                        <th><a class="text-decoration-none text-reset" href="{{ $billingSortUrl('method') }}">Payment Method <i class="bi {{ $sortIcon('method', $billingSort, $billingDirection) }} small"></i></a></th>
                        <th><a class="text-decoration-none text-reset" href="{{ $billingSortUrl('reference_number') }}">Reference <i class="bi {{ $sortIcon('reference_number', $billingSort, $billingDirection) }} small"></i></a></th>
                        <th><a class="text-decoration-none text-reset" href="{{ $billingSortUrl('status') }}">Status <i class="bi {{ $sortIcon('status', $billingSort, $billingDirection) }} small"></i></a></th>
                        <th><a class="text-decoration-none text-reset" href="{{ $billingSortUrl('recorded_by') }}">Recorded By <i class="bi {{ $sortIcon('recorded_by', $billingSort, $billingDirection) }} small"></i></a></th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($billingHistory as $payment)
                        <tr>
                            <td class="text-nowrap"><x-date-time :value="$payment->paid_at" /></td>
                            <td>{{ $payment->plan?->name ?? $payment->subscription?->plan?->name ?? '—' }}</td><td>{{ $payment->billing_cycle ?? $payment->subscription?->billing_cycle ?? '—' }}</td>
                            <td class="text-nowrap">Rs {{ number_format($payment->amount, 2) }}</td>
                            <td>{{ $payment->method ?: '—' }}</td>
                            <td>{{ $payment->reference_number ?: '—' }}</td>
                            <td><span class="tf-badge {{ $statusBadge($payment->status) }}">{{ $payment->status }}</span></td>
                            <td>{{ $payment->recordedBy?->name ?? 'System' }}</td><td class="text-nowrap">@if($payment->status === 'Received')<a class="btn btn-sm btn-outline-success" href="{{ route('business.subscription.payments.receipt', $payment) }}">Receipt</a>@else <span class="tf-muted small">Verification pending</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center tf-muted py-5">No billing payments match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </x-table>
            <div class="p-3"><x-table-result-summary :paginator="$billingHistory" />{{ $billingHistory->links('pagination::bootstrap-5') }}</div>
        @elseif($activeTab === 'renewals')
            <x-table class="tf-business-data-table mb-0">
                <thead><tr><th>Invoice ID</th><th>Amount</th><th>Current Access End</th><th>Due Date</th><th>Status</th><th>Created</th></tr></thead>
                <tbody>@forelse($renewalInvoices as $renewal)
                    <tr><td class="fw-semibold">{{ $renewal->invoice_number }}</td><td>Rs {{ number_format((float) $renewal->amount, 2) }}</td><td>{{ $renewal->access_ends_at->format('n/j/Y') }}</td><td>{{ $renewal->due_date->format('n/j/Y') }}</td><td><span class="tf-badge {{ $statusBadge($renewal->status) }}">{{ $renewal->status }}</span></td><td><x-date-time :value="$renewal->created_at" /></td></tr>
                @empty<tr><td colspan="6" class="text-center tf-muted py-5">No renewal invoices have been generated for this business.</td></tr>@endforelse</tbody>
            </x-table>
            <div class="p-3"><x-table-result-summary :paginator="$renewalInvoices" />{{ $renewalInvoices->links('pagination::bootstrap-5') }}</div>
        @else
            <x-table class="tf-business-data-table mb-0" style="min-width: 1320px">
                <thead>
                    <tr>
                        <th><a class="text-decoration-none text-reset" href="{{ $requestSortUrl('id') }}">Request ID <i class="bi {{ $sortIcon('id', $requestSort, $requestDirection) }} small"></i></a></th>
                        <th><a class="text-decoration-none text-reset" href="{{ $requestSortUrl('type') }}">Type <i class="bi {{ $sortIcon('type', $requestSort, $requestDirection) }} small"></i></a></th>
                        <th>Current Plan</th>
                        <th>Requested Plan</th>
                        <th><a class="text-decoration-none text-reset" href="{{ $requestSortUrl('billing_cycle') }}">Billing Cycle <i class="bi {{ $sortIcon('billing_cycle', $requestSort, $requestDirection) }} small"></i></a></th>
                        <th><a class="text-decoration-none text-reset" href="{{ $requestSortUrl('payment_method') }}">Payment Method <i class="bi {{ $sortIcon('payment_method', $requestSort, $requestDirection) }} small"></i></a></th>
                        <th><a class="text-decoration-none text-reset" href="{{ $requestSortUrl('expected_amount') }}">Amount <i class="bi {{ $sortIcon('expected_amount', $requestSort, $requestDirection) }} small"></i></a></th>
                        <th><a class="text-decoration-none text-reset" href="{{ $requestSortUrl('effective_at') }}">Effective Date <i class="bi {{ $sortIcon('effective_at', $requestSort, $requestDirection) }} small"></i></a></th>
                        <th><a class="text-decoration-none text-reset" href="{{ $requestSortUrl('status') }}">Status <i class="bi {{ $sortIcon('status', $requestSort, $requestDirection) }} small"></i></a></th>
                        <th><a class="text-decoration-none text-reset" href="{{ $requestSortUrl('created_at') }}">Requested At <i class="bi {{ $sortIcon('created_at', $requestSort, $requestDirection) }} small"></i></a></th>
                        <th><a class="text-decoration-none text-reset" href="{{ $requestSortUrl('reviewed_at') }}">Reviewed At / By <i class="bi {{ $sortIcon('reviewed_at', $requestSort, $requestDirection) }} small"></i></a></th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscriptionRequests as $change)
                        <tr>
                            <td class="text-nowrap">#{{ $change->id }}</td>
                            <td>{{ $change->type }}</td>
                            <td>{{ $change->currentPlan?->name ?? '—' }}</td>
                            <td>{{ $change->requestedPlan?->name ?? '—' }}</td>
                            <td>{{ $change->billing_cycle ?: '—' }}</td>
                            <td>{{ $change->payment_method ?: '—' }}</td>
                            <td class="text-nowrap">Rs {{ number_format($change->expected_amount, 2) }}</td>
                            <td class="text-nowrap">{{ $change->effective_at?->format('n/j/Y') ?? '—' }}</td>
                            <td><span class="tf-badge {{ $statusBadge($change->status) }}">{{ $change->status }}</span></td>
                            <td class="text-nowrap"><x-date-time :value="$change->created_at" /></td>
                            <td class="text-nowrap">@if($change->reviewed_at)<x-date-time :value="$change->reviewed_at" /><span class="d-block small tf-muted">{{ $change->reviewer?->name ?? 'System' }}</span>@else<span class="tf-muted">Not reviewed</span>@endif</td>
                            <td class="text-end text-nowrap">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport">Actions</button>
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#subscriptionRequestDetailsModal"
                                            data-request-id="#{{ $change->id }}" data-request-type="{{ $change->type }}"
                                            data-current-plan="{{ $change->currentPlan?->name ?? '—' }}" data-requested-plan="{{ $change->requestedPlan?->name ?? '—' }}"
                                            data-billing-cycle="{{ $change->billing_cycle ?: '—' }}" data-payment-method="{{ $change->payment_method ?: '—' }}"
                                            data-amount="Rs {{ number_format($change->expected_amount, 2) }}" data-effective-date="{{ $change->effective_at?->format('n/j/Y') ?? '—' }}"
                                            data-status="{{ $change->status }}" data-requested-by="{{ $change->requester?->name ?? '—' }}"
                                            data-requested-at="{{ $change->created_at?->format('n/j/Y, g:i A') ?? '—' }}" data-reviewed-by="{{ $change->reviewer?->name ?? 'Not reviewed' }}"
                                            data-reviewed-at="{{ $change->reviewed_at?->format('n/j/Y, g:i A') ?? 'Not reviewed' }}" data-note="{{ $change->admin_note ?: ($change->note ?: '') }}">
                                            <i class="bi bi-eye me-2"></i>View Details
                                        </button>
                                        @if($change->status === 'Pending' && $canCancelRequest)
                                            <form method="POST" action="{{ route('business.subscription.requests.cancel', $change) }}">@csrf @method('PATCH')
                                                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-x-circle me-2"></i>Cancel Request</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center tf-muted py-5">No subscription requests recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </x-table>
            <div class="p-3"><x-table-result-summary :paginator="$subscriptionRequests" />{{ $subscriptionRequests->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>

    <div class="modal fade" id="subscriptionRequestDetailsModal" tabindex="-1" aria-labelledby="subscriptionRequestDetailsTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header"><h2 class="modal-title fs-5" id="subscriptionRequestDetailsTitle">Subscription Request Details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <dl class="row mb-0" data-request-detail-list>
                        @foreach(['Request ID', 'Request Type', 'Current Plan', 'Requested Plan', 'Billing Cycle', 'Payment Method', 'Amount', 'Effective Date', 'Status', 'Requested By', 'Requested At', 'Reviewed By', 'Reviewed At'] as $label)
                            <dt class="col-sm-4 tf-muted">{{ $label }}</dt><dd class="col-sm-8" data-request-detail="{{ \Illuminate\Support\Str::snake($label) }}">—</dd>
                        @endforeach
                        <dt class="col-sm-4 tf-muted d-none" data-request-note-label>Reason / Note</dt><dd class="col-sm-8 d-none" data-request-detail="note">—</dd>
                    </dl>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('subscriptionRequestDetailsModal');
            if (!modal) return;

            modal.addEventListener('show.bs.modal', function (event) {
                const source = event.relatedTarget;
                if (!source) return;
                Object.entries(source.dataset).forEach(function ([key, value]) {
                    const target = modal.querySelector('[data-request-detail="' + key.replace(/([A-Z])/g, '_$1').toLowerCase() + '"]');
                    if (target) target.textContent = value || '—';
                });
                const note = source.dataset.note || '';
                modal.querySelector('[data-request-note-label]').classList.toggle('d-none', !note);
                modal.querySelector('[data-request-detail="note"]').classList.toggle('d-none', !note);
            });
        });
    </script>
@endpush
