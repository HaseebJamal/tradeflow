@extends('layouts.dashboard')

@section('page-title', 'Payments & Billing')
@section('page-subtitle', 'Record negotiated charges, payment references, receipts, and paid access periods')

@section('content')
@php
    $showPaymentForm = !request()->has('record') || request()->boolean('record');
    $compactDate = static fn ($value) => filled($value) ? \Illuminate\Support\Carbon::parse($value)->format('n/j/Y') : '';
@endphp

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="tf-billing-workspace">
    <section class="tf-card tf-billing-form-card mb-4">
        <div class="tf-billing-card-heading">
            <div><h2>Custom Business Payment</h2><p>Record the negotiated amount and paid access period for one business.</p></div>
            <a href="{{ route('admin.payments', array_merge(request()->except('record'), ['record' => $showPaymentForm ? 0 : 1])) }}" class="btn btn-outline-primary tf-billing-button">{{ $showPaymentForm ? 'Hide Form' : 'Show Form' }}</a>
        </div>

        @if($showPaymentForm)
            <form method="POST" action="{{ route('admin.payments.store') }}" class="tf-billing-payment-form">
                @csrf
                <fieldset>
                    <legend>Payment Details</legend>
                    <div class="row g-3">
                        <div class="col-xl-4 col-lg-4"><label class="form-label">Business</label><select name="business_id" class="form-select" required><option value="">Select business</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected(old('business_id', $filters['business_id'] ?? null) == $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
                        <div class="col-xl-2 col-md-3"><label class="form-label">Agreed Amount</label><input name="amount" type="number" min="1" step="1" value="{{ old('amount') }}" class="form-control" required></div>
                        <div class="col-xl-2 col-md-3"><label class="form-label">Payment Method</label><select name="method" class="form-select" required>@foreach(['Cash','Bank Transfer','Jazz Cash','Easypaisa','Cheque','Other'] as $method)<option value="{{ $method }}" @selected(old('method') === $method)>{{ $method }}</option>@endforeach</select></div>
                        <div class="col-xl-2 col-md-3"><label class="form-label">Payment Status</label><select name="status" class="form-select" required>@foreach(['Received','Pending'] as $status)<option value="{{ $status }}" @selected(old('status', 'Received') === $status)>{{ $status }}</option>@endforeach</select></div>
                        <div class="col-xl-2 col-md-3"><label class="form-label">Payment Date</label><input name="paid_at" type="text" inputmode="numeric" placeholder="8/9/2026" value="{{ old('paid_at', now()->format('n/j/Y')) }}" class="form-control" required></div>
                    </div>
                </fieldset>
                <fieldset>
                    <legend>Access Period</legend>
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3 col-md-4"><label class="form-label">Access Start Date</label><input name="period_starts_at" type="text" inputmode="numeric" placeholder="8/9/2026" value="{{ old('period_starts_at', now()->format('n/j/Y')) }}" class="form-control"></div>
                        <div class="col-lg-3 col-md-4"><label class="form-label">Access End Date</label><input name="period_ends_at" type="text" inputmode="numeric" placeholder="8/9/2026" value="{{ old('period_ends_at') }}" class="form-control"></div>
                        <div class="col-lg-3 col-md-4"><label class="form-label">Payment Reference</label><input name="transaction_reference" maxlength="120" value="{{ old('transaction_reference') }}" class="form-control" placeholder="Transfer, cheque, or receipt"></div>
                        <div class="col-lg-3 col-md-8"><label class="form-label">Admin Note</label><input name="notes" maxlength="2000" value="{{ old('notes') }}" class="form-control" placeholder="Optional agreement or renewal note"></div>
                        <div class="col-12 d-flex justify-content-end"><button class="btn btn-tf-primary tf-billing-button" type="submit">Save Payment</button></div>
                    </div>
                </fieldset>
            </form>
        @endif
    </section>

    <section class="tf-card tf-billing-filter-card mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-xl-3 col-lg-3"><label class="form-label">Search</label><input name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Reference or owner"></div>
            <div class="col-xl-2 col-lg-2"><label class="form-label">Business</label><select name="business_id" class="form-select"><option value="">All businesses</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected(($filters['business_id'] ?? null) == $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-lg-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['Pending','Received','Rejected','Failed','Refunded'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-lg-2"><label class="form-label">Method</label><select name="method" class="form-select"><option value="">All methods</option>@foreach(['Cash','Bank Transfer','Jazz Cash','Easypaisa','Cheque','Other'] as $method)<option value="{{ $method }}" @selected(($filters['method'] ?? null) === $method)>{{ $method }}</option>@endforeach</select></div>
            <div class="col-xl-1"><label class="form-label">Date From</label><input name="date_from" type="text" inputmode="numeric" placeholder="8/9/2026" value="{{ $compactDate($filters['date_from'] ?? null) }}" class="form-control"></div>
            <div class="col-xl-1"><label class="form-label">Date To</label><input name="date_to" type="text" inputmode="numeric" placeholder="8/9/2026" value="{{ $compactDate($filters['date_to'] ?? null) }}" class="form-control"></div>
            <div class="col-xl-1 d-flex gap-1"><button class="btn btn-outline-primary tf-billing-filter-button flex-fill" type="submit">Filter</button><a href="{{ route('admin.payments') }}" class="btn btn-outline-secondary tf-billing-filter-button" title="Clear"><i class="bi bi-arrow-counterclockwise"></i></a></div>
        </form>
    </section>

    <section class="tf-card tf-billing-table-card">
        <div class="table-responsive tf-dropdown-safe-scroll">
            <table class="table tf-admin-data-table mb-0 tf-billing-table">
                <thead><tr><th>Payment ID</th><th>Business</th><th>Amount</th><th>Method</th><th>Reference</th><th>Access Period</th><th>Status</th><th>Submitted</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse($payments as $payment)
                        @php
                            $tone = match($payment->status) {
                                'Received' => 'tf-badge-success',
                                'Pending' => 'tf-badge-warning',
                                'Rejected', 'Failed' => 'tf-badge-danger',
                                default => 'tf-badge-secondary',
                            };
                        @endphp
                        <tr>
                            <td><span class="tf-billing-id">{{ $payment->reference_number ?? '#'.$payment->id }}</span></td>
                            <td><strong class="tf-billing-business">{{ $payment->business?->business_name ?? '—' }}</strong></td>
                            <td class="text-nowrap fw-semibold">Rs {{ number_format($payment->amount, 2) }}</td>
                            <td>{{ $payment->method }}</td>
                            <td><span class="tf-billing-reference" title="{{ $payment->transaction_reference }}">{{ $payment->transaction_reference ?: '—' }}</span></td>
                            <td class="tf-billing-period">{{ $payment->period_starts_at?->format('n/j/Y') ?? '—' }} <i class="bi bi-arrow-right"></i> {{ $payment->period_ends_at?->format('n/j/Y') ?? '—' }}</td>
                            <td><span class="tf-badge {{ $tone }} text-nowrap">{{ $payment->status === 'Received' ? 'Verified / Paid' : $payment->status }}</span></td>
                            <td class="text-nowrap">{{ ($payment->submitted_at ?? $payment->paid_at)?->format('n/j/Y, g:i A') ?? '—' }}</td>
                            <td class="text-end text-nowrap"><div class="btn-group"><button class="btn btn-sm btn-outline-primary tf-billing-table-button" type="button" data-bs-toggle="modal" data-bs-target="#payment{{ $payment->id }}">View</button><button class="btn btn-sm btn-outline-secondary tf-billing-more-button dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-tf-payment-dropdown="1" aria-label="More payment actions"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end tf-payment-actions">
                                @if($payment->status === 'Received')
                                    <li><a class="dropdown-item" href="{{ route('admin.payments.receipt', $payment) }}" target="_blank" rel="noopener">Download Receipt (PDF)</a></li>
                                @endif
                                @if($payment->payment_proof)
                                    <li><a class="dropdown-item" href="{{ route('admin.payments.proof', $payment) }}">View Proof</a></li>
                                @endif
                                <li><a class="dropdown-item" href="{{ route('admin.subscriptions', ['business_id' => $payment->business_id, 'manage' => 1, 'payment_id' => $payment->id]) }}">Manage Access</a></li>
                                @if($payment->status === 'Pending')
                                    <li><button class="dropdown-item text-success" type="button" data-bs-toggle="modal" data-bs-target="#approve{{ $payment->id }}">Verify Payment</button></li>
                                    <li><button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#reject{{ $payment->id }}">Reject Payment</button></li>
                                @endif
                                @if($payment->can_delete)
                                    <li><hr class="dropdown-divider"></li>
                                    <li><form method="POST" action="{{ route('admin.payments.destroy', $payment) }}" data-payment-delete-form data-id="{{ $payment->reference_number ?? '#'.$payment->id }}" data-business="{{ $payment->business?->business_name ?? 'this business' }}" data-amount="Rs {{ number_format($payment->amount, 2) }}">@csrf @method('DELETE')<button type="submit" class="dropdown-item text-danger">Delete Payment Record</button></form></li>
                                @else
                                    <li><hr class="dropdown-divider"></li>
                                    <li><span class="dropdown-item disabled" title="End active paid access before deleting this payment.">Delete Payment</span></li>
                                @endif
                            </ul></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center tf-muted py-5">No payments match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3"><x-table-result-summary :paginator="$payments" />{{ $payments->links('pagination::bootstrap-5') }}</div>
    </section>
</div>

@foreach($payments as $payment)
    <div class="modal fade tf-payment-modal" id="payment{{ $payment->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5">Payment Details</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-5 tf-muted">Payment ID</dt><dd class="col-7">{{ $payment->reference_number ?? '#'.$payment->id }}</dd><dt class="col-5 tf-muted">Business</dt><dd class="col-7">{{ $payment->business?->business_name }}</dd><dt class="col-5 tf-muted">Agreed amount</dt><dd class="col-7">Rs {{ number_format($payment->amount, 2) }}</dd><dt class="col-5 tf-muted">Method</dt><dd class="col-7">{{ $payment->method }}</dd><dt class="col-5 tf-muted">Reference</dt><dd class="col-7">{{ $payment->transaction_reference ?: '—' }}</dd><dt class="col-5 tf-muted">Status</dt><dd class="col-7">{{ $payment->status === 'Received' ? 'Verified / Paid' : $payment->status }}</dd><dt class="col-5 tf-muted">Access period</dt><dd class="col-7">{{ $payment->period_starts_at?->format('n/j/Y') ?? '—' }} → {{ $payment->period_ends_at?->format('n/j/Y') ?? '—' }}</dd><dt class="col-5 tf-muted">Submitted</dt><dd class="col-7">{{ ($payment->submitted_at ?? $payment->paid_at)?->format('n/j/Y, g:i A') ?? '—' }}</dd><dt class="col-5 tf-muted">Recorded by</dt><dd class="col-7">{{ $payment->recordedBy?->name ?? '—' }}</dd><dt class="col-5 tf-muted">Verified by</dt><dd class="col-7">{{ $payment->verifiedBy?->name ?? '—' }}</dd></dl></div></div></div></div>
    @if($payment->status === 'Pending')
        <div class="modal fade" id="approve{{ $payment->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form method="POST" action="{{ route('admin.payments.approve', $payment) }}" class="modal-content">@csrf @method('PATCH')<div class="modal-body">Verify this payment and activate its saved access period?</div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" type="submit">Verify Payment</button></div></form></div></div>
        <div class="modal fade" id="reject{{ $payment->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="modal-content">@csrf @method('PATCH')<div class="modal-body"><label class="form-label">Reason</label><textarea name="rejection_reason" required class="form-control"></textarea></div><div class="modal-footer"><button type="submit" class="btn btn-danger">Reject Payment</button></div></form></div></div>
    @endif
@endforeach
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-payment-delete-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (form.dataset.confirmed === '1' || form.dataset.submitting === '1') return;
        event.preventDefault();
        if (!window.Swal) return;
        Swal.fire({
            icon: 'warning',
            title: 'Delete this payment record permanently?',
            text: 'Payment ID: ' + form.dataset.id + ' · Business: ' + form.dataset.business + ' · Amount: ' + form.dataset.amount + '. This does not delete the business or its access record.',
            showCancelButton: true,
            confirmButtonText: 'Delete Payment Record',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#dc3545',
            reverseButtons: true,
        }).then(function (result) {
            if (!result.isConfirmed) return;
            form.dataset.confirmed = '1';
            form.dataset.submitting = '1';
            form.querySelector('button').disabled = true;
            form.submit();
        });
    });
});
</script>
@endpush
