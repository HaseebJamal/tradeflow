@extends('layouts.dashboard')
@section('page-title', 'Suppliers')
@section('page-subtitle', 'Supplier records, filters, and payable tracking')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())
    <div class="alert alert-danger" @if($errors->first('supplier_name') === 'A supplier with the same phone or complete identity already exists for this business.') data-tf-alert-title="Supplier already exists" @endif>{{ $errors->first() }}</div>
@endif

@companyCan('suppliers.create')<div class="tf-card tf-suppliers-form-card p-4 mb-4">
    <h2 class="h5 mb-3">Add Supplier</h2>
    <form method="POST" action="{{ route('business.suppliers.store') }}" class="row g-3 tf-supplier-create-form">
        @csrf
        <div class="col-12 col-md-6"><input name="supplier_name" value="{{ old('supplier_name') }}" class="form-control" placeholder="Supplier name" required></div>
        <div class="col-12 col-md-6"><input name="company_name" value="{{ old('company_name') }}" class="form-control" placeholder="Company name"></div>
        <div class="col-12 col-md-6"><x-phone-input name="phone" :value="old('phone')" :error="$errors->first('phone')" /></div>
        <div class="col-12 col-md-6"><input name="city" value="{{ old('city') }}" class="form-control" placeholder="City"></div>
        <div class="col-12 col-md-6"><input name="email" type="email" value="{{ old('email') }}" class="form-control" placeholder="Email"></div>
        <div class="col-12 col-md-6"><input name="opening_balance" type="number" step="1" min="0" value="{{ old('opening_balance') }}" class="form-control js-whole-number" placeholder="Opening balance"><small class="text-muted">Optional - defaults to Rs 0</small></div>
        <div class="col-12 col-md-6"><select name="status" class="form-select"><option @selected(old('status', 'Active') === 'Active')>Active</option><option @selected(old('status') === 'Inactive')>Inactive</option></select></div>
        <div class="col-12 col-md-6"><input name="address" value="{{ old('address') }}" class="form-control" placeholder="Address"></div>
        <div class="col-12 tf-supplier-create-actions"><button class="btn btn-tf-primary" type="submit">Save Supplier</button></div>
    </form>
</div>@endcompanyCan

<div class="tf-card tf-suppliers-filter-card p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-2"><label class="form-label">Name</label><input name="name" value="{{ request('name') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Company</label><input name="company" value="{{ request('company') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Phone</label><input name="phone" value="{{ request('phone') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">City</label><input name="city" value="{{ request('city') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option @selected(request('status') === 'Active')>Active</option><option @selected(request('status') === 'Inactive')>Inactive</option><option value="Archived" @selected(request('status') === 'Archived')>Archived</option></select></div>
        <div class="col-md-2"><label class="form-label">Created By</label><select name="created_by" class="form-select"><option value="">All</option>@foreach($creators as $creator)<option value="{{ $creator->id }}" @selected(request('created_by') == $creator->id)>{{ $creator->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ $dateTo }}" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
        <div class="col-md-2"><a href="{{ route('business.suppliers.index', ['clear' => 1]) }}" class="btn btn-outline-secondary w-100">Clear Filters</a></div>
    </form>
</div>

<x-table class="tf-business-data-table tf-suppliers-data-table">
    <thead><tr><th>Supplier</th><th>Contact</th><th>City</th><th>Balance</th><th>Status</th><th>Created By</th><th>Actions</th></tr></thead>
    <tbody>
        @forelse($suppliers as $supplier)
            <tr>
                <td><strong>{{ $supplier->supplier_name }}</strong><small class="d-block tf-muted">{{ $supplier->company_name ?: 'Independent supplier' }}</small></td>
                <td>{{ $supplier->phone ?: '-' }}<small class="d-block tf-muted">{{ $supplier->email ?: 'No email' }}</small></td>
                <td>{{ $supplier->city ?: '-' }}</td>
                <td>Rs {{ number_format($supplier->opening_balance) }}</td>
                <td><span class="badge {{ $supplier->trashed() ? 'bg-warning text-dark' : ($supplier->status === 'Active' ? 'bg-success' : 'bg-secondary') }}">{{ $supplier->trashed() ? 'Archived' : $supplier->status }}</span></td>
                <td>{{ $supplier->creator?->name ?? '-' }}</td>
                <td class="text-end text-nowrap">
                    <div class="d-flex justify-content-end align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary tf-table-view-action" data-bs-toggle="modal" data-bs-target="#supplierDetailsModal{{ $supplier->id }}">View</button>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary tf-table-more-action" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-display="dynamic" aria-expanded="false" aria-label="More actions for {{ $supplier->supplier_name }}"><i class="bi bi-three-dots"></i></button>
                        <div class="dropdown-menu dropdown-menu-end shadow-sm">
                            @if($supplier->trashed())
                                @companyCan('suppliers.archive')<form method="POST" action="{{ route('business.suppliers.restore', $supplier->id) }}">@csrf @method('PATCH')<button class="dropdown-item text-success">Restore</button></form>@endcompanyCan
                                @companyCan('suppliers.archive')<form method="POST" action="{{ route('business.suppliers.destroy', $supplier->id) }}" data-tf-confirm-message="Delete this archived supplier permanently?" data-tf-confirm-title="Delete supplier?" data-tf-confirm-button="Delete supplier" data-tf-confirm-color="#dc3545">@csrf @method('DELETE')<button class="dropdown-item text-danger">Permanently Delete</button></form>@endcompanyCan
                            @else
                                @companyCan('suppliers.edit')<a href="{{ route('business.suppliers.edit', $supplier) }}" class="dropdown-item">Edit</a>@endcompanyCan
                                @companyCan('suppliers.archive')<form method="POST" action="{{ route('business.suppliers.archive', $supplier) }}">@csrf @method('PATCH')<button class="dropdown-item text-warning">Archive</button></form>@endcompanyCan
                                @companyCan('suppliers.archive')<form method="POST" action="{{ route('business.suppliers.destroy', $supplier) }}" data-tf-confirm-message="Delete this unused supplier permanently?" data-tf-confirm-title="Delete supplier?" data-tf-confirm-button="Delete supplier" data-tf-confirm-color="#dc3545">@csrf @method('DELETE')<button class="dropdown-item text-danger">Delete</button></form>@endcompanyCan
                            @endif
                        </div>
                    </div>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center tf-muted py-4">No suppliers found.</td></tr>
        @endforelse
    </tbody>
</x-table>
@foreach($suppliers as $supplier)
    <x-record-details-modal :id="'supplierDetailsModal'.$supplier->id" :title="$supplier->supplier_name" :status="$supplier->trashed() ? 'Archived' : $supplier->status" :open-url="route('business.suppliers.show', $supplier)" open-label="Open supplier profile">
        <div class="tf-record-details-grid">
            <div><span>Company</span><strong>{{ $supplier->company_name ?: 'Independent supplier' }}</strong></div>
            <div><span>Opening balance</span><strong>Rs {{ number_format($supplier->opening_balance) }}</strong></div>
            <div><span>Phone</span><strong>{{ $supplier->phone ?: 'Not provided' }}</strong></div>
            <div><span>Email</span><strong>{{ $supplier->email ?: 'Not provided' }}</strong></div>
            <div><span>City</span><strong>{{ $supplier->city ?: 'Not provided' }}</strong></div>
            <div><span>Created by</span><strong>{{ $supplier->creator?->name ?? '-' }}</strong></div>
            <div class="tf-record-details-wide"><span>Address</span><strong>{{ $supplier->address ?: 'Not provided' }}</strong></div>
        </div>
    </x-record-details-modal>
@endforeach
<div class="mt-3"><x-table-result-summary :paginator="$suppliers" />{{ $suppliers->links('pagination::bootstrap-5') }}</div>
@endsection
