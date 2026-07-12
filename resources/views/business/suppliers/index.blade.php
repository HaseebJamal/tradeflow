@extends('layouts.dashboard')
@section('page-title', 'Suppliers')
@section('page-subtitle', 'Supplier records, filters, and payable tracking')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@companyCan('suppliers.create')<div class="tf-card p-4 mb-4">
    <h2 class="h5 mb-3">Add Supplier</h2>
    <form method="POST" action="{{ route('business.suppliers.store') }}" class="row g-3">
        @csrf
        <div class="col-md-3"><input name="supplier_name" class="form-control" placeholder="Supplier name" required></div>
        <div class="col-md-3"><input name="company_name" class="form-control" placeholder="Company name"></div>
        <div class="col-md-2"><input name="phone" class="form-control" placeholder="Phone"></div>
        <div class="col-md-2"><input name="city" class="form-control" placeholder="City"></div>
        <div class="col-md-2"><input name="email" type="email" class="form-control" placeholder="Email"></div>
        <div class="col-md-3"><input name="opening_balance" type="number" step="0.01" min="0" class="form-control" placeholder="Opening balance"></div>
        <div class="col-md-3"><select name="status" class="form-select"><option>Active</option><option>Inactive</option></select></div>
        <div class="col-md-4"><input name="address" class="form-control" placeholder="Address"></div>
        <div class="col-md-2"><button class="btn btn-tf-primary w-100">Save Supplier</button></div>
    </form>
</div>@endcompanyCan

<div class="tf-card p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-2"><label class="form-label">Name</label><input name="name" value="{{ request('name') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Company</label><input name="company" value="{{ request('company') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Phone</label><input name="phone" value="{{ request('phone') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">City</label><input name="city" value="{{ request('city') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option @selected(request('status') === 'Active')>Active</option><option @selected(request('status') === 'Inactive')>Inactive</option></select></div>
        <div class="col-md-2"><label class="form-label">Created By</label><select name="created_by" class="form-select"><option value="">All</option>@foreach($creators as $creator)<option value="{{ $creator->id }}" @selected(request('created_by') == $creator->id)>{{ $creator->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ request('date_from', $dateFrom) }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ request('date_to', $dateTo) }}" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
        <div class="col-md-2"><a href="{{ route('business.suppliers.index', ['clear' => 1]) }}" class="btn btn-outline-secondary w-100">Clear Filters</a></div>
    </form>
</div>

<x-table>
    <thead><tr><th>Supplier</th><th>Company</th><th>Phone</th><th>City</th><th>Opening Balance</th><th>Status</th><th>Created By</th><th>Created At</th><th>Updated At</th><th>Actions</th></tr></thead>
    <tbody>
        @forelse($suppliers as $supplier)
            <tr>
                <td>{{ $supplier->supplier_name }}</td>
                <td>{{ $supplier->company_name ?: '-' }}</td>
                <td>{{ $supplier->phone ?: '-' }}</td>
                <td>{{ $supplier->city ?: '-' }}</td>
                <td>Rs {{ number_format($supplier->opening_balance) }}</td>
                <td><span class="badge {{ $supplier->status === 'Active' ? 'bg-success' : 'bg-secondary' }}">{{ $supplier->status }}</span></td>
                <td>{{ $supplier->creator?->name ?? '-' }}</td>
                <td><x-date-time :value="$supplier->created_at" /></td>
                <td><x-date-time :value="$supplier->updated_at" /></td>
                <td class="d-flex gap-2">
                    <a href="{{ route('business.suppliers.show', $supplier) }}" class="btn btn-sm btn-outline-primary">View</a>
                    @companyCan('suppliers.edit')<a href="{{ route('business.suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-secondary">Edit</a>@endcompanyCan
                    @companyCan('suppliers.archive')<form method="POST" action="{{ route('business.suppliers.destroy', $supplier) }}" onsubmit="return confirm('Delete or deactivate this supplier?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endcompanyCan
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="text-center tf-muted py-4">No suppliers found.</td></tr>
        @endforelse
    </tbody>
</x-table>
<div class="mt-3">{{ $suppliers->links() }}</div>
@endsection
