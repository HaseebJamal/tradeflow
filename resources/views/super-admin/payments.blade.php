@extends('layouts.dashboard')

@section('page-title', 'Payments & Billing')
@section('page-subtitle', 'Record negotiated charges, payment references, receipts, and paid access periods')

@section('content')
@php
    $showPaymentForm = !request()->has('record') || request()->boolean('record');
    $compactDate = static fn ($value) => filled($value) ? \Illuminate\Support\Carbon::parse($value)->format('n/j/Y') : '';
    $selectedPaymentBusinessId = old('business_id', $recordRenewal?->business_id ?? ($filters['business_id'] ?? null));
    $initialPaymentDates = $paymentDateDefaults[(int) $selectedPaymentBusinessId] ?? [
        'starts_at' => $paymentNow->toDateString(),
        'ends_at' => $paymentNow->copy()->addDays($defaultPaidAccessDays)->toDateString(),
    ];
@endphp

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="tf-billing-workspace">
    <nav class="nav nav-tabs mb-4" aria-label="Payments and renewal invoices">
        <a class="nav-link {{ $tab === 'payments' ? 'active' : '' }}" href="{{ route('admin.payments', ['tab' => 'payments']) }}">Payment Records</a>
        <a class="nav-link {{ $tab === 'renewals' ? 'active' : '' }}" href="{{ route('admin.payments', ['tab' => 'renewals']) }}">Renewal Invoices</a>
    </nav>

    @if($tab === 'payments')
    <section class="tf-card tf-billing-form-card mb-4">
        <div class="tf-billing-card-heading">
            <div><h2>Custom Business Payment</h2><p>Record the negotiated amount and paid access period for one business.</p></div>
            <a href="{{ route('admin.payments', array_merge(request()->except('record'), ['record' => $showPaymentForm ? 0 : 1])) }}" class="btn btn-outline-primary tf-billing-button">{{ $showPaymentForm ? 'Hide Form' : 'Show Form' }}</a>
        </div>

        @if($showPaymentForm)
            <form method="POST" action="{{ route('admin.payments.store') }}" class="tf-billing-payment-form">
                @csrf
                @if($recordRenewal)<input type="hidden" name="renewal_invoice_id" value="{{ $recordRenewal->id }}">@endif
                <fieldset>
                    @if($recordRenewal)<p class="small tf-muted mb-3">Recording renewal {{ $recordRenewal->invoice_number }} for {{ $recordRenewal->business?->business_name }}. Confirm the negotiated amount and new access dates before saving.</p>@endif
                    <legend>Payment Details</legend>
                    <div class="row g-3">
                        <div class="col-xl-4 col-lg-4"><label class="form-label">Business</label><select id="paymentBusiness" name="business_id" class="form-select" data-tom-select-inline="true" required><option value="">Select business</option>@foreach($businesses as $business)@php $businessPaymentDates = $paymentDateDefaults[$business->id]; @endphp<option value="{{ $business->id }}" data-access-start="{{ $businessPaymentDates['starts_at'] }}" data-access-end="{{ $businessPaymentDates['ends_at'] }}" @selected($selectedPaymentBusinessId == $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
                        <div class="col-xl-2 col-md-3"><label class="form-label">Agreed Amount</label><input name="amount" type="number" min="1" step="1" value="{{ old('amount', $recordRenewal?->amount) }}" class="form-control" required></div>
                        <div class="col-xl-2 col-md-3"><label class="form-label">Payment Method</label><select name="method" class="form-select" data-tom-select-inline="true" required>@foreach(['Cash','Bank Transfer','Jazz Cash','Easypaisa','Cheque','Other'] as $method)<option value="{{ $method }}" @selected(old('method', $recordRenewal?->last_payment_method) === $method)>{{ $method }}</option>@endforeach</select></div>
                        <div class="col-xl-2 col-md-3"><label class="form-label">Payment Status</label><select name="status" class="form-select" data-tom-select-inline="true" required>@foreach(['Received','Pending'] as $status)<option value="{{ $status }}" @selected(old('status', 'Received') === $status)>{{ $status }}</option>@endforeach</select></div>
                        <div class="col-xl-2 col-md-3"><label class="form-label">Payment Date</label><input name="paid_at" type="date" value="{{ old('paid_at', $paymentNow->toDateString()) }}" class="form-control" required></div>
                    </div>
                </fieldset>
                <fieldset>
                    <legend>Access Period</legend>
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-3 col-md-4"><label class="form-label">Access Start Date</label><input id="paymentAccessStart" name="period_starts_at" type="date" value="{{ old('period_starts_at', $initialPaymentDates['starts_at']) }}" class="form-control @error('period_starts_at') is-invalid @enderror">@error('period_starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-lg-3 col-md-4"><label class="form-label">Access End Date</label><input id="paymentAccessEnd" name="period_ends_at" type="date" min="{{ \Illuminate\Support\Carbon::parse($initialPaymentDates['starts_at'])->addDay()->toDateString() }}" value="{{ old('period_ends_at', $initialPaymentDates['ends_at']) }}" class="form-control @error('period_ends_at') is-invalid @enderror">@error('period_ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
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
            <div class="col-xl-2 col-lg-2"><label class="form-label">Business</label><select name="business_id" class="form-select" data-tom-select-inline="true"><option value="">All businesses</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected(($filters['business_id'] ?? null) == $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-lg-2"><label class="form-label">Status</label><select name="status" class="form-select" data-tom-select-inline="true"><option value="">All statuses</option>@foreach(['Pending','Received','Rejected','Failed','Refunded'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? null) === $status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-lg-2"><label class="form-label">Method</label><select name="method" class="form-select" data-tom-select-inline="true"><option value="">All methods</option>@foreach(['Cash','Bank Transfer','Jazz Cash','Easypaisa','Cheque','Other'] as $method)<option value="{{ $method }}" @selected(($filters['method'] ?? null) === $method)>{{ $method }}</option>@endforeach</select></div>
            <div class="col-xl-1"><label class="form-label">Date From</label><input name="date_from" type="text" inputmode="numeric" placeholder="8/9/2026" value="{{ $compactDate($filters['date_from'] ?? null) }}" class="form-control"></div>
            <div class="col-xl-1"><label class="form-label">Date To</label><input name="date_to" type="text" inputmode="numeric" placeholder="8/9/2026" value="{{ $compactDate($filters['date_to'] ?? null) }}" class="form-control"></div>
            <div class="col-xl-1 d-flex gap-1"><button class="btn btn-outline-primary tf-billing-filter-button flex-fill" type="submit">Filter</button><a href="{{ route('admin.payments') }}" class="btn btn-outline-secondary tf-billing-filter-button" title="Clear"><i class="bi bi-arrow-counterclockwise"></i></a></div>
        </form>
    </section>

    <section class="tf-card tf-billing-table-card">
        <div class="table-responsive tf-dropdown-safe-scroll">
            <table class="table tf-admin-data-table mb-0 tf-billing-table tf-has-actions-column">
                <thead><tr><th>Payment ID</th><th>Business</th><th>Amount</th><th>Method</th><th>Reference</th><th>Access Period</th><th>Status</th><th>Submitted</th><th class="text-end tf-table-action-cell">Actions</th></tr></thead>
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
                            <td class="text-end text-nowrap tf-table-action-cell"><div class="btn-group tf-table-action-group"><button class="btn btn-sm btn-outline-primary tf-billing-table-button" type="button" data-bs-toggle="modal" data-bs-target="#payment{{ $payment->id }}">View</button><button class="btn btn-sm btn-outline-secondary tf-billing-more-button dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-tf-payment-dropdown="1" aria-label="More payment actions"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end tf-payment-actions">
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
    @else
    <section class="tf-card tf-billing-table-card tf-renewal-invoices-card">
        <div class="tf-renewal-invoices-heading"><h2>Renewal Invoices</h2><p>Automatic reminders for real custom paid-access cycles. Suggested amounts come from the latest agreed payment and remain editable before recording payment.</p></div>
        <div class="table-responsive tf-dropdown-safe-scroll tf-renewal-invoices-scroll">
            <table class="table tf-admin-data-table tf-billing-table tf-renewal-invoice-table mb-0 tf-has-actions-column">
                <colgroup>
                    <col class="tf-renewal-col-invoice"><col class="tf-renewal-col-business"><col class="tf-renewal-col-amount"><col class="tf-renewal-col-access-end"><col class="tf-renewal-col-due"><col class="tf-renewal-col-status"><col class="tf-renewal-col-delivery"><col class="tf-renewal-col-created"><col class="tf-renewal-col-actions">
                </colgroup>
                <thead><tr><th class="tf-renewal-col-invoice">Invoice ID</th><th class="tf-renewal-col-business">Business</th><th class="tf-renewal-col-amount">Amount</th><th class="tf-renewal-col-access-end">Access End</th><th class="tf-renewal-col-due">Renewal Due</th><th class="tf-renewal-col-status">Status</th><th class="tf-renewal-col-delivery">Sent Via</th><th class="tf-renewal-col-created">Created</th><th class="text-end tf-table-action-cell tf-renewal-col-actions">Actions</th></tr></thead>
                <tbody>@forelse($renewalInvoices as $invoice)
                    @php($tone = match($invoice->status) { 'Paid' => 'tf-badge-success', 'Cancelled', 'Superseded', 'Overdue' => 'tf-badge-danger', 'Pending Payment' => 'tf-badge-warning', default => 'tf-badge-info' })
                    <tr data-renewal-invoice-row="{{ $invoice->id }}">
                        <td class="tf-renewal-invoice-id">{{ $invoice->invoice_number }}</td><td class="tf-renewal-business-cell"><strong class="tf-renewal-business-name">{{ $invoice->business?->business_name }}</strong><small class="tf-renewal-owner">{{ $invoice->business?->owner?->name ?? '—' }}</small></td>
                        <td class="tf-renewal-amount">Rs {{ number_format((float) $invoice->amount, 2) }}</td><td class="tf-renewal-date">{{ $invoice->access_ends_at->format('n/j/Y') }}</td><td class="tf-renewal-date">{{ $invoice->due_date->format('n/j/Y') }}</td>
                        <td data-renewal-status class="tf-renewal-status"><span class="tf-badge {{ $tone }}">{{ $invoice->status }}</span></td><td data-renewal-delivery class="tf-renewal-delivery"><span>{{ $invoice->email_sent_at ? 'Email sent' : ($invoice->email_draft_opened_at ? 'Email draft opened' : ($invoice->whatsapp_opened_at ? 'WhatsApp draft opened' : 'Not sent')) }}</span></td><td class="tf-renewal-created">{{ $invoice->created_at->format('n/j/Y, g:i A') }}</td>
                        <td class="text-end text-nowrap tf-table-action-cell"><div class="btn-group tf-table-action-group"><button class="btn btn-sm btn-outline-primary tf-billing-table-button" type="button" data-bs-toggle="modal" data-bs-target="#renewal{{ $invoice->id }}">View</button><button class="btn btn-sm btn-outline-secondary tf-billing-more-button dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-label="More renewal invoice actions"></button><ul class="dropdown-menu dropdown-menu-end tf-payment-actions">
                            <li><a class="dropdown-item" href="{{ route('admin.renewal-invoices.pdf', $invoice) }}" target="_blank" rel="noopener">Download PDF</a></li>
                            @if($invoice->can_manage)<li><form method="POST" action="{{ route('admin.renewal-invoices.email', $invoice) }}">@csrf<button class="dropdown-item" type="submit">Send by Email</button></form></li><li><a class="dropdown-item" href="{{ route('admin.renewal-invoices.whatsapp', $invoice) }}" target="_blank" rel="noopener">Send by WhatsApp</a></li><li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#amount{{ $invoice->id }}">Update Amount</button></li><li><a class="dropdown-item text-success" href="{{ route('admin.payments', ['record' => 1, 'renewal_invoice_id' => $invoice->id]) }}">Record Payment</a></li><li><hr class="dropdown-divider"></li><li><form method="POST" action="{{ route('admin.renewal-invoices.cancel', $invoice) }}">@csrf @method('PATCH')<button class="dropdown-item text-danger" type="submit">Cancel Invoice</button></form></li>@endif
                        </ul></div></td>
                    </tr>
                @empty<tr><td colspan="9" class="text-center tf-muted py-5">No renewal invoices have been generated yet.</td></tr>@endforelse</tbody>
            </table>
        </div>
        <div class="p-3"><x-table-result-summary :paginator="$renewalInvoices" />{{ $renewalInvoices->links('pagination::bootstrap-5') }}</div>
    </section>
    @endif
</div>

@foreach($payments as $payment)
    <div class="modal fade tf-payment-modal" id="payment{{ $payment->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5">Payment Details</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-5 tf-muted">Payment ID</dt><dd class="col-7">{{ $payment->reference_number ?? '#'.$payment->id }}</dd><dt class="col-5 tf-muted">Business</dt><dd class="col-7">{{ $payment->business?->business_name }}</dd><dt class="col-5 tf-muted">Agreed amount</dt><dd class="col-7">Rs {{ number_format($payment->amount, 2) }}</dd><dt class="col-5 tf-muted">Method</dt><dd class="col-7">{{ $payment->method }}</dd><dt class="col-5 tf-muted">Reference</dt><dd class="col-7">{{ $payment->transaction_reference ?: '—' }}</dd><dt class="col-5 tf-muted">Status</dt><dd class="col-7">{{ $payment->status === 'Received' ? 'Verified / Paid' : $payment->status }}</dd><dt class="col-5 tf-muted">Access period</dt><dd class="col-7">{{ $payment->period_starts_at?->format('n/j/Y') ?? '—' }} → {{ $payment->period_ends_at?->format('n/j/Y') ?? '—' }}</dd><dt class="col-5 tf-muted">Submitted</dt><dd class="col-7">{{ ($payment->submitted_at ?? $payment->paid_at)?->format('n/j/Y, g:i A') ?? '—' }}</dd><dt class="col-5 tf-muted">Recorded by</dt><dd class="col-7">{{ $payment->recordedBy?->name ?? '—' }}</dd><dt class="col-5 tf-muted">Verified by</dt><dd class="col-7">{{ $payment->verifiedBy?->name ?? '—' }}</dd></dl></div></div></div></div>
    @if($payment->status === 'Pending')
        <div class="modal fade" id="approve{{ $payment->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form method="POST" action="{{ route('admin.payments.approve', $payment) }}" class="modal-content">@csrf @method('PATCH')<div class="modal-body">Verify this payment and activate its saved access period?</div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" type="submit">Verify Payment</button></div></form></div></div>
        <div class="modal fade" id="reject{{ $payment->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="modal-content">@csrf @method('PATCH')<div class="modal-body"><label class="form-label">Reason</label><textarea name="rejection_reason" required class="form-control"></textarea></div><div class="modal-footer"><button type="submit" class="btn btn-danger">Reject Payment</button></div></form></div></div>
    @endif
@endforeach

@foreach($renewalInvoices as $invoice)
    <div class="modal fade tf-payment-modal" id="renewal{{ $invoice->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5">Renewal Invoice</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-5 tf-muted">Invoice number</dt><dd class="col-7">{{ $invoice->invoice_number }}</dd><dt class="col-5 tf-muted">Business</dt><dd class="col-7">{{ $invoice->business?->business_name }}</dd><dt class="col-5 tf-muted">Owner</dt><dd class="col-7">{{ $invoice->business?->owner?->name ?? '—' }}</dd><dt class="col-5 tf-muted">Registration email</dt><dd class="col-7">{{ $invoice->business?->owner?->email ?? '—' }}</dd><dt class="col-5 tf-muted">Phone / WhatsApp</dt><dd class="col-7">{{ $invoice->business?->phone ?: ($invoice->business?->owner?->phone ?: '—') }}</dd><dt class="col-5 tf-muted">Current paid access</dt><dd class="col-7">{{ $invoice->access_starts_at?->format('n/j/Y') ?? '—' }} → {{ $invoice->access_ends_at->format('n/j/Y') }}</dd><dt class="col-5 tf-muted">Days remaining</dt><dd class="col-7">{{ max(0, (int) now(config('app.timezone'))->diffInDays($invoice->access_ends_at->copy()->endOfDay(), false)) }} days</dd><dt class="col-5 tf-muted">Proposed amount</dt><dd class="col-7">Rs {{ number_format((float) $invoice->amount, 2) }}</dd><dt class="col-5 tf-muted">Due date</dt><dd class="col-7">{{ $invoice->due_date->format('n/j/Y') }}</dd><dt class="col-5 tf-muted">Status</dt><dd class="col-7">{{ $invoice->status }}</dd><dt class="col-5 tf-muted">Delivery history</dt><dd class="col-7">Generated {{ $invoice->created_at->format('n/j/Y, g:i A') }}@if($invoice->email_sent_at)<br>Email sent {{ $invoice->email_sent_at->format('n/j/Y, g:i A') }}@endif @if($invoice->whatsapp_opened_at)<br>WhatsApp click-to-chat opened {{ $invoice->whatsapp_opened_at->format('n/j/Y, g:i A') }}@endif @if($invoice->email_error)<br><span class="text-danger">Latest email attempt failed.</span>@endif</dd></dl></div><div class="modal-footer"><a class="btn btn-outline-primary" href="{{ route('admin.renewal-invoices.pdf', $invoice) }}" target="_blank" rel="noopener">Download PDF</a>@if($invoice->can_manage)<form method="POST" action="{{ route('admin.renewal-invoices.email', $invoice) }}">@csrf<button class="btn btn-outline-primary" type="submit">Send Email</button></form><a class="btn btn-outline-success" href="{{ route('admin.renewal-invoices.whatsapp', $invoice) }}" target="_blank" rel="noopener">Send WhatsApp</a>@endif</div></div></div></div>
    @if($invoice->email_draft_opened_at)<span hidden data-renewal-email-draft-history="{{ $invoice->id }}" data-opened-at="{{ $invoice->email_draft_opened_at->format('n/j/Y, g:i A') }}"></span>@endif
    @if($invoice->can_manage)
        <div class="modal fade" id="amount{{ $invoice->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.renewal-invoices.amount', $invoice) }}">@csrf @method('PATCH')<div class="modal-header"><h2 class="modal-title h5">Update custom renewal amount</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="tf-muted small">Confirm the negotiated amount for {{ $invoice->business?->business_name }} before recording payment.</p><label class="form-label" for="renewalAmount{{ $invoice->id }}">Agreed amount</label><input class="form-control" id="renewalAmount{{ $invoice->id }}" name="amount" type="number" min="1" step="0.01" value="{{ $invoice->amount }}" required></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tf-primary" type="submit">Save Amount</button></div></form></div></div>
    @endif
@endforeach

<div class="modal fade" id="renewalInvoiceDeliveryConfirm" data-bs-backdrop="false" data-tf-nested-modal tabindex="-1" aria-labelledby="renewalInvoiceDeliveryConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="renewalInvoiceDeliveryForm" method="POST" target="renewal-invoice-draft" class="modal-content">
            @csrf
            <div class="modal-header">
                <h2 id="renewalInvoiceDeliveryConfirmTitle" class="modal-title h5">Send renewal invoice</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="renewalInvoiceDeliveryConfirmMessage" class="mb-3"></p>
                <div id="renewalInvoiceDeliveryError" class="alert alert-danger d-none mb-0" role="alert"></div>
                <dl id="renewalInvoiceDeliveryDetails" class="row mb-0 small">
                    <dt class="col-5 tf-muted">Business</dt><dd class="col-7" data-renewal-detail="business"></dd>
                    <dt class="col-5 tf-muted" data-renewal-recipient-label>Registration Email</dt><dd class="col-7" data-renewal-detail="recipient"></dd>
                    <dt class="col-5 tf-muted">Invoice Number</dt><dd class="col-7" data-renewal-detail="invoice"></dd>
                    <dt class="col-5 tf-muted">Access End Date</dt><dd class="col-7" data-renewal-detail="access-end"></dd>
                    <dt class="col-5 tf-muted">Proposed Amount</dt><dd class="col-7" data-renewal-detail="amount"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                <button id="renewalInvoiceDeliveryContinue" class="btn btn-tf-primary" type="submit">Continue</button>
            </div>
        </form>
    </div>
</div>
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

(function () {
    var business = document.getElementById('paymentBusiness');
    var start = document.getElementById('paymentAccessStart');
    var end = document.getElementById('paymentAccessEnd');

    if (!business || !start || !end) return;

    function dayAfter(dateValue) {
        if (!dateValue) return '';
        var parts = dateValue.split('-').map(Number);
        var date = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2] + 1));
        return date.toISOString().slice(0, 10);
    }

    business.addEventListener('change', function () {
        var selected = business.options[business.selectedIndex];
        if (!selected || !selected.dataset.accessStart || !selected.dataset.accessEnd) return;

        // Selecting a business intentionally loads the current server-derived
        // renewal/default dates. The date inputs remain freely editable after.
        start.value = selected.dataset.accessStart;
        end.value = selected.dataset.accessEnd;
        end.min = dayAfter(selected.dataset.accessStart);
    });

    start.addEventListener('change', function () {
        end.min = dayAfter(start.value);
    });
})();

(function () {
    var confirmModal = document.getElementById('renewalInvoiceDeliveryConfirm');
    var deliveryForm = document.getElementById('renewalInvoiceDeliveryForm');
    if (!confirmModal || !deliveryForm || !window.bootstrap || confirmModal.dataset.renewalDeliveryReady === 'true') return;
    confirmModal.dataset.renewalDeliveryReady = 'true';

    var modalInstance = bootstrap.Modal.getOrCreateInstance(confirmModal);
    var title = document.getElementById('renewalInvoiceDeliveryConfirmTitle');
    var message = document.getElementById('renewalInvoiceDeliveryConfirmMessage');
    var error = document.getElementById('renewalInvoiceDeliveryError');
    var details = document.getElementById('renewalInvoiceDeliveryDetails');
    var continueButton = document.getElementById('renewalInvoiceDeliveryContinue');
    var recipientLabel = confirmModal.querySelector('[data-renewal-recipient-label]');
    var sourceModal = null;
    var deliveryActionOpen = false;
    var deliverySubmitting = false;

    function valuesFor(sourceUrl) {
        var match = new URL(sourceUrl, window.location.origin).pathname.match(/\/renewals\/(\d+)\/(?:email|whatsapp)$/);
        var invoiceModal = match ? document.getElementById('renewal' + match[1]) : null;
        var values = {};
        invoiceModal?.querySelectorAll('dt').forEach(function (term) {
            values[term.textContent.trim()] = term.nextElementSibling?.textContent.trim() || '';
        });
        var draftHistory = match ? document.querySelector('[data-renewal-email-draft-history="' + match[1] + '"]') : null;
        if (draftHistory) values['Email draft opened'] = draftHistory.dataset.openedAt || '';
        return values;
    }

    function accessEnd(value) {
        var match = (value || '').match(/\d{1,2}\/\d{1,2}\/\d{4}\s*$/);
        return match ? match[0].trim() : (value || '').trim();
    }

    function setDetail(name, value) {
        var target = confirmModal.querySelector('[data-renewal-detail="' + name + '"]');
        if (target) target.textContent = value || '—';
    }

    function appendDraftHistory(invoiceModal, openedAt) {
        if (!invoiceModal || !openedAt) return;
        var historyTerm = Array.from(invoiceModal.querySelectorAll('dt')).find(function (term) {
            return term.textContent.trim() === 'Delivery history';
        });
        var history = historyTerm?.nextElementSibling;
        if (!history || history.dataset.emailDraftOpened === '1') return;
        history.append(document.createElement('br'), 'Email draft opened ' + openedAt);
        history.dataset.emailDraftOpened = '1';
    }

    document.querySelectorAll('[data-renewal-email-draft-history]').forEach(function (entry) {
        appendDraftHistory(document.getElementById('renewal' + entry.dataset.renewalEmailDraftHistory), entry.dataset.openedAt);
    });

    function showDelivery(source, channel, origin) {
        if (deliveryActionOpen || deliverySubmitting) return;

        var values = valuesFor(source);
        var recipient = channel === 'email' ? values['Registration email'] : values['Phone / WhatsApp'];
        var valid = channel === 'email'
            ? /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipient || '')
            : /^[1-9]\d{7,14}$/.test((recipient || '').replace(/\D/g, ''));
        var isEmail = channel === 'email';

        deliveryActionOpen = true;
        sourceModal = origin?.closest('.modal') || document.activeElement?.closest('.modal') || null;
        title.textContent = isEmail ? 'Open email draft?' : 'Send renewal invoice by WhatsApp?';
        message.textContent = isEmail
            ? 'This will open a pre-filled renewal email draft for ' + recipient + '.'
            : 'This will open WhatsApp with a prefilled renewal message. You can review it before sending.';
        recipientLabel.textContent = isEmail ? 'Registration Email' : 'Phone / WhatsApp';
        setDetail('business', values.Business);
        setDetail('recipient', recipient);
        setDetail('invoice', values['Invoice number']);
        setDetail('access-end', accessEnd(values['Current paid access']));
        setDetail('amount', values['Proposed amount']);
        appendDraftHistory(sourceModal, values['Email draft opened']);
        deliveryForm.action = source;
        deliveryForm.target = '_blank';
        details.classList.toggle('d-none', !valid);
        error.classList.toggle('d-none', valid);
        error.textContent = valid ? '' : (isEmail ? 'No business email is available.' : 'No WhatsApp/phone number is available.');
        continueButton.classList.toggle('d-none', !valid);
        continueButton.classList.toggle('btn-tf-primary', isEmail);
        continueButton.classList.toggle('btn-success', !isEmail);
        continueButton.textContent = 'Continue';

        if (typeof window.openTradeFlowNestedModal === 'function') {
            window.openTradeFlowNestedModal(confirmModal, sourceModal);
        } else {
            modalInstance.show();
        }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[action*="/payments/renewals/"][action$="/email"]');
        if (!form || form === deliveryForm) return;
        event.preventDefault();
        event.stopPropagation();
        showDelivery(form.action, 'email', form);
    });
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href*="/payments/renewals/"][href$="/whatsapp"]');
        if (!link) return;
        event.preventDefault();
        event.stopPropagation();
        showDelivery(link.href, 'whatsapp', link);
    });
    function updateDraftState(source, channel, status) {
        var match = new URL(source, window.location.origin).pathname.match(/\/renewals\/(\d+)\/(?:email|whatsapp)$/);
        if (!match) return;

        var row = document.querySelector('[data-renewal-invoice-row="' + match[1] + '"]');
        var statusCell = row?.querySelector('[data-renewal-status]');
        var deliveryCell = row?.querySelector('[data-renewal-delivery]');
        if (statusCell) {
            statusCell.replaceChildren();
            var badge = document.createElement('span');
            var nextStatus = status || 'Pending Payment';
            badge.className = 'tf-badge ' + (nextStatus === 'Overdue' ? 'tf-badge-danger' : 'tf-badge-warning');
            badge.textContent = nextStatus;
            statusCell.appendChild(badge);
        }
        if (deliveryCell) deliveryCell.textContent = channel === 'email' ? 'Email draft opened' : 'WhatsApp draft opened';

        var invoiceModal = document.getElementById('renewal' + match[1]);
        var statusTerm = Array.from(invoiceModal?.querySelectorAll('dt') || []).find(function (term) {
            return term.textContent.trim() === 'Status';
        });
        if (statusTerm?.nextElementSibling) statusTerm.nextElementSibling.textContent = status || 'Pending Payment';
    }

    deliveryForm.addEventListener('submit', function (event) {
        if (deliverySubmitting) {
            event.preventDefault();
            return;
        }

        deliverySubmitting = true;
        continueButton.disabled = true;
        // Allow the confirmed form to submit normally into its target tab.
        // The controller redirects that tab directly to Gmail/WhatsApp, so no
        // empty window is opened first and the current Profit Point modal/tab
        // stays intact.
        modalInstance.hide();
    });
    confirmModal.addEventListener('hidden.bs.modal', function () {
        if (typeof window.clearTradeFlowNestedModal === 'function') {
            window.clearTradeFlowNestedModal(confirmModal);
        }

        window.setTimeout(function () {
            deliveryActionOpen = false;
            deliverySubmitting = false;
            continueButton.disabled = false;
        }, 300);
        sourceModal = null;
    });
})();
</script>
@endpush
