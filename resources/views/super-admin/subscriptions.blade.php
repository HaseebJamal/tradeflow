@extends('layouts.dashboard')
@section('page-title', 'Subscriptions')
@section('page-subtitle', 'Plans and manual business subscription control')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="dashboard-cards mb-4">
    <div class="tf-stat-card"><div class="tf-muted small">Active Subscriptions</div><div class="h3 mb-0">{{ $stats['active'] }}</div></div>
    <div class="tf-stat-card"><div class="tf-muted small">Expired Subscriptions</div><div class="h3 mb-0 text-warning">{{ $stats['expired'] }}</div></div>
    <div class="tf-stat-card"><div class="tf-muted small">Cancelled Subscriptions</div><div class="h3 mb-0 text-danger">{{ $stats['cancelled'] }}</div></div>
    <div class="tf-stat-card"><div class="tf-muted small">Revenue This Month</div><div class="h3 mb-0">Rs {{ number_format($stats['monthly_revenue'], 2) }}</div></div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="tf-card p-4 h-100">
            <h2 class="h5 mb-3">Create Plan</h2>
            <form method="POST" action="{{ route('admin.subscription-plans.store') }}" class="row g-3">
                @csrf
                <div class="col-12"><label class="form-label">Plan Name</label><input name="name" class="form-control" value="{{ old('name') }}" maxlength="100" required></div>
                <div class="col-md-6"><label class="form-label">Price</label><input name="price" type="number" min="0" step="0.01" class="form-control" value="{{ old('price', 0) }}" required></div>
                <div class="col-md-6"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                <div class="col-md-4"><label class="form-label">Products</label><input name="product_limit" type="number" min="0" class="form-control" value="{{ old('product_limit', 0) }}" required></div>
                <div class="col-md-4"><label class="form-label">Staff</label><input name="staff_limit" type="number" min="0" class="form-control" value="{{ old('staff_limit', 0) }}" required></div>
                <div class="col-md-4"><label class="form-label">Orders</label><input name="order_limit" type="number" min="0" class="form-control" value="{{ old('order_limit', 0) }}" required></div>
                <div class="col-12"><button class="btn btn-tf-primary">Create Plan</button></div>
            </form>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="tf-card p-0 h-100">
            <div class="p-4 pb-2"><h2 class="h5 mb-0">Subscription Plans</h2></div>
            <x-table><thead><tr><th>Plan</th><th>Price</th><th>Products</th><th>Staff</th><th>Orders</th><th>Businesses</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
            @forelse($plans as $plan)
                <tr><td><strong>{{ $plan->name }}</strong></td><td>Rs {{ number_format($plan->price, 2) }}</td><td>{{ number_format($plan->product_limit) }}</td><td>{{ number_format($plan->staff_limit) }}</td><td>{{ number_format($plan->order_limit) }}</td><td>{{ $plan->subscriptions_count }}</td><td><span class="tf-badge {{ $plan->status === 'Active' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $plan->status }}</span></td><td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#plan-{{ $plan->id }}">Edit</button><form method="POST" action="{{ route('admin.subscription-plans.destroy', $plan) }}" class="d-inline" onsubmit="return confirm('Delete this plan? Plans with subscription history are deactivated instead.')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
                <tr class="collapse" id="plan-{{ $plan->id }}"><td colspan="8" class="bg-light"><form method="POST" action="{{ route('admin.subscription-plans.update', $plan) }}" class="row g-2 align-items-end">@csrf @method('PATCH')<div class="col-md-3"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ $plan->name }}" required></div><div class="col-md-2"><label class="form-label">Price</label><input name="price" type="number" min="0" step="0.01" class="form-control" value="{{ $plan->price }}" required></div><div class="col-md-1"><label class="form-label">Products</label><input name="product_limit" type="number" min="0" class="form-control" value="{{ $plan->product_limit }}" required></div><div class="col-md-1"><label class="form-label">Staff</label><input name="staff_limit" type="number" min="0" class="form-control" value="{{ $plan->staff_limit }}" required></div><div class="col-md-1"><label class="form-label">Orders</label><input name="order_limit" type="number" min="0" class="form-control" value="{{ $plan->order_limit }}" required></div><div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Active" @selected($plan->status === 'Active')>Active</option><option value="Inactive" @selected($plan->status === 'Inactive')>Inactive</option></select></div><div class="col-md-2 d-grid"><button class="btn btn-tf-primary">Save Changes</button></div></form></td></tr>
            @empty
                <tr><td colspan="8" class="text-center tf-muted py-4">No subscription plans created yet.</td></tr>
            @endforelse
            </tbody></x-table>
        </div>
    </div>
</div>

<div class="tf-card p-4 mt-4">
    <h2 class="h5 mb-3">Assign Subscription</h2>
    <form method="POST" action="{{ route('admin.subscriptions.activate') }}" class="row g-3">
        @csrf
        <div class="col-md-3"><label class="form-label">Business</label><select name="business_id" class="form-select" required><option value="">Select business</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected(old('business_id') == $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Plan</label><select name="subscription_plan_id" class="form-select" required><option value="">Select plan</option>@foreach($plans->where('status', 'Active') as $plan)<option value="{{ $plan->id }}" @selected(old('subscription_plan_id') == $plan->id)>{{ $plan->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Amount</label><input name="amount" type="number" min="0" step="0.01" class="form-control" value="{{ old('amount') }}" placeholder="Uses plan price"></div>
        <div class="col-md-2"><label class="form-label">Payment Method</label><select name="payment_method" class="form-select">@foreach(['Cash','Bank Transfer','JazzCash Manual','Easypaisa Manual'] as $method)<option value="{{ $method }}" @selected(old('payment_method', 'Cash') === $method)>{{ $method }}</option>@endforeach</select></div>
        <div class="col-md-1"><label class="form-label">Starts</label><input name="starts_at" type="date" class="form-control" value="{{ old('starts_at', now()->toDateString()) }}" required></div>
        <div class="col-md-1"><label class="form-label">Ends</label><input name="ends_at" type="date" class="form-control" value="{{ old('ends_at', now()->addMonth()->toDateString()) }}" required></div>
        <div class="col-md-1"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Active">Active</option><option value="Expired">Expired</option><option value="Cancelled">Cancelled</option></select></div>
        <div class="col-12"><button class="btn btn-tf-primary">Save Subscription</button></div>
    </form>
</div>

<div class="tf-card p-3 mt-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">Business</label><select name="business_id" class="form-select"><option value="">All businesses</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected(request('business_id') == $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Plan</label><select name="subscription_plan_id" class="form-select"><option value="">All plans</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected(request('subscription_plan_id') == $plan->id)>{{ $plan->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['Active','Expired','Cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Payment Method</label><select name="payment_method" class="form-select"><option value="">All methods</option>@foreach(['Cash','Bank Transfer','JazzCash Manual','Easypaisa Manual'] as $method)<option value="{{ $method }}" @selected(request('payment_method') === $method)>{{ $method }}</option>@endforeach</select></div>
        <div class="col-md-1"><label class="form-label">Date From</label><input name="date_from" type="date" class="form-control" value="{{ request('date_from', now()->toDateString()) }}"></div>
        <div class="col-md-1"><label class="form-label">Date To</label><input name="date_to" type="date" class="form-control" value="{{ request('date_to', now()->toDateString()) }}"></div>
        <div class="col-md-1 d-grid"><button class="btn btn-outline-primary">Filter</button></div>
        <div class="col-md-1 d-grid"><a href="{{ route('admin.subscriptions') }}" class="btn btn-outline-secondary">Clear</a></div>
    </form>
</div>

<div class="tf-card p-0 mt-4">
    <div class="p-4 pb-2 d-flex flex-wrap justify-content-between gap-2"><div><h2 class="h5 mb-1">Business Subscriptions</h2><small class="tf-muted">Subscriptions past their end date are marked expired automatically.</small></div></div>
    <x-table><thead><tr><th>Business</th><th>Plan</th><th>Amount</th><th>Method</th><th>Status</th><th>Starts</th><th>Ends</th><th>Updated</th><th class="text-end">Actions</th></tr></thead><tbody>
    @forelse($subscriptions as $subscription)
        @php($statusClass = $subscription->status === 'Active' ? 'tf-badge-success' : ($subscription->status === 'Expired' ? 'tf-badge-warning' : 'tf-badge-danger'))
        <tr>
            <td><strong>{{ $subscription->business?->business_name ?? 'Deleted business' }}</strong></td>
            <td>{{ $subscription->plan?->name ?? 'Deleted plan' }}</td>
            <td>Rs {{ number_format($subscription->amount, 2) }}</td>
            <td>{{ $subscription->payment_method ?: '—' }}</td>
            <td><span class="tf-badge {{ $statusClass }}">{{ $subscription->status }}</span></td>
            <td>{{ $subscription->starts_at?->format('d M, Y') ?? '—' }}</td>
            <td>{{ $subscription->ends_at?->format('d M, Y') ?? '—' }}</td>
            <td><x-date-time :value="$subscription->updated_at" /></td>
            <td class="text-end text-nowrap">
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#subscription-{{ $subscription->id }}">Manage</button>
                @if($subscription->status !== 'Cancelled')
                    <form method="POST" action="{{ route('admin.subscriptions.cancel', $subscription) }}" class="d-inline" onsubmit="return confirm('Cancel this subscription?')">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-sm btn-outline-warning">Cancel</button>
                    </form>
                @endif
                @if(in_array($subscription->status, ['Expired', 'Cancelled'], true))
                    <form method="POST" action="{{ route('admin.subscriptions.destroy', $subscription) }}" class="d-inline" onsubmit="return confirm('Delete this expired or cancelled subscription record?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                @endif
            </td>
        </tr>
        <tr class="collapse" id="subscription-{{ $subscription->id }}"><td colspan="9" class="bg-light"><form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}" class="row g-2 align-items-end">@csrf @method('PATCH')<div class="col-md-2"><label class="form-label">Plan</label><select name="subscription_plan_id" class="form-select" required>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected($plan->id === $subscription->subscription_plan_id)>{{ $plan->name }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label">Amount</label><input name="amount" type="number" min="0" step="0.01" value="{{ $subscription->amount }}" class="form-control"></div><div class="col-md-2"><label class="form-label">Payment Method</label><select name="payment_method" class="form-select">@foreach(['Cash','Bank Transfer','JazzCash Manual','Easypaisa Manual'] as $method)<option value="{{ $method }}" @selected($subscription->payment_method === $method)>{{ $method }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label">Starts</label><input name="starts_at" type="date" value="{{ $subscription->starts_at?->toDateString() ?? now()->toDateString() }}" class="form-control" required></div><div class="col-md-2"><label class="form-label">Ends</label><input name="ends_at" type="date" value="{{ $subscription->ends_at?->toDateString() ?? now()->addMonth()->toDateString() }}" class="form-control" required></div><div class="col-md-1"><label class="form-label">Status</label><select name="status" class="form-select">@foreach(['Active','Expired','Cancelled'] as $status)<option value="{{ $status }}" @selected($subscription->status === $status)>{{ $status }}</option>@endforeach</select></div><div class="col-md-1 d-grid"><button class="btn btn-tf-primary">Save</button></div></form></td></tr>
    @empty
        <tr><td colspan="9" class="text-center tf-muted py-4">No subscriptions match the selected filters.</td></tr>
    @endforelse
    </tbody></x-table>
    <div class="p-3">{{ $subscriptions->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
