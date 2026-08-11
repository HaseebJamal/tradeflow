@extends('layouts.dashboard')

@section('page-title', 'Customer Profile')
@section('page-subtitle', $customer->display_name)

@section('content')
@if(session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

<div class="tf-customer-profile-layout">
    <main class="tf-customer-profile-main">
        <section class="tf-card tf-customer-overview-card" aria-labelledby="customer-overview-title">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                <div><span class="tf-dashboard-eyebrow">Customer profile</span><h2 id="customer-overview-title" class="h4 mb-1">{{ $customer->display_name }}</h2><p class="tf-muted mb-0">{{ $customer->business_name ?: 'No shop name recorded' }}</p></div>
                @if(in_array($customer->status, ['Active', 'Inactive'], true))
                    @companyCan('customers.edit')<x-inline-status-switch :status="$customer->status" :action="route('business.customers.status', $customer)" entity="customer {{ $customer->display_name }}" />@else<span class="tf-badge {{ $customer->status === 'Active' ? 'tf-badge-success' : 'tf-badge-secondary' }}">{{ $customer->status }}</span>@endcompanyCan
                @else
                    <span class="tf-badge tf-badge-warning">{{ $customer->status }}</span>
                @endif
            </div>
            <dl class="tf-customer-info-grid mb-0">
                <div><dt>Phone</dt><dd>{{ $customer->phone ?: '—' }}</dd></div>
                <div><dt>Email</dt><dd class="text-break">{{ $customer->email ?: '—' }}</dd></div>
                <div><dt>Type</dt><dd>{{ $customer->customer_type ?: '—' }}</dd></div>
                <div><dt>City / Location</dt><dd>{{ collect([$customer->city, $customer->province])->filter()->implode(', ') ?: '—' }}</dd></div>
                <div class="tf-customer-info-wide"><dt>Address</dt><dd>{{ $customer->address ?: '—' }}</dd></div>
                <div><dt>Created by</dt><dd>{{ $customer->creator?->name ?: '—' }}</dd></div>
                <div><dt>Created at</dt><dd>{{ $customer->created_at?->format('n/j/Y, g:i A') ?: '—' }}</dd></div>
            </dl>
        </section>

        <section class="mb-4" aria-labelledby="customer-financial-title">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><span class="tf-dashboard-eyebrow">Financial summary</span><h2 id="customer-financial-title" class="h5 mb-0">Account position</h2></div></div>
            <div class="row g-3">
                @foreach([
                    ['Outstanding Receivable', 'Rs '.number_format($outstanding), $outstanding > 0 ? 'warning' : 'success'],
                    ['Credit Limit', 'Rs '.number_format($customer->credit_limit), 'blue'],
                    ['Available Credit', 'Rs '.number_format(max(0, $customer->credit_limit - $outstanding)), 'success'],
                    ['Total Sales', 'Rs '.number_format($totalSales), 'blue'],
                    ['Payments', 'Rs '.number_format($paymentsReceived), 'success'],
                    ['Orders', number_format($customer->orders->count()), 'slate'],
                    ['Last Order', $lastOrder?->created_at?->format('n/j/Y') ?? '—', 'slate'],
                    ['Last Payment', $lastPayment?->payment_date?->format('n/j/Y') ?? '—', 'slate'],
                ] as [$label, $value, $tone])
                    <div class="col-6 col-xl-3"><article class="tf-customer-kpi is-{{ $tone }}"><small>{{ $label }}</small><strong>{{ $value }}</strong></article></div>
                @endforeach
            </div>
        </section>

        <section class="tf-card tf-customer-ledger-card" aria-labelledby="customer-ledger-title">
            <div class="tf-customer-ledger-heading"><div><span class="tf-dashboard-eyebrow">Ledger</span><h2 id="customer-ledger-title" class="h5 mb-1">Customer Ledger</h2><p class="tf-muted small mb-0">Posted account activity for this customer.</p></div></div>
            <x-table class="tf-customer-ledger-table">
                <thead><tr><th>Date</th><th>Voucher / Order</th><th>Account</th><th>Description</th><th class="text-end">Debit</th><th class="text-end">Credit</th></tr></thead>
                <tbody>
                @forelse($journalLines as $line)
                    <tr><td>{{ $line->journalEntry?->entry_date?->format('n/j/Y') ?: '—' }}</td><td>{{ $line->journalEntry?->voucher_number ?: '—' }}</td><td>{{ $line->account?->name ?: '—' }}</td><td>{{ $line->description ?: '—' }}</td><td class="text-end text-nowrap">Rs {{ number_format($line->debit) }}</td><td class="text-end text-nowrap">Rs {{ number_format($line->credit) }}</td></tr>
                @empty
                    @forelse($customer->ledgers as $ledger)
                        <tr><td>{{ $ledger->created_at?->format('n/j/Y') ?: '—' }}</td><td>{{ $ledger->order?->order_number ?? '—' }}</td><td>Legacy Khata</td><td>{{ $ledger->description ?: '—' }}</td><td class="text-end text-nowrap">Rs {{ number_format($ledger->amount) }}</td><td class="text-end">—</td></tr>
                    @empty
                        <tr><td colspan="6" class="text-center tf-muted py-4">No ledger entries found.</td></tr>
                    @endforelse
                @endforelse
                </tbody>
            </x-table>
            @if(method_exists($journalLines, 'links') && $journalLines->hasPages())<div class="tf-customer-ledger-pagination px-3 py-3">{{ $journalLines->withQueryString()->links('pagination::bootstrap-5') }}</div>@endif
        </section>

        @companyCan('customers.edit')
        <section class="tf-card tf-customer-update-card" id="update-customer" aria-labelledby="update-customer-title">
            <div class="tf-customer-section-heading"><span class="tf-dashboard-eyebrow">Customer details</span><h2 id="update-customer-title" class="h5 mb-1">Update Customer</h2><p class="tf-muted small mb-0">Update contact and credit details. Status is managed from the profile switch above.</p></div>
            <form method="POST" action="{{ route('business.customers.update', $customer) }}" class="row g-3">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ $customer->status }}">
                <div class="col-12 col-md-6"><label class="form-label" for="update-customer-name">Owner Name <span class="text-danger">*</span></label><input id="update-customer-name" name="name" value="{{ old('name', $customer->name) }}" class="form-control" required></div>
                <div class="col-12 col-md-6"><label class="form-label" for="update-customer-shop">Shop Name</label><input id="update-customer-shop" name="shop_name" value="{{ old('shop_name', $customer->business_name) }}" class="form-control"></div>
                <div class="col-12 col-md-6"><label class="form-label" for="update-customer-phone">Phone</label><x-phone-input id="update-customer-phone" name="phone" :value="old('phone', $customer->phone)" :error="$errors->first('phone')" /></div>
                <div class="col-12 col-md-6"><label class="form-label" for="update-customer-email">Email</label><input id="update-customer-email" name="email" type="email" value="{{ old('email', $customer->email) }}" class="form-control"></div>
                <div class="col-12 col-md-6"><label class="form-label" for="update-customer-city">City</label><input id="update-customer-city" name="city" value="{{ old('city', $customer->city) }}" class="form-control"></div>
                <div class="col-12 col-md-6"><label class="form-label" for="update-customer-province">Province / Location</label><input id="update-customer-province" name="province" value="{{ old('province', $customer->province) }}" class="form-control"></div>
                <div class="col-12 col-md-4"><label class="form-label" for="update-customer-type">Type</label><select id="update-customer-type" name="customer_type" class="form-select">@foreach(['Retailer','Dealer','Distributor','Walk-in Customer','Other','Wholesaler'] as $type)<option value="{{ $type }}" @selected(old('customer_type', $customer->customer_type) === $type)>{{ $type }}</option>@endforeach</select></div>
                <div class="col-12 col-md-4"><label class="form-label" for="update-customer-credit-limit">Credit Limit</label><div class="input-group"><span class="input-group-text">Rs</span><input id="update-customer-credit-limit" name="credit_limit" type="number" min="0" step="1" value="{{ old('credit_limit', (int) $customer->credit_limit) }}" class="form-control js-whole-number"></div><small class="tf-muted">Defaults to Rs 0.</small></div>
                <div class="col-12 col-md-4"><label class="form-label" for="update-customer-balance">Current Balance</label><div class="input-group"><span class="input-group-text">Rs</span><input id="update-customer-balance" name="current_balance" type="number" min="0" step="1" value="{{ old('current_balance', (int) $customer->current_balance) }}" class="form-control js-whole-number"></div></div>
                <div class="col-12"><label class="form-label" for="update-customer-address">Address</label><input id="update-customer-address" name="address" value="{{ old('address', $customer->address) }}" class="form-control"></div>
                <div class="col-12 d-flex justify-content-end"><button class="btn btn-tf-primary px-4">Save Changes</button></div>
            </form>
        </section>
        @endcompanyCan
    </main>

    <aside class="tf-customer-profile-aside">
        <section class="tf-card tf-customer-side-card" aria-labelledby="customer-controls-title">
            <span class="tf-dashboard-eyebrow">Quick controls</span><h2 id="customer-controls-title" class="h5 mb-3">Customer Actions</h2>
            <a href="{{ route('business.customers.statement', $customer) }}" class="btn btn-outline-primary w-100"><i class="bi bi-receipt me-2"></i>Export Statement</a>
            @companyCan('customers.archive')
                <div class="border-top mt-4 pt-4"><small class="text-danger fw-semibold d-block mb-2">Danger zone</small>
                    <div class="dropdown"><button class="btn btn-outline-danger w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport"><i class="bi bi-three-dots me-1"></i>More Actions</button><div class="dropdown-menu dropdown-menu-end shadow-sm w-100">
                        <form method="POST" action="{{ route('business.customers.archive', $customer) }}" data-tf-confirm-message="Archive {{ $customer->display_name }}? Its history will be retained." data-tf-confirm-title="Archive customer" data-tf-confirm-button="Archive Customer" data-tf-confirm-color="#f59e0b">@csrf @method('PATCH')<button class="dropdown-item text-warning"><i class="bi bi-archive me-2"></i>Archive</button></form>
                        <form method="POST" action="{{ route('business.customers.destroy', $customer) }}" data-tf-confirm-message="Delete {{ $customer->display_name }}? Customers with history will be archived instead." data-tf-confirm-title="Delete customer" data-tf-confirm-button="Delete Customer" data-tf-confirm-color="#ef4444">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form>
                    </div></div>
                </div>
            @endcompanyCan
        </section>
    </aside>
</div>
@endsection
