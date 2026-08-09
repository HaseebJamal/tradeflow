@extends('layouts.dashboard')
@section('page-title', 'Sales Quotations')
@section('page-subtitle', 'Prepare and track customer quotations before inventory is committed')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div><h2 class="h5 mb-1">Quotation register</h2><p class="tf-muted mb-0">Quotations do not change inventory, receivables, or accounting until converted into a sale.</p></div>
    @companyCan('sales.quotations')
        <a href="{{ route('business.sales.quotations.create') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>New quotation</a>
    @endcompanyCan
</div>

<x-table>
    <thead><tr><th>Quotation</th><th>Customer</th><th>Date</th><th>Valid Until</th><th>Status</th><th>Total</th><th>Actions</th></tr></thead>
    <tbody>
        @forelse($quotations as $quotation)
            <tr>
                <td><strong>{{ $quotation->quotation_number }}</strong></td>
                <td>{{ $quotation->customer?->business_name ?? $quotation->customer?->name ?? 'Walk-in / prospective customer' }}</td>
                <td>{{ $quotation->quotation_date?->format('d M Y') }}</td>
                <td>{{ $quotation->valid_until?->format('d M Y') ?? '—' }}</td>
                <td>{{ $quotation->status }}</td>
                <td>Rs {{ number_format($quotation->grand_total, 0) }}</td>
                <td>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-display="dynamic" aria-expanded="false">Actions</button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('business.sales.quotations.show', $quotation) }}">View</a></li>
                            @if(in_array($quotation->status, ['Draft', 'Sent'], true))
                                <li><a class="dropdown-item" href="{{ route('business.sales.quotations.edit', $quotation) }}">Edit</a></li>
                            @endif
                            @if(in_array($quotation->status, ['Draft', 'Sent', 'Accepted'], true) && ! $quotation->valid_until?->isPast())
                                <li><form method="POST" action="{{ route('business.sales.quotations.convert', $quotation) }}">@csrf<button class="dropdown-item" type="submit">Convert to Sale</button></form></li>
                            @endif
                            @if(in_array($quotation->status, ['Draft', 'Sent'], true))
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="POST" action="{{ route('business.sales.quotations.destroy', $quotation) }}" data-tf-confirm-message="Delete quotation {{ $quotation->quotation_number }}?">@csrf @method('DELETE')<button class="dropdown-item text-danger" type="submit">Delete</button></form></li>
                            @endif
                        </ul>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center tf-muted py-4">No quotations yet.</td></tr>
        @endforelse
    </tbody>
</x-table>
<div class="mt-3"><x-table-result-summary :paginator="$quotations" />{{ $quotations->links('pagination::bootstrap-5') }}</div>
@endsection
