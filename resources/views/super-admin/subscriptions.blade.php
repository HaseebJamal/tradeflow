@extends('layouts.dashboard')
@section('page-title', 'Subscriptions')
@section('page-subtitle', 'Manage plans, billing, assignments and renewals')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@php($activeSubscriptionTab = request('tab', $lockedBusiness ? 'assign-subscription' : 'subscription-plans'))

<div class="tf-subscriptions-page">
    <nav class="tf-subscription-tabs-wrap" aria-label="Subscription navigation">
        <ul class="nav nav-tabs tf-subscription-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link {{ $activeSubscriptionTab === 'subscription-plans' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#subscription-plans" type="button">Subscription Plans</button></li>
            <li class="nav-item"><button class="nav-link {{ $activeSubscriptionTab === 'assign-subscription' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#assign-subscription" type="button">Assign Subscription</button></li>
            <li class="nav-item"><button class="nav-link {{ $activeSubscriptionTab === 'business-subscriptions' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#business-subscriptions" type="button">Business Subscriptions</button></li>
            <li class="nav-item"><button class="nav-link {{ $activeSubscriptionTab === 'billing-history' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#billing-history" type="button">Billing History</button></li>
        </ul>
    </nav>

    <section class="tf-subscription-summary" aria-label="Subscription overview">
        <div class="tf-subscription-kpis">
    @foreach([
        ['Active Subscriptions', $stats['active'], 'bi-check2-circle', 'bg-green'],
        ['Expired Subscriptions', $stats['expired'], 'bi-calendar-x', 'bg-amber'],
        ['Cancelled Subscriptions', $stats['cancelled'], 'bi-x-circle', 'bg-red'],
        ['Revenue This Month', 'Rs '.number_format($stats['monthly_revenue']), 'bi-cash-stack', 'bg-blue'],
    ] as [$label, $value, $icon, $color])
        <article class="tf-card tf-stat-card tf-subscription-kpi"><div class="d-flex justify-content-between align-items-start gap-2"><div><div class="tf-muted small">{{ $label }}</div><div class="h3 mb-0">{{ $value }}</div></div><span class="tf-brand-mark {{ $color }}"><i class="bi {{ $icon }}"></i></span></div></article>
    @endforeach
        </div>
        <div class="tf-subscription-as-of">Current date: {{ now()->format('n/j/Y') }}</div>
    </section>

<div class="tab-content tf-subscription-content">
    <section class="tab-pane fade {{ $activeSubscriptionTab === 'subscription-plans' ? 'show active' : '' }}" id="subscription-plans">
        <div class="tf-subscription-section-toolbar">
            <div><h2 class="h5 mb-1">Subscription Plans</h2><p class="tf-muted small mb-0">Create, review, and maintain your subscription plans.</p></div>
            <button class="btn btn-tf-primary" type="button" data-bs-toggle="modal" data-bs-target="#createSubscriptionPlanModal"><i class="bi bi-plus-lg me-1"></i>Create Plan</button>
        </div>
        <div class="tf-card tf-subscription-filter-card p-3 mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-5"><label class="form-label">Search Plans</label><input class="form-control" data-plan-search placeholder="Plan name"></div>
                <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" data-plan-status><option value="">All statuses</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
            </div>
        </div>
        <div class="tf-card p-0">
            <x-table><thead><tr><th>Plan</th><th>Price</th><th>Product Limit</th><th>Staff Limit</th><th>Order Limit</th><th>Active Businesses</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody data-plan-table>
            @forelse($plans as $plan)
                <tr data-plan-row data-plan-name="{{ strtolower($plan->name) }}" data-plan-status="{{ $plan->status }}">
                    <td><strong>{{ $plan->name }}</strong></td><td>Rs {{ number_format($plan->price) }}</td><td>{{ number_format($plan->product_limit) }}</td><td>{{ number_format($plan->staff_limit) }}</td><td>{{ number_format($plan->order_limit) }}</td><td>{{ $plan->subscriptions_count }}</td><td><span class="tf-badge {{ $plan->status === 'Active' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $plan->status }}</span></td>
                    <td class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPlanModal-{{ $plan->id }}">Edit</button><form method="POST" action="{{ route('admin.subscription-plans.update', $plan) }}" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="name" value="{{ $plan->name }}"><input type="hidden" name="price" value="{{ (int) $plan->price }}"><input type="hidden" name="product_limit" value="{{ $plan->product_limit }}"><input type="hidden" name="staff_limit" value="{{ $plan->staff_limit }}"><input type="hidden" name="order_limit" value="{{ $plan->order_limit }}"><input type="hidden" name="status" value="{{ $plan->status === 'Active' ? 'Inactive' : 'Active' }}"><button class="btn btn-sm btn-outline-warning">{{ $plan->status === 'Active' ? 'Deactivate' : 'Activate' }}</button></form><form method="POST" action="{{ route('admin.subscription-plans.destroy', $plan) }}" class="d-inline" onsubmit="return confirm('Delete this plan? Plans with history are deactivated instead.')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></td>
                </tr>
            @empty<tr><td colspan="8" class="text-center tf-muted py-4">No subscription plans created yet.</td></tr>@endforelse
            </tbody></x-table>
        </div>
    </section>

    <section class="tab-pane fade {{ $activeSubscriptionTab === 'assign-subscription' ? 'show active' : '' }}" id="assign-subscription">
        <div class="tf-card p-4">
            <div class="mb-3"><h2 class="h5 mb-1">Assign Subscription</h2>@if($lockedBusiness)<small class="tf-muted">This subscription is locked to the company selected from the Companies actions menu.</small>@endif</div>
            <form method="POST" action="{{ route('admin.subscriptions.activate') }}" class="row g-3" data-subscription-assignment-form>@csrf
                @if($lockedBusiness)
                    <div class="col-md-4"><label class="form-label">Business</label><input class="form-control" value="{{ $lockedBusiness->business_name }}" readonly><input type="hidden" name="business_id" value="{{ $lockedBusiness->id }}"><input type="hidden" name="subscription_context_business_id" value="{{ $lockedBusiness->id }}"></div>
                @else
                    <div class="col-md-4"><label class="form-label">Business</label><select name="business_id" class="form-select" required><option value="">Select business</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected((string) old('business_id') === (string) $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
                @endif
                <div class="col-md-3"><label class="form-label">Plan</label><select name="subscription_plan_id" class="form-select" required data-subscription-plan><option value="">Select plan</option>@foreach($plans->where('status', 'Active') as $plan)<option value="{{ $plan->id }}" data-price="{{ (int) $plan->price }}" @selected(old('subscription_plan_id') == $plan->id)>{{ $plan->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label">Amount</label><input name="amount" type="number" min="0" step="1" class="form-control" value="{{ old('amount') }}" placeholder="Plan price" data-subscription-amount></div>
                <div class="col-md-3"><label class="form-label">Payment Method</label><select name="payment_method" class="form-select">@foreach(['Cash','Bank Transfer','JazzCash Manual','Easypaisa Manual'] as $method)<option value="{{ $method }}" @selected(old('payment_method', 'Cash') === $method)>{{ $method }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Start Date</label><input name="starts_at" type="date" class="form-control" value="{{ old('starts_at', now()->toDateString()) }}" required></div>
                <div class="col-md-3"><label class="form-label">End Date</label><input name="ends_at" type="date" class="form-control" value="{{ old('ends_at', now()->addMonth()->toDateString()) }}" required></div>
                <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Active">Active</option><option value="Expired">Expired</option><option value="Cancelled">Cancelled</option></select></div>
                <div class="col-md-3 d-flex align-items-end"><button class="btn btn-tf-primary w-100">Save Subscription</button></div>
            </form>
        </div>
    </section>

    <section class="tab-pane fade {{ $activeSubscriptionTab === 'business-subscriptions' ? 'show active' : '' }}" id="business-subscriptions">
        <div class="tf-card p-3 mb-3"><form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="business-subscriptions">
            <div class="col-md-3"><label class="form-label">Business</label><select name="business_id" class="form-select"><option value="">All businesses</option>@foreach($businesses as $business)<option value="{{ $business->id }}" @selected(request('business_id') == $business->id)>{{ $business->business_name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Plan</label><select name="subscription_plan_id" class="form-select"><option value="">All plans</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected(request('subscription_plan_id') == $plan->id)>{{ $plan->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['Active','Expired','Cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Payment Method</label><select name="payment_method" class="form-select"><option value="">All methods</option>@foreach(['Cash','Bank Transfer','JazzCash Manual','Easypaisa Manual'] as $method)<option value="{{ $method }}" @selected(request('payment_method') === $method)>{{ $method }}</option>@endforeach</select></div>
            <div class="col-md-1"><label class="form-label">From</label><input name="date_from" type="date" value="{{ request('date_from', now()->toDateString()) }}" class="form-control"></div><div class="col-md-1"><label class="form-label">To</label><input name="date_to" type="date" value="{{ request('date_to', now()->toDateString()) }}" class="form-control"></div><div class="col-md-1 d-grid"><button class="btn btn-outline-primary">Filter</button></div><div class="col-md-1 d-grid"><a href="{{ route('admin.subscriptions') }}" class="btn btn-outline-secondary">Clear</a></div>
        </form></div>
        <div class="tf-card p-0"><x-table><thead><tr><th>Business</th><th>Plan</th><th>Amount</th><th>Method</th><th>Status</th><th>Starts</th><th>Ends</th><th>Updated</th><th class="text-end">Actions</th></tr></thead><tbody>
        @forelse($subscriptions as $subscription)
            @php($statusClass = $subscription->status === 'Active' ? 'tf-badge-success' : ($subscription->status === 'Expired' ? 'tf-badge-warning' : 'tf-badge-danger'))
            <tr><td><strong>{{ $subscription->business?->business_name ?? 'Deleted business' }}</strong></td><td>{{ $subscription->plan?->name ?? 'Deleted plan' }}</td><td>Rs {{ number_format($subscription->amount) }}</td><td>{{ $subscription->payment_method ?: '—' }}</td><td><span class="tf-badge {{ $statusClass }}">{{ $subscription->status }}</span></td><td>{{ $subscription->starts_at?->format('d M, Y') ?? '—' }}</td><td>{{ $subscription->ends_at?->format('d M, Y') ?? '—' }}</td><td><x-date-time :value="$subscription->updated_at" /></td><td class="text-end text-nowrap">
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#subscription-{{ $subscription->id }}">Manage</button>
                @if($subscription->status === 'Active')
                    <form method="POST" action="{{ route('admin.subscriptions.cancel', $subscription) }}" class="d-inline" onsubmit="return confirm('Cancel this subscription?')">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-sm btn-outline-warning">Cancel</button>
                    </form>
                @endif
                @if(in_array($subscription->status, ['Expired', 'Cancelled'], true))
                    <form method="POST" action="{{ route('admin.subscriptions.destroy', $subscription) }}" class="d-inline" onsubmit="return confirm('Delete this inactive subscription record?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                @endif
            </td></tr>
            <tr class="collapse" id="subscription-{{ $subscription->id }}"><td colspan="9" class="bg-light"><form method="POST" action="{{ route('admin.subscriptions.update', $subscription) }}" class="row g-2 align-items-end">@csrf @method('PATCH')<div class="col-md-2"><label class="form-label">Plan</label><select name="subscription_plan_id" class="form-select" required>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected($plan->id === $subscription->subscription_plan_id)>{{ $plan->name }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label">Amount</label><input name="amount" type="number" min="0" step="1" value="{{ (int) $subscription->amount }}" class="form-control"></div><div class="col-md-2"><label class="form-label">Payment Method</label><select name="payment_method" class="form-select">@foreach(['Cash','Bank Transfer','JazzCash Manual','Easypaisa Manual'] as $method)<option value="{{ $method }}" @selected($subscription->payment_method === $method)>{{ $method }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label">Starts</label><input name="starts_at" type="date" value="{{ $subscription->starts_at?->toDateString() ?? now()->toDateString() }}" class="form-control" required></div><div class="col-md-2"><label class="form-label">Ends</label><input name="ends_at" type="date" value="{{ $subscription->ends_at?->toDateString() ?? now()->addMonth()->toDateString() }}" class="form-control" required></div><div class="col-md-1"><label class="form-label">Status</label><select name="status" class="form-select">@foreach(['Active','Expired','Cancelled'] as $status)<option value="{{ $status }}" @selected($subscription->status === $status)>{{ $status }}</option>@endforeach</select></div><div class="col-md-1 d-grid"><button class="btn btn-tf-primary">Save</button></div></form></td></tr>
        @empty<tr><td colspan="9" class="text-center tf-muted py-4">No subscriptions match the selected filters.</td></tr>@endforelse
        </tbody></x-table><div class="p-3">{{ $subscriptions->links('pagination::bootstrap-5') }}</div></div>
    </section>

    <section class="tab-pane fade {{ $activeSubscriptionTab === 'billing-history' ? 'show active' : '' }}" id="billing-history">
        <div class="tf-card p-0"><x-table><thead><tr><th>Business</th><th>Plan</th><th>Amount</th><th>Method</th><th>Payment Date</th><th>Status</th><th>Reference</th><th>Recorded By</th></tr></thead><tbody>
        @forelse($billingHistory as $payment)<tr><td>{{ $payment->business?->business_name ?: '—' }}</td><td>{{ $payment->subscription?->plan?->name ?: '—' }}</td><td>Rs {{ number_format($payment->amount) }}</td><td>{{ $payment->method }}</td><td><x-date-time :value="$payment->paid_at" /></td><td>{{ $payment->status }}</td><td>{{ $payment->reference_number ?: '—' }}</td><td>{{ $payment->recordedBy?->name ?: 'System' }}</td></tr>@empty<tr><td colspan="8" class="text-center tf-muted py-4">No billing history has been recorded.</td></tr>@endforelse
        </tbody></x-table></div>
    </section>
</div>

<div class="modal fade" id="createSubscriptionPlanModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="POST" action="{{ route('admin.subscription-plans.store') }}">@csrf<div class="modal-header"><h2 class="modal-title h5">Create Plan</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body row g-3"><div class="col-md-8"><label class="form-label">Plan Name</label><input name="name" class="form-control" maxlength="100" required></div><div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div><div class="col-md-3"><label class="form-label">Price</label><input name="price" type="number" min="0" step="1" value="0" class="form-control" required></div><div class="col-md-3"><label class="form-label">Product Limit</label><input name="product_limit" type="number" min="0" step="1" value="0" class="form-control" required></div><div class="col-md-3"><label class="form-label">Staff Limit</label><input name="staff_limit" type="number" min="0" step="1" value="0" class="form-control" required></div><div class="col-md-3"><label class="form-label">Order Limit</label><input name="order_limit" type="number" min="0" step="1" value="0" class="form-control" required></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tf-primary">Create Plan</button></div></form></div></div></div>

@foreach($plans as $plan)
<div class="modal fade" id="editPlanModal-{{ $plan->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="POST" action="{{ route('admin.subscription-plans.update', $plan) }}">@csrf @method('PATCH')<div class="modal-header"><h2 class="modal-title h5">Edit {{ $plan->name }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body row g-3"><div class="col-md-8"><label class="form-label">Plan Name</label><input name="name" class="form-control" value="{{ $plan->name }}" maxlength="100" required></div><div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="Active" @selected($plan->status === 'Active')>Active</option><option value="Inactive" @selected($plan->status === 'Inactive')>Inactive</option></select></div><div class="col-md-3"><label class="form-label">Price</label><input name="price" type="number" min="0" step="1" value="{{ (int) $plan->price }}" class="form-control" required></div><div class="col-md-3"><label class="form-label">Product Limit</label><input name="product_limit" type="number" min="0" step="1" value="{{ $plan->product_limit }}" class="form-control" required></div><div class="col-md-3"><label class="form-label">Staff Limit</label><input name="staff_limit" type="number" min="0" step="1" value="{{ $plan->staff_limit }}" class="form-control" required></div><div class="col-md-3"><label class="form-label">Order Limit</label><input name="order_limit" type="number" min="0" step="1" value="{{ $plan->order_limit }}" class="form-control" required></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-tf-primary">Save Changes</button></div></form></div></div></div>
@endforeach
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const search = document.querySelector('[data-plan-search]');
    const status = document.querySelector('[data-plan-status]');
    const filterPlans = () => document.querySelectorAll('[data-plan-row]').forEach((row) => {
        const nameMatches = !search?.value || row.dataset.planName.includes(search.value.trim().toLowerCase());
        const statusMatches = !status?.value || row.dataset.planStatus === status.value;
        row.classList.toggle('d-none', !(nameMatches && statusMatches));
    });
    search?.addEventListener('input', filterPlans);
    status?.addEventListener('change', filterPlans);

    const form = document.querySelector('[data-subscription-assignment-form]');
    const plan = form?.querySelector('[data-subscription-plan]');
    const amount = form?.querySelector('[data-subscription-amount]');
    plan?.addEventListener('change', () => {
        const selected = plan.selectedOptions[0];
        if (selected?.dataset.price) amount.value = selected.dataset.price;
    });
});
</script>
@endpush
