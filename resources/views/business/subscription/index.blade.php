@extends('layouts.dashboard')

@section('page-title', 'Subscription')
@section('page-subtitle', 'Review your plan, usage, and upgrade options')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="tf-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
        <div><div class="tf-muted small">Current Plan</div><h2 class="h4 mb-1">{{ $subscription?->plan?->name ?? 'No active plan' }}</h2><span class="tf-badge {{ in_array($subscription?->status, ['Trial', 'Active', 'Expiring'], true) ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $subscription?->status ?? 'Not assigned' }}</span></div>
        <div><div class="tf-muted small">Billing Cycle</div><strong>{{ $subscription?->billing_cycle ?? '—' }}</strong></div>
        <div><div class="tf-muted small">Expires</div><strong>{{ ($subscription?->status === 'Trial' ? $subscription?->trial_end_at : $subscription?->ends_at)?->format('d M, Y') ?? '—' }}</strong></div>
    </div>
</div>

<div class="row g-4">
    @foreach($plans as $plan)
        @php($current = $subscription?->subscription_plan_id === $plan->id)
        @php($relation = !$subscription?->plan ? 'Subscription' : ($plan->priceFor('Monthly') > $subscription->plan->priceFor('Monthly') ? 'Upgrade' : 'Downgrade'))
        <div class="col-md-6 col-xl-4"><article class="tf-card p-4 h-100 {{ $plan->is_recommended ? 'border-primary' : '' }}"><div class="d-flex justify-content-between gap-2"><h2 class="h5">{{ $plan->name }}</h2>@if($plan->is_recommended)<span class="tf-badge tf-badge-info">Recommended</span>@endif</div><p class="tf-muted small">{{ $plan->short_description }}</p><div class="h4">Rs {{ number_format($plan->priceFor('Monthly')) }} <small class="tf-muted fs-6">/ month</small></div><ul class="small ps-3"><li>{{ number_format($plan->product_limit) }} products</li><li>{{ number_format($plan->staff_limit) }} staff</li><li>{{ number_format($plan->order_limit) }} orders</li></ul>@if($current)<button class="btn btn-outline-secondary w-100 mt-auto" disabled>{{ $subscription?->status === 'Trial' ? 'Current Trial' : 'Current Plan' }}</button>@else<form method="POST" action="{{ route('business.subscription.requests.store') }}" class="mt-auto">@csrf<input type="hidden" name="requested_plan_id" value="{{ $plan->id }}"><div class="row g-2"><div class="col-6"><select name="billing_cycle" class="form-select form-select-sm"><option value="Monthly">Monthly</option><option value="Yearly">Yearly</option></select></div><div class="col-6"><select name="payment_method" class="form-select form-select-sm"><option value="">Payment method</option><option>Cash</option><option>Bank Transfer</option><option>JazzCash Manual</option><option>Easypaisa Manual</option></select></div></div><button class="btn {{ $relation === 'Upgrade' ? 'btn-tf-primary' : 'btn-outline-primary' }} w-100 mt-2">{{ $relation }} Plan</button></form>@endif</article></div>
    @endforeach
</div>

<div class="tf-card p-0 mt-4"><div class="p-3 border-bottom"><h2 class="h5 mb-0">Request History</h2></div><x-table><thead><tr><th>Requested Plan</th><th>Type</th><th>Cycle</th><th>Expected Amount</th><th>Status</th><th>Requested At</th></tr></thead><tbody>@forelse($requests as $change)<tr><td>{{ $change->requestedPlan?->name }}</td><td>{{ $change->type }}</td><td>{{ $change->billing_cycle }}</td><td>Rs {{ number_format($change->expected_amount) }}</td><td><span class="tf-badge tf-badge-info">{{ $change->status }}</span></td><td><x-date-time :value="$change->created_at" /></td></tr>@empty<tr><td colspan="6" class="text-center tf-muted py-4">No subscription requests yet.</td></tr>@endforelse</tbody></x-table><div class="p-3">{{ $requests->links('pagination::bootstrap-5') }}</div></div>
@endsection
