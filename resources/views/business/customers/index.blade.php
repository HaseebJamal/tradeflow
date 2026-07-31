@extends('layouts.dashboard')
@section('page-title', 'Customers')
@section('page-subtitle', 'Customer, dealer, credit, and statement management')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@companyCan('customers.create')<div class="tf-card p-4 mb-4">
    <h2 class="h5">Add Customer</h2>
    <form method="POST" action="{{ route('business.customers.store') }}" class="row g-3">
        @csrf
        <div class="col-md-3"><label class="form-label">Owner Name *</label><input name="name" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Shop Name</label><input name="shop_name" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Email</label><input name="email" type="email" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Type</label><select name="customer_type" class="form-select"><option>Retailer</option><option>Dealer</option><option>Distributor</option><option>Walk-in Customer</option><option>Other</option></select></div>
        <div class="col-md-2"><label class="form-label">City</label><input name="city" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Province</label><input name="province" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Credit Limit</label><input name="credit_limit" type="number" min="0" step="1" class="form-control js-whole-number"><small class="text-muted">Optional - defaults to Rs 0</small></div>
        <div class="col-md-2"><label class="form-label">Opening Balance</label><input name="opening_balance" type="number" min="0" step="1" class="form-control js-whole-number"><small class="text-muted">Optional - defaults to Rs 0</small></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option>Active</option><option>Blocked</option></select></div>
        <div class="col-md-10"><label class="form-label">Address</label><input name="address" class="form-control"></div>
        <div class="col-12"><button class="btn btn-tf-primary">Save Customer</button></div>
    </form>
</div>@endcompanyCan
<form class="tf-card p-4 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">Search</label><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Name, shop, phone, email"></div>
        <div class="col-md-2"><label class="form-label">Type</label><select name="customer_type" class="form-select"><option value="">All</option>@foreach(['Retailer','Dealer','Distributor','Walk-in Customer','Other','Wholesaler'] as $type)<option @selected(request('customer_type')===$type)>{{ $type }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">City</label><input name="city" value="{{ request('city') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option><option @selected(request('status') === 'Active')>Active</option><option @selected(request('status') === 'Blocked')>Blocked</option><option @selected(request('status') === 'Inactive')>Inactive</option><option value="Archived" @selected(request('status') === 'Archived' || request('archived'))>Archived</option></select></div>
        <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
    </div>
</form>
<x-table class="tf-business-data-table">
    <thead><tr><th>Customer</th><th>Shop</th><th>Phone</th><th>Email</th><th>Type</th><th>City</th><th>Credit Balance</th><th>Credit Limit</th><th>Status</th><th>Created By</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($customers ?? [] as $customer)
        <tr>
            <td><strong>{{ $customer->display_name }}</strong></td>
            <td>{{ $customer->business_name ?: '-' }}</td>
            <td>{{ $customer->phone }}</td>
            <td>{{ $customer->email ?: '-' }}</td>
            <td>{{ $customer->customer_type }}</td>
            <td>{{ $customer->city }}</td>
            <td>Rs {{ number_format($customer->current_balance) }}</td>
            <td>Rs {{ number_format($customer->credit_limit) }}</td>
            <td><span class="tf-badge {{ $customer->status === 'Active' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $customer->deleted_at ? 'Archived' : $customer->status }}</span></td>
            <td>{{ $customer->creator?->name ?? '-' }}</td>
            <td class="text-end text-nowrap">
                @if($customer->trashed() && app(\App\Services\CompanyPermissionService::class)->allowsUser(auth()->user(), 'customers.restore'))
                    <form method="POST" action="{{ route('business.customers.restore', $customer->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Restore</button></form>
                @else
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('business.customers.show', $customer) }}">View / Edit</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('business.customers.statement', $customer) }}">Statement</a>
                    @companyCan('customers.edit')
                        @if($customer->status !== 'Active')<form class="d-inline" method="POST" action="{{ route('business.customers.status', $customer) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="Active"><button class="btn btn-sm btn-outline-success">Activate</button></form>@endif
                        @if($customer->status !== 'Inactive')<form class="d-inline" method="POST" action="{{ route('business.customers.status', $customer) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="Inactive"><button class="btn btn-sm btn-outline-secondary">Deactivate</button></form>@endif
                    @endcompanyCan
                    @companyCan('customers.archive')<form class="d-inline" method="POST" action="{{ route('business.customers.archive', $customer) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-warning">Archive</button></form>@endcompanyCan
                    @companyCan('customers.archive')<form class="d-inline" method="POST" action="{{ route('business.customers.destroy', $customer) }}" onsubmit="return confirm('Delete this customer when safe? Customers with history are archived.')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endcompanyCan
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="11" class="text-center tf-muted py-4">No customers yet.</td></tr>
    @endforelse
    </tbody>
</x-table>
@if(isset($customers) && method_exists($customers, 'links'))<div class="mt-3"><x-table-result-summary :paginator="$customers" />{{ $customers->links('pagination::bootstrap-5') }}</div>@endif
@endsection
