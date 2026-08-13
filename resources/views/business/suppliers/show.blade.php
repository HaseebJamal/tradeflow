@extends('layouts.dashboard')
@section('page-title', 'Supplier Profile')
@section('page-subtitle', $supplier->supplier_name)
@section('content')
<div class="row g-4 mb-4">
    <div class="col-md-3"><x-card label="Current Payable" :value="'Rs '.number_format($remainingPayable, 2)" icon="bi-wallet2" color="bg-amber" /></div>
    <div class="col-md-3"><x-card label="Received Value" :value="'Rs '.number_format($receivedValue, 2)" icon="bi-box-seam" color="bg-blue" /></div>
    <div class="col-md-3"><x-card label="Overdue Payable" :value="'Rs '.number_format($overduePayable, 2)" icon="bi-exclamation-circle" color="bg-red" /></div>
    <div class="col-md-3"><x-card label="Available Advances" :value="'Rs '.number_format($availableAdvances, 2)" icon="bi-cash-stack" color="bg-green" /></div>
</div>

<div class="tf-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h2 class="h5 mb-1">{{ $supplier->supplier_name }}</h2>
            <div class="tf-muted">{{ $supplier->company_name ?: 'Independent supplier' }}</div>
        </div>
        @companyCan('suppliers.edit')<a href="{{ route('business.suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-primary">Edit Supplier</a>@endcompanyCan
    </div>
    <div class="row g-3 mt-3">
        <div class="col-md-3"><strong>Phone</strong><div>{{ $supplier->phone ?: '-' }}</div></div>
        <div class="col-md-3"><strong>Email</strong><div>{{ $supplier->email ?: '-' }}</div></div>
        <div class="col-md-3"><strong>City</strong><div>{{ $supplier->city ?: '-' }}</div></div>
        <div class="col-md-3"><strong>Status</strong><div>{{ $supplier->status }}</div></div>
        <div class="col-12"><strong>Address</strong><div>{{ $supplier->address ?: '-' }}</div></div>
    </div>
</div>

<div class="tf-card p-4 tf-supplier-statement-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><h2 class="h5 mb-0">Supplier Statement</h2><form class="tf-supplier-statement-filters"><div><label class="form-label" for="supplierStatementDateFrom">Date From</label><input id="supplierStatementDateFrom" type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control"></div><div><label class="form-label" for="supplierStatementDateTo">Date To</label><input id="supplierStatementDateTo" type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control"></div><div class="tf-supplier-statement-filter-actions"><button class="btn btn-outline-primary">Filter</button><a href="{{ route('business.suppliers.show', $supplier) }}" class="btn btn-outline-secondary">Clear</a></div></form></div>
    <div class="row g-3 small mb-3 tf-supplier-statement-summary"><div class="col-6 col-md-3"><strong>Opening payable</strong><div>Rs {{ number_format($supplier->opening_balance, 2) }}</div></div><div class="col-6 col-md-3"><strong>Payments</strong><div>Rs {{ number_format($totalPayments, 2) }}</div></div><div class="col-6 col-md-3"><strong>Returns / credits</strong><div>Rs {{ number_format($returnsValue, 2) }}</div></div><div class="col-6 col-md-3"><strong>Current payable</strong><div>Rs {{ number_format($remainingPayable, 2) }}</div></div></div>
    <x-table>
        <thead><tr><th>Date</th><th>Voucher</th><th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead>
        <tbody>
            @php($balance = 0)
            @forelse($lines as $line)
                @php($balance += $line->credit - $line->debit)
                <tr>
                    <td>{{ $line->journalEntry?->entry_date?->format('n/j/Y') }}</td>
                    <td>{{ $line->journalEntry?->voucher_number }}</td>
                    <td>{{ $line->description ?: $line->journalEntry?->description }}</td>
                    <td>Rs {{ number_format($line->debit) }}</td>
                    <td>Rs {{ number_format($line->credit) }}</td>
                    <td>Rs {{ number_format($balance) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center tf-muted py-4">No supplier ledger entries.</td></tr>
            @endforelse
        </tbody>
    </x-table>
</div>
@endsection
