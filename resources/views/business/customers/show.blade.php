@extends('layouts.dashboard')
@section('page-title', 'Customer Profile')
@section('page-subtitle', $customer->display_name)
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="row g-4">
    <div class="col-lg-4">
        <div class="tf-card p-4">
            <h2 class="h4">{{ $customer->display_name }}</h2>
            <p class="tf-muted">{{ $customer->customer_type }}, {{ $customer->city }} {{ $customer->province ? ', '.$customer->province : '' }}</p>
            <div class="h3 text-danger">Rs {{ number_format($outstanding) }}</div><small class="tf-muted">Outstanding receivable</small>
            <div class="row g-2 mt-3">
                <div class="col-6"><div class="border rounded p-2"><small class="tf-muted">Orders</small><strong class="d-block">{{ $customer->orders->count() }}</strong></div></div>
                <div class="col-6"><div class="border rounded p-2"><small class="tf-muted">Total Sales</small><strong class="d-block">Rs {{ number_format($totalSales) }}</strong></div></div>
                <div class="col-6"><div class="border rounded p-2"><small class="tf-muted">Payments</small><strong class="d-block">Rs {{ number_format($paymentsReceived) }}</strong></div></div>
                <div class="col-6"><div class="border rounded p-2"><small class="tf-muted">Available Credit</small><strong class="d-block">Rs {{ number_format(max(0, $customer->credit_limit - $outstanding)) }}</strong></div></div>
                <div class="col-6"><div class="border rounded p-2"><small class="tf-muted">Last Order</small><strong class="d-block">{{ $lastOrder?->created_at?->format('M d') ?? '-' }}</strong></div></div>
                <div class="col-6"><div class="border rounded p-2"><small class="tf-muted">Last Payment</small><strong class="d-block">{{ $lastPayment?->payment_date?->format('M d') ?? '-' }}</strong></div></div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                @companyCan('customers.view')<a href="{{ route('business.customers.statement', $customer) }}" class="btn btn-outline-primary btn-sm">Export Statement</a>@endcompanyCan
                @companyCan('customers.edit')
                    @if($customer->status !== 'Active')<form method="POST" action="{{ route('business.customers.status', $customer) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="Active"><button class="btn btn-outline-success btn-sm">Activate</button></form>@endif
                    @if($customer->status !== 'Inactive')<form method="POST" action="{{ route('business.customers.status', $customer) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="Inactive"><button class="btn btn-outline-secondary btn-sm">Deactivate</button></form>@endif
                @endcompanyCan
                @companyCan('customers.archive')<form method="POST" action="{{ route('business.customers.archive', $customer) }}">@csrf @method('PATCH')<button class="btn btn-outline-warning btn-sm">Archive</button></form>@endcompanyCan
                @companyCan('customers.archive')<form method="POST" action="{{ route('business.customers.destroy', $customer) }}" onsubmit="return confirm('Delete permanently if unused, otherwise archive?')">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Delete</button></form>@endcompanyCan
            </div>
        </div>
        @companyCan('customers.edit')<div class="tf-card p-4 mt-4">
            <h3 class="h5">Update Customer</h3>
            <form method="POST" action="{{ route('business.customers.update', $customer) }}" class="row g-2">
                @csrf @method('PATCH')
                <div class="col-12"><input name="name" value="{{ $customer->name }}" class="form-control" placeholder="Owner name"></div>
                <div class="col-12"><input name="business_name" value="{{ $customer->business_name }}" class="form-control" placeholder="Shop name"></div>
                <div class="col-6"><input name="phone" value="{{ $customer->phone }}" class="form-control" placeholder="Phone"></div>
                <div class="col-6"><input name="email" value="{{ $customer->email }}" class="form-control" placeholder="Email"></div>
                <div class="col-6"><input name="city" value="{{ $customer->city }}" class="form-control" placeholder="City"></div>
                <div class="col-6"><input name="province" value="{{ $customer->province }}" class="form-control" placeholder="Province"></div>
                <div class="col-6"><select name="customer_type" class="form-select">@foreach(['Retailer','Dealer','Distributor','Walk-in Customer','Other','Wholesaler'] as $type)<option @selected($customer->customer_type === $type)>{{ $type }}</option>@endforeach</select></div>
                <div class="col-6"><select name="status" class="form-select"><option @selected($customer->status === 'Active')>Active</option><option @selected($customer->status === 'Blocked')>Blocked</option><option @selected($customer->status === 'Inactive')>Inactive</option></select></div>
                <div class="col-6"><label class="form-label small">Credit Limit</label><input name="credit_limit" type="number" min="0" step="0.01" value="{{ $customer->credit_limit }}" class="form-control" placeholder="Credit limit"></div>
                <div class="col-6"><label class="form-label small">Current Balance</label><input name="current_balance" type="number" min="0" step="0.01" value="{{ $customer->current_balance }}" class="form-control" placeholder="Current balance"></div>
                <div class="col-12"><input name="address" value="{{ $customer->address }}" class="form-control" placeholder="Address"></div>
                <div class="col-12"><button class="btn btn-outline-primary btn-sm">Save Changes</button></div>
            </form>
        </div>@endcompanyCan
    </div>
    <div class="col-lg-8">
        <div class="tf-card p-4 mb-4">
            <h3 class="h5">Customer Ledger</h3>
            <x-table>
                <thead><tr><th>Date</th><th>Voucher / Order</th><th>Account</th><th>Description</th><th>Debit</th><th>Credit</th></tr></thead>
                <tbody>
                @forelse($journalLines as $line)
                    <tr><td>{{ $line->journalEntry?->entry_date?->format('M d, Y') }}</td><td>{{ $line->journalEntry?->voucher_number }}</td><td>{{ $line->account?->name }}</td><td>{{ $line->description }}</td><td>Rs {{ number_format($line->debit) }}</td><td>Rs {{ number_format($line->credit) }}</td></tr>
                @empty
                    @foreach($customer->ledgers as $ledger)<tr><td>{{ $ledger->created_at->format('M d') }}</td><td>{{ $ledger->order?->order_number ?? '-' }}</td><td>Legacy Khata</td><td>{{ $ledger->description }}</td><td>Rs {{ number_format($ledger->amount) }}</td><td>-</td></tr>@endforeach
                    @if($customer->ledgers->isEmpty())<tr><td colspan="6" class="text-center tf-muted py-4">No ledger entries.</td></tr>@endif
                @endforelse
                </tbody>
            </x-table>
        </div>
    </div>
</div>
@endsection
