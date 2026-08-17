@extends('layouts.dashboard')

@section('page-title', $type === 'customer' ? 'Customer Aging Detail' : 'Supplier Aging Detail')
@section('page-subtitle', $type === 'customer' ? 'Outstanding receivables by document' : 'Outstanding payables by document')

@section('content')
@php
    $isCustomer = $type === 'customer';
    $partyName = $isCustomer ? $party->display_name : ($party->company_name ?: $party->supplier_name);
    $back = $isCustomer ? route('business.reports.customer-aging', request()->query()) : route('business.reports.supplier-aging', request()->query());
    $labels = ['current' => 'Current', 'days_1_30' => '1–30 Days', 'days_31_60' => '31–60 Days', 'days_61_90' => '61–90 Days', 'days_90_plus' => '90+ Days'];
    $total = $rows->sum('outstanding');
@endphp
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3"><div><a href="{{ $back }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left me-1"></i>Back to Aging</a><h2 class="h5 mb-1">{{ $partyName }}</h2><p class="tf-muted mb-0">As of {{ \Illuminate\Support\Carbon::parse($filters['as_of'])->format('n/j/Y') }} · {{ count($rows) }} open {{ $isCustomer ? 'sale' : 'purchase' }} {{ str('document')->plural(count($rows)) }}</p></div><div class="tf-card p-3"><span class="tf-muted small d-block">Total Outstanding</span><strong class="fs-4">Rs {{ number_format($total, 2) }}</strong></div></div>

<section class="tf-card p-0 overflow-hidden"><x-table><thead><tr><th>{{ $isCustomer ? 'Invoice' : 'Purchase' }}</th><th>{{ $isCustomer ? 'Invoice Date' : 'Purchase Date' }}</th><th>Due Date</th><th class="text-end">Original</th><th class="text-end">Paid</th><th class="text-end">Credits / Returns</th><th class="text-end">Outstanding</th><th class="text-end">Days Overdue</th><th>Bucket</th></tr></thead><tbody>@forelse($rows as $row)<tr><td class="fw-semibold">{{ $row['reference'] ?: '—' }}</td><td>{{ $row['date'] ? \Illuminate\Support\Carbon::parse($row['date'])->format('n/j/Y') : '—' }}</td><td>{{ $row['due_date'] ? \Illuminate\Support\Carbon::parse($row['due_date'])->format('n/j/Y') : '—' }}</td><td class="text-end">Rs {{ number_format($row['original_amount'], 2) }}</td><td class="text-end">Rs {{ number_format($row['paid_amount'], 2) }}</td><td class="text-end">{{ $row['credits'] > 0 ? 'Rs '.number_format($row['credits'], 2) : '—' }}</td><td class="text-end fw-semibold">Rs {{ number_format($row['outstanding'], 2) }}</td><td class="text-end">{{ $row['days_overdue'] ?: '—' }}</td><td><span class="tf-badge {{ $row['bucket'] === 'days_90_plus' ? 'tf-badge-danger' : ($row['bucket'] === 'current' ? 'tf-badge-info' : 'tf-badge-warning') }}">{{ $labels[$row['bucket']] }}</span>@if(! $row['due_date'])<small class="d-block tf-muted mt-1">Aged from document date</small>@endif</td></tr>@empty<tr><td colspan="9" class="text-center tf-muted py-5">No outstanding documents.</td></tr>@endforelse</tbody></x-table></section>
@endsection
