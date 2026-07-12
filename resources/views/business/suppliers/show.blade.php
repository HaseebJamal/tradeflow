@extends('layouts.dashboard')
@section('page-title', 'Supplier Profile')
@section('page-subtitle', $supplier->supplier_name)
@section('content')
<div class="row g-4 mb-4">
    <div class="col-md-4"><x-card label="Total Purchases" :value="'Rs '.number_format($totalPurchases)" icon="bi-box-seam" color="bg-blue" /></div>
    <div class="col-md-4"><x-card label="Total Payments" :value="'Rs '.number_format($totalPayments)" icon="bi-cash-stack" color="bg-green" /></div>
    <div class="col-md-4"><x-card label="Remaining Payable" :value="'Rs '.number_format($remainingPayable)" icon="bi-wallet2" color="bg-amber" /></div>
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

<div class="tf-card p-4">
    <h2 class="h5">Supplier Ledger</h2>
    <x-table>
        <thead><tr><th>Date</th><th>Voucher</th><th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead>
        <tbody>
            @php($balance = 0)
            @forelse($lines as $line)
                @php($balance += $line->credit - $line->debit)
                <tr>
                    <td>{{ $line->journalEntry?->entry_date?->format('M d, Y') }}</td>
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
