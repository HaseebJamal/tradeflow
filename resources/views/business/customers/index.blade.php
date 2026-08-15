@extends('layouts.dashboard')

@section('page-title', 'Customers')
@section('page-subtitle', 'Customer, dealer, credit, and statement management')

@section('content')
@php($hasFilters = request()->filled('search') || request()->filled('customer_type') || request()->filled('city') || request()->filled('status'))

@if(session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

@companyCan('customers.create')
<section class="tf-card tf-customers-form-card p-4 mb-4" aria-labelledby="add-customer-title">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div><h2 id="add-customer-title" class="h5 mb-1">Add Customer</h2><p class="tf-muted small mb-0">Create a customer record and set its credit details.</p></div>
    </div>
    <form method="POST" action="{{ route('business.customers.store') }}" class="row g-3" data-tf-confirm-message="Create this customer record?" data-tf-confirm-title="Save customer?" data-tf-confirm-button="Save Customer" data-tf-confirm-color="#2563eb" data-tf-confirm-saving-text="Saving customer...">
        @csrf
        <div class="col-12 col-md-6 col-xl-3"><label class="form-label" for="customer-name">Owner Name <span class="text-danger">*</span></label><input id="customer-name" name="name" value="{{ old('name') }}" class="form-control" required></div>
        <div class="col-12 col-md-6 col-xl-3"><label class="form-label" for="customer-shop">Shop Name</label><input id="customer-shop" name="shop_name" value="{{ old('shop_name') }}" class="form-control"></div>
        <div class="col-12 col-md-6 col-xl-3"><label class="form-label" for="customer-phone">Phone</label><x-phone-input id="customer-phone" name="phone" :value="old('phone')" :error="$errors->first('phone')" /></div>
        <div class="col-12 col-md-6 col-xl-3"><label class="form-label" for="customer-email">Email</label><input id="customer-email" name="email" type="email" value="{{ old('email') }}" class="form-control"></div>
        <div class="col-12 col-md-6 col-xl-3"><label class="form-label" for="customer-type">Type <span class="text-danger">*</span></label><select id="customer-type" name="customer_type" class="form-select" required>@foreach(['Retailer','Dealer','Distributor','Walk-in Customer','Other','Wholesaler'] as $type)<option value="{{ $type }}" @selected(old('customer_type', 'Retailer') === $type)>{{ $type }}</option>@endforeach</select></div>
        <div class="col-12 col-md-6 col-xl-3"><label class="form-label" for="customer-city">City</label><input id="customer-city" name="city" value="{{ old('city') }}" class="form-control"></div>
        <div class="col-12 col-md-6 col-xl-3"><label class="form-label" for="customer-province">Province</label><input id="customer-province" name="province" value="{{ old('province') }}" class="form-control"></div>
        <div class="col-12 col-md-6 col-xl-3"><label class="form-label" for="customer-status">Status</label><select id="customer-status" name="status" class="form-select"><option value="Active" @selected(old('status', 'Active') === 'Active')>Active</option><option value="Blocked" @selected(old('status') === 'Blocked')>Blocked</option></select></div>
        <div class="col-12 col-md-6 col-xl-3"><label class="form-label" for="customer-credit-limit">Credit Limit</label><div class="input-group"><span class="input-group-text">Rs</span><input id="customer-credit-limit" name="credit_limit" type="number" min="0" step="1" value="{{ old('credit_limit', 0) }}" class="form-control js-whole-number"></div><small class="tf-muted">Defaults to Rs 0.</small></div>
        <div class="col-12 col-md-6 col-xl-3"><label class="form-label" for="customer-opening-balance">Opening Balance</label><div class="input-group"><span class="input-group-text">Rs</span><input id="customer-opening-balance" name="opening_balance" type="number" min="0" step="1" value="{{ old('opening_balance', 0) }}" class="form-control js-whole-number"></div><small class="tf-muted">Defaults to Rs 0.</small></div>
        <div class="col-12 col-xl-6"><label class="form-label" for="customer-address">Address</label><input id="customer-address" name="address" value="{{ old('address') }}" class="form-control"></div>
        <div class="col-12 d-flex justify-content-end"><button class="btn btn-tf-primary px-4">Save Customer</button></div>
    </form>
</section>
@endcompanyCan

<section class="tf-card tf-customers-filter-card p-3 mb-3" aria-labelledby="customer-filter-title">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 id="customer-filter-title" class="h6 mb-1">Search and filter</h2><p class="tf-muted small mb-0">Find customers by their contact, type, location, or status.</p></div></div>
    <form method="GET" action="{{ route('business.customers.index') }}" class="row g-2 align-items-end">
        <div class="col-12 col-md-6 col-xl-4"><label class="form-label" for="customer-search">Search</label><input id="customer-search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Name, shop, phone, or email"></div>
        <div class="col-12 col-md-4 col-xl-2"><label class="form-label" for="customer-filter-type">Type</label><select id="customer-filter-type" name="customer_type" class="form-select"><option value="">All types</option>@foreach(['Retailer','Dealer','Distributor','Walk-in Customer','Other','Wholesaler'] as $type)<option value="{{ $type }}" @selected(request('customer_type') === $type)>{{ $type }}</option>@endforeach</select></div>
        <div class="col-12 col-md-4 col-xl-2"><label class="form-label" for="customer-filter-city">City</label><input id="customer-filter-city" name="city" value="{{ request('city') }}" class="form-control" placeholder="Any city"></div>
        <div class="col-12 col-md-4 col-xl-2"><label class="form-label" for="customer-filter-status">Status</label><select id="customer-filter-status" name="status" class="form-select"><option value="">All statuses</option>@foreach(['Active', 'Inactive', 'Blocked', 'Archived'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div class="col-12 col-xl-2 d-flex gap-2"><button class="btn btn-tf-primary flex-grow-1">Filter</button><a class="btn btn-outline-secondary" href="{{ route('business.customers.index') }}">Clear </a></div>
    </form>
</section>

<section class="tf-card tf-customers-table-card p-0" aria-labelledby="customers-table-title">
    <div class="tf-customers-table-heading"><div><h2 id="customers-table-title" class="h6 mb-1">Customers</h2><p class="tf-muted small mb-0">Customer records and current credit position.</p></div></div>
    <x-table class="tf-business-data-table tf-customers-table">
        <thead><tr><th>Customer</th><th>Type</th><th>City</th><th>Credit Balance</th><th>Credit Limit</th><th>Status</th><th>Created By</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        @forelse($customers as $customer)
            @php($hasOutstandingBalance = (float) $customer->current_balance > 0)
            <tr>
                <td>
                    <strong>{{ $customer->display_name }}</strong>
                    @if($customer->business_name)<small class="d-block tf-muted">{{ $customer->business_name }}</small>@endif
                    @if($customer->phone || $customer->email)<small class="d-block tf-muted text-truncate tf-customer-contact">{{ collect([$customer->phone, $customer->email])->filter()->implode(' · ') }}</small>@endif
                </td>
                <td>{{ $customer->customer_type }}</td>
                <td>{{ $customer->city ?: '—' }}</td>
                <td><span class="tf-customer-balance {{ $hasOutstandingBalance ? 'is-outstanding' : '' }}">Rs {{ number_format($customer->current_balance) }}</span>@if($hasOutstandingBalance)<small class="d-block text-warning-emphasis">Outstanding</small>@endif</td>
                <td>Rs {{ number_format($customer->credit_limit) }}</td>
                <td>
                    @if($customer->trashed())
                        <span class="tf-badge tf-badge-warning">Archived</span>
                    @elseif(in_array($customer->status, ['Active', 'Inactive'], true))
                        @companyCan('customers.edit')<x-inline-status-switch :status="$customer->status" :action="route('business.customers.status', $customer)" entity="customer {{ $customer->display_name }}" />@else<span class="tf-badge {{ $customer->status === 'Active' ? 'tf-badge-success' : 'tf-badge-secondary' }}">{{ $customer->status }}</span>@endcompanyCan
                    @else
                        <span class="tf-badge tf-badge-warning">{{ $customer->status }}</span>
                    @endif
                </td>
                <td>{{ $customer->creator?->name ?? '—' }}<small class="d-block tf-muted">{{ $customer->created_at?->format('n/j/Y') }}</small></td>
                <td class="text-end text-nowrap">
                    @if($customer->trashed())
                        @companyCan('customers.restore')<form class="d-inline" method="POST" action="{{ route('business.customers.restore', $customer->id) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-success">Restore</button></form>@endcompanyCan
                    @else
                        <button type="button" class="btn btn-sm btn-outline-primary tf-table-view-action" data-bs-toggle="modal" data-bs-target="#customerDetailsModal{{ $customer->id }}">View</button>
                        <div class="dropdown d-inline-block ms-1">
                            <button class="btn btn-sm btn-outline-secondary tf-table-more-action" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-label="Customer actions for {{ $customer->display_name }}"><i class="bi bi-three-dots"></i></button>
                            <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                @companyCan('customers.edit')<a class="dropdown-item" href="{{ route('business.customers.show', $customer) }}#update-customer"><i class="bi bi-pencil me-2"></i>Edit Customer</a>@endcompanyCan
                                <a class="dropdown-item" href="{{ route('business.customers.statement', $customer) }}"><i class="bi bi-receipt me-2"></i>Download Excel</a>
                                <div class="dropdown-divider"></div>
                                @companyCan('customers.archive')<form method="POST" action="{{ route('business.customers.archive', $customer) }}" data-tf-confirm-message="Archive {{ $customer->display_name }}? Its history will be retained." data-tf-confirm-title="Archive customer" data-tf-confirm-button="Archive Customer" data-tf-confirm-color="#f59e0b">@csrf @method('PATCH')<button class="dropdown-item text-warning"><i class="bi bi-archive me-2"></i>Archive</button></form>@endcompanyCan
                                @companyCan('customers.archive')<form method="POST" action="{{ route('business.customers.destroy', $customer) }}" data-tf-confirm-message="Delete {{ $customer->display_name }}? Customers with history will be archived instead." data-tf-confirm-title="Delete customer" data-tf-confirm-button="Delete Customer" data-tf-confirm-color="#ef4444">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form>@endcompanyCan
                            </div>
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center tf-muted py-5">{{ $hasFilters ? 'No customers match your filters.' : 'No customers found.' }}@if($hasFilters)<div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('business.customers.index') }}">Clear Filters</a></div>@endif</td></tr>
        @endforelse
        </tbody>
    </x-table>
    @foreach($customers as $customer)
        <x-record-details-modal :id="'customerDetailsModal'.$customer->id" :title="$customer->display_name" :status="$customer->trashed() ? 'Archived' : $customer->status" :open-url="route('business.customers.show', $customer)" open-label="Open customer profile">
            <div class="tf-record-details-grid">
                <div><span>Customer type</span><strong>{{ $customer->customer_type }}</strong></div><div><span>Current balance</span><strong>Rs {{ number_format($customer->current_balance) }}</strong></div>
                <div><span>Credit limit</span><strong>Rs {{ number_format($customer->credit_limit) }}</strong></div><div><span>City</span><strong>{{ $customer->city ?: 'Not provided' }}</strong></div>
                <div><span>Phone</span><strong>{{ $customer->phone ?: 'Not provided' }}</strong></div><div><span>Email</span><strong>{{ $customer->email ?: 'Not provided' }}</strong></div>
                <div class="tf-record-details-wide"><span>Address</span><strong>{{ $customer->address ?: 'Not provided' }}</strong></div>
            </div>
        </x-record-details-modal>
    @endforeach
    <div class="tf-customers-pagination px-3 py-3">@if($customers->count())<x-table-result-summary :paginator="$customers" />@endif{{ $customers->links('pagination::bootstrap-5') }}</div>
</section>
@endsection
