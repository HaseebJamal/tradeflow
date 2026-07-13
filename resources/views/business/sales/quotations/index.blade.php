@extends('layouts.dashboard')
@section('page-title', 'Sales Quotations')
@section('page-subtitle', 'Prepare and track customer quotations before inventory is committed')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><div><h2 class="h5 mb-1">Quotation register</h2><p class="tf-muted mb-0">Quotations do not change inventory, receivables, or accounting until converted into a sale.</p></div>@companyCan('sales.quotations')<a href="{{ route('business.sales.quotations.create') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>New quotation</a>@endcompanyCan</div>
<x-table><thead><tr><th>Quotation</th><th>Customer</th><th>Date</th><th>Valid Until</th><th>Status</th><th>Total</th></tr></thead><tbody>@forelse($quotations as $quotation)<tr><td>{{ $quotation->quotation_number }}</td><td>{{ $quotation->customer?->business_name ?? $quotation->customer?->name ?? 'Walk-in / prospective customer' }}</td><td>{{ $quotation->quotation_date?->format('d M Y') }}</td><td>{{ $quotation->valid_until?->format('d M Y') ?? '—' }}</td><td>{{ $quotation->status }}</td><td>Rs {{ number_format($quotation->grand_total, 2) }}</td></tr>@empty<tr><td colspan="6" class="text-center tf-muted py-4">No quotations yet.</td></tr>@endforelse</tbody></x-table>
<div class="mt-3">{{ $quotations->links() }}</div>
@endsection
