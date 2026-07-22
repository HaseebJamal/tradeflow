@extends('layouts.dashboard')

@section('page-title', 'Subscription')
@section('page-subtitle', 'Review your plan, usage, and upgrade options')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@php
    $selectedPlanId = (int) request('plan');
    $selectedBillingCycle = in_array(request('billing_cycle'), ['Monthly', 'Yearly'], true) ? request('billing_cycle') : '';
    $permissions = app(\App\Services\CompanyPermissionService::class);
    $canViewHistory = $permissions->allowsUser(auth()->user(), 'subscriptions.view_history', $business);
    $isLiveSubscription = in_array($subscription?->status, ['Trial', 'Active', 'Expiring'], true);
    $expiry = $subscription?->status === 'Trial' ? $subscription?->trial_end_at : $subscription?->ends_at;
    $daysRemaining = $expiry ? max(0, now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false)) : null;
@endphp

<div class="tf-card p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div><div class="tf-muted small">Current Plan</div><h2 class="h4 mb-1">{{ $subscription?->plan?->name ?? 'No active plan' }}</h2><span class="tf-badge {{ $isLiveSubscription ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $subscription?->status ?? 'Not assigned' }}</span></div>
        @if($daysRemaining !== null)<div class="text-lg-end"><div class="tf-muted small">Days Remaining</div><strong>{{ $daysRemaining }} day{{ $daysRemaining === 1 ? '' : 's' }}</strong></div>@endif
    </div>
    <div class="row g-3">
        <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Billing Cycle</small><strong>{{ $subscription?->billing_cycle ?? '-' }}</strong></div></div>
        <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Start Date</small><strong>{{ $subscription?->starts_at?->format('d M, Y') ?? '-' }}</strong></div></div>
        <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Trial / Expiry</small><strong>{{ $expiry?->format('d M, Y') ?? '-' }}</strong></div></div>
        <div class="col-sm-6 col-lg-3"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Payment Status</small><strong>{{ $subscription?->payment_status ?? 'Pending' }}</strong></div></div>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3"><div><h2 class="h5 mb-1">Available Plans</h2><p class="tf-muted mb-0">Select a plan, billing cycle, and payment method to submit a request.</p></div></div>
<div class="row g-4">
    @foreach($plans as $plan)
        @php
            $isCurrentPlan = $subscription?->subscription_plan_id === $plan->id;
            $relation = ! $subscription?->plan
                ? 'Subscription'
                : ($isCurrentPlan && in_array($subscription->status, ['Expired', 'Cancelled'], true)
                    ? 'Renew'
                    : ($plan->priceFor('Monthly') > $subscription->plan->priceFor('Monthly') ? 'Upgrade' : 'Downgrade'));
            $actionPermission = 'subscriptions.'.strtolower($relation);
            if ($relation === 'Subscription') $actionPermission = 'subscriptions.request';
            $canRequest = $permissions->allowsUser(auth()->user(), $actionPermission, $business);
        @endphp
        <div class="col-md-6 col-xl-4">
            <article class="tf-card p-4 h-100 d-flex flex-column {{ $plan->is_recommended || $plan->id === $selectedPlanId ? 'border-primary' : '' }}">
                <div class="d-flex justify-content-between gap-2"><h2 class="h5 mb-0">{{ $plan->name }}</h2>@if($plan->is_recommended)<span class="tf-badge tf-badge-info">Recommended</span>@endif</div>
                <p class="tf-muted small mt-2">{{ $plan->short_description }}</p>
                <div class="h4 mb-3">Rs {{ number_format($plan->priceFor('Monthly')) }} <small class="tf-muted fs-6">/ month</small></div>
                <ul class="small ps-3 mb-3"><li>{{ number_format($plan->product_limit) }} products</li><li>{{ number_format($plan->staff_limit) }} staff</li><li>{{ number_format($plan->order_limit) }} orders</li><li>{{ (int) $plan->trial_days }} trial days</li>@foreach(array_slice($plan->features ?? [], 0, 3) as $feature)<li>{{ $feature }}</li>@endforeach</ul>
                @if($isCurrentPlan && $isLiveSubscription)
                    <button class="btn btn-outline-secondary w-100 mt-auto" disabled>{{ $subscription?->status === 'Trial' ? 'Current Trial' : 'Current Plan' }}</button>
                @elseif($canRequest)
                    <form method="POST" action="{{ route('business.subscription.requests.store') }}" class="mt-auto" data-subscription-request-form novalidate>
                        @csrf
                        <input type="hidden" name="requested_plan_id" value="{{ $plan->id }}">
                        <div class="row g-2">
                            <div class="col-6"><label class="visually-hidden">Billing Cycle</label><select name="billing_cycle" class="form-select form-select-sm" required data-subscription-billing><option value="">Billing cycle</option><option value="Monthly" @selected($selectedBillingCycle === 'Monthly')>Monthly</option><option value="Yearly" @selected($selectedBillingCycle === 'Yearly')>Yearly</option></select></div>
                            <div class="col-6"><label class="visually-hidden">Payment Method</label><select name="payment_method" class="form-select form-select-sm" required data-subscription-payment><option value="">Payment method</option><option value="Cash">Cash</option><option value="Bank Transfer">Bank Transfer</option><option value="JazzCash Manual">JazzCash Manual</option><option value="Easypaisa Manual">Easypaisa Manual</option></select></div>
                        </div>
                        <button class="btn {{ $relation === 'Upgrade' ? 'btn-tf-primary' : 'btn-outline-primary' }} w-100 mt-2" type="submit" disabled data-subscription-submit>{{ $relation }} Plan</button>
                    </form>
                @else
                    <span class="btn btn-outline-secondary w-100 mt-auto disabled">Permission required</span>
                @endif
            </article>
        </div>
    @endforeach
</div>

@if($canViewHistory)
<div class="tf-card p-0 mt-4">
    <div class="p-3 border-bottom"><h2 class="h5 mb-0">Request History</h2></div>
    <x-table><thead><tr><th>Requested Plan</th><th>Type</th><th>Cycle</th><th>Payment Method</th><th>Expected Amount</th><th>Status</th><th>Admin Note</th><th>Requested At</th><th class="text-end">Actions</th></tr></thead><tbody>
        @forelse($requests as $change)
            <tr><td>{{ $change->requestedPlan?->name }}</td><td>{{ $change->type }}</td><td>{{ $change->billing_cycle }}</td><td>{{ $change->payment_method }}</td><td>Rs {{ number_format($change->expected_amount) }}</td><td><span class="tf-badge tf-badge-info">{{ $change->status }}</span></td><td>{{ $change->admin_note ?: '-' }}</td><td><x-date-time :value="$change->created_at" /></td><td class="text-end">@if($change->status === 'Pending' && $permissions->allowsUser(auth()->user(), 'subscriptions.cancel', $business))<form method="POST" action="{{ route('business.subscription.requests.cancel', $change) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger">Cancel Request</button></form>@endif</td></tr>
        @empty
            <tr><td colspan="9" class="text-center tf-muted py-4">No subscription requests yet.</td></tr>
        @endforelse
    </tbody></x-table>
    <div class="p-3">{{ $requests->links('pagination::bootstrap-5') }}</div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-subscription-request-form]').forEach(function (form) {
        const billing = form.querySelector('[data-subscription-billing]');
        const payment = form.querySelector('[data-subscription-payment]');
        const submit = form.querySelector('[data-subscription-submit]');
        const sync = function () { submit.disabled = !billing.value || !payment.value; };

        billing.addEventListener('change', sync);
        payment.addEventListener('change', sync);
        form.addEventListener('submit', function (event) {
            if (billing.value && payment.value) return;
            event.preventDefault();
            const message = !billing.value && !payment.value
                ? 'Please select a plan, billing cycle, and payment method before submitting your request.'
                : (!billing.value ? 'Please select a billing cycle.' : 'Please select a payment method.');
            window.Swal ? Swal.fire({icon: 'warning', text: message}) : window.alert(message);
        });
        sync();
    });
});
</script>
@endpush
