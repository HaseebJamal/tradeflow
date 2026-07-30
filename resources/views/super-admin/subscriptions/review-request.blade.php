@extends('layouts.dashboard')

@section('page-title', 'Review Subscription Request')
@section('page-subtitle', $changeRequest->business?->business_name.' subscription request')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@php
    $business = $changeRequest->business;
    $currentSubscription = $changeRequest->subscription ?? $business?->subscription;
    $selectedPlan = $changeRequest->requestedPlan;
    $canProcess = in_array($changeRequest->status, ['Pending', 'Changes Requested'], true);
@endphp

<div class="d-flex flex-wrap gap-2 mb-4"><a class="btn btn-outline-primary" href="{{ route('admin.subscriptions', ['tab' => 'business-subscriptions']) }}"><i class="bi bi-arrow-left me-1"></i>Back to Subscriptions</a><a class="btn btn-outline-primary" href="{{ route('admin.companies.show', $business) }}">View Company</a></div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="tf-card p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"><div><h2 class="h5 mb-1">Requested Subscription</h2><p class="tf-muted mb-0">Submitted by {{ $changeRequest->requester?->name ?? 'Business user' }} on <x-date-time :value="$changeRequest->created_at" /></p></div><span class="tf-badge {{ $changeRequest->status === 'Pending' ? 'tf-badge-warning' : 'tf-badge-info' }}">{{ $changeRequest->status }}</span></div>
            <div class="row g-3">
                <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Business</small><strong>{{ $business?->business_name }}</strong></div></div>
                <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Requested Plan</small><strong>{{ $selectedPlan?->name }}</strong></div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Billing Cycle</small><strong>{{ $changeRequest->billing_cycle }}</strong></div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Payment Method</small><strong>{{ $changeRequest->payment_method }}</strong></div></div>
                <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Requested Price</small><strong>Rs {{ number_format($changeRequest->expected_amount) }}</strong></div></div>
                <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Trial Status</small><strong>{{ $changeRequest->trial_eligible ? ((int) $changeRequest->trial_days.'-day trial') : 'No trial' }}</strong></div></div>
                <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Requested Dates</small><strong>{{ $changeRequest->starts_at?->format('d M, Y') ?? '-' }} to {{ $changeRequest->ends_at?->format('d M, Y') ?? '-' }}</strong></div></div>
            </div>
        </div>

        <div class="tf-card p-4 mt-4">
            <h2 class="h5 mb-3">Review Details</h2>
            @if($canProcess)
                <form method="POST" action="{{ route('admin.subscription-change-requests.review-details', $changeRequest) }}" class="row g-3" data-subscription-review-form>@csrf @method('PATCH')
                    <div class="col-md-6"><label class="form-label">Plan</label><select name="requested_plan_id" class="form-select" required data-review-plan>@foreach($plans as $plan)<option value="{{ $plan->id }}" data-monthly="{{ $plan->priceFor('Monthly') }}" data-yearly="{{ $plan->priceFor('Yearly') }}" data-trial="{{ $plan->trial_days }}" @selected($plan->id === $changeRequest->requested_plan_id)>{{ $plan->name }}{{ $plan->is_public ? '' : ' (Private)' }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Billing Cycle</label><select name="billing_cycle" class="form-select" data-review-cycle><option value="Monthly" @selected($changeRequest->billing_cycle === 'Monthly')>Monthly</option><option value="Yearly" @selected($changeRequest->billing_cycle === 'Yearly')>Yearly</option></select></div>
                    <div class="col-md-3"><label class="form-label">Authoritative Amount</label><div class="input-group"><span class="input-group-text">Rs</span><input class="form-control" readonly value="{{ number_format($changeRequest->expected_amount) }}" data-review-amount></div></div>
                    <div class="col-md-3"><label class="form-label">Start Date</label><input type="date" name="starts_at" class="form-control" value="{{ $changeRequest->starts_at?->toDateString() ?? now()->toDateString() }}"></div>
                    <div class="col-md-3"><label class="form-label">End Date</label><input type="date" name="ends_at" class="form-control" value="{{ $changeRequest->ends_at?->toDateString() ?? ($changeRequest->billing_cycle === 'Yearly' ? now()->addYear()->toDateString() : now()->addMonth()->toDateString()) }}"></div>
                    <div class="col-md-3"><label class="form-label">Effective Date</label><input type="date" name="effective_at" class="form-control" value="{{ $changeRequest->effective_at?->toDateString() ?? now()->toDateString() }}"></div>
                    <div class="col-md-3"><label class="form-label">Trial Days</label><input type="number" name="trial_days" class="form-control" min="0" max="365" step="1" value="{{ $changeRequest->trial_days ?? $selectedPlan?->trial_days ?? 0 }}" data-review-trial></div>
                    <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input type="hidden" name="trial_eligible" value="0"><input class="form-check-input" type="checkbox" name="trial_eligible" value="1" id="reviewTrial" @checked($changeRequest->trial_eligible)><label class="form-check-label" for="reviewTrial">Enable trial</label></div></div>
                    <div class="col-12"><label class="form-label">Admin Note</label><textarea class="form-control" name="admin_note" rows="3" maxlength="2000" placeholder="Optional note for the business owner">{{ $changeRequest->admin_note }}</textarea></div>
                    <div class="col-12"><button class="btn btn-tf-primary">Save Review Details</button></div>
                </form>
            @else
                <p class="tf-muted mb-0">This request has already been processed. Review details are read-only.</p>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        <div class="tf-card p-4"><h2 class="h5 mb-3">Current Subscription</h2><p class="mb-1"><strong>{{ $currentSubscription?->plan?->name ?? 'No current subscription' }}</strong></p><p class="tf-muted mb-1">{{ $currentSubscription?->status ?? 'Not assigned' }}{{ $currentSubscription?->billing_cycle ? ' - '.$currentSubscription->billing_cycle : '' }}</p><p class="tf-muted mb-0">Expires: {{ $currentSubscription?->ends_at?->format('d M, Y') ?? '-' }}</p></div>
        @if($canProcess)
            <div class="tf-card p-4 mt-4"><h2 class="h5 mb-3">Decision</h2><form method="POST" action="{{ route('admin.subscription-change-requests.review', $changeRequest) }}">@csrf @method('PATCH')<label class="form-label" for="decisionNote">Decision Note</label><textarea class="form-control mb-3" id="decisionNote" name="admin_note" rows="3" maxlength="2000" placeholder="Explain requested changes or the decision, if needed">{{ $changeRequest->admin_note }}</textarea><div class="d-grid gap-2"><button class="btn btn-outline-primary w-100" name="decision" value="Approved">Approve Request</button><button class="btn btn-success w-100" name="decision" value="Activate">Activate Subscription</button><button class="btn btn-outline-warning w-100" name="decision" value="Changes Requested">Request Changes</button><button class="btn btn-outline-danger w-100" name="decision" value="Rejected">Reject Request</button></div></form></div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-subscription-review-form]');
    if (!form) return;
    const plan = form.querySelector('[data-review-plan]');
    const cycle = form.querySelector('[data-review-cycle]');
    const amount = form.querySelector('[data-review-amount]');
    const trial = form.querySelector('[data-review-trial]');
    const sync = function () { const option = plan.options[plan.selectedIndex]; if (!option) return; amount.value = new Intl.NumberFormat().format(Number(cycle.value === 'Yearly' ? option.dataset.yearly : option.dataset.monthly)); trial.value = option.dataset.trial || 0; };
    plan.addEventListener('change', sync); cycle.addEventListener('change', sync);
});
</script>
@endpush
