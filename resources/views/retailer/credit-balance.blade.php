@extends('layouts.dashboard')
@section('page-title', 'Credit Balance')
@section('page-subtitle', 'Retailer khata overview')
@section('content')
<div class="row g-3 mb-4"><div class="col-md-4"><div class="tf-card p-4"><div class="tf-muted">Used Credit</div><div class="h3 fw-bold">Rs {{ number_format($balance ?? 0) }}</div></div></div><div class="col-md-4"><div class="tf-card p-4"><div class="tf-muted">Ledger Entries</div><div class="h3 fw-bold">{{ isset($ledgers) ? $ledgers->total() : 0 }}</div></div></div><div class="col-md-4"><div class="tf-card p-4"><div class="tf-muted">Status</div><div class="h3 fw-bold">Active</div></div></div></div>
<x-table><thead><tr><th>Date</th><th>Supplier</th><th>Description</th><th>Type</th><th>Amount</th><th>Balance</th></tr></thead><tbody>@forelse($ledgers ?? [] as $ledger)<tr><td>{{ $ledger->created_at->format('M d, Y') }}</td><td>{{ $ledger->customer?->business?->business_name }}</td><td>{{ $ledger->description }}</td><td>{{ $ledger->type }}</td><td>Rs {{ number_format($ledger->amount) }}</td><td>Rs {{ number_format($ledger->balance) }}</td></tr>@empty<tr><td colspan="6" class="text-center tf-muted py-4">No credit records.</td></tr>@endforelse</tbody></x-table>
@endsection
