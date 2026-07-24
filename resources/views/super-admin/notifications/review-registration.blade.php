@extends('layouts.dashboard')
@section('page-title', 'Registration Review')
@section('page-subtitle', 'Review and decide this company registration without leaving the notification workflow')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@php
    $registrationSnapshot = $business->selected_plan_snapshot ?? [];
    $registrationPlan = $business->selectedPlan ?? $business->subscription?->plan;
    $planName = $registrationSnapshot['plan_name'] ?? $registrationPlan?->name;
    $planCycle = $registrationSnapshot['billing_cycle'] ?? $business->selected_billing_cycle ?? $business->subscription?->billing_cycle;
    $planPrice = $registrationSnapshot['selected_price'] ?? $business->selected_plan_price ?? $business->subscription?->amount;
    $planStatus = $registrationSnapshot['plan_status'] ?? $registrationPlan?->status;
    $planModules = collect($registrationSnapshot['included_modules'] ?? $registrationPlan?->included_modules ?? [])->filter();
    $planSelectionSource = $business->plan_selection_source === 'pricing' ? 'Landing Pricing' : 'Registration Form';
    $reviewPlanId = old('selected_plan_id', $business->selected_plan_id ?? $business->subscription?->subscription_plan_id);
@endphp

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
    <a href="{{ route('admin.notifications.registrations') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Registration queue</a>
    <a href="{{ route('admin.companies.show', $business) }}" class="btn btn-outline-primary">Open full company workspace <i class="bi bi-box-arrow-up-right ms-1"></i></a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="tf-card p-4 mb-4">
            <div class="d-flex flex-wrap gap-3 align-items-center mb-4">
                @if($business->logo)<img src="{{ asset('storage/'.$business->logo) }}" alt="{{ $business->business_name }} logo" class="rounded border" style="width:64px;height:64px;object-fit:cover">@else<span class="tf-icon-tile bg-blue text-white" style="width:64px;height:64px"><i class="bi bi-buildings fs-4"></i></span>@endif
                <div><h2 class="h4 mb-1">{{ $business->business_name }}</h2><p class="tf-muted mb-0">Submitted <x-date-time :value="$item->created_at" /></p></div>
                @php($statusClass = match(strtolower($business->status)) { 'approved' => 'success', 'rejected', 'suspended' => 'danger', default => 'warning' })
                <span class="tf-badge tf-badge-{{ $statusClass }} ms-lg-auto">{{ ucfirst($business->status) }}</span>
            </div>
            <div class="row g-3">
                <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block mb-1">Business type</small><strong>{{ $business->display_business_type ?: 'Not provided' }}</strong><small class="tf-muted d-block mt-2">Category: {{ $business->category ?: 'Not provided' }}</small></div></div>
                <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block mb-1">Business contact</small><strong>{{ $business->phone ?: 'Not provided' }}</strong><small class="tf-muted d-block mt-2">{{ $business->city ?: 'City not provided' }}</small></div></div>
                <div class="col-12"><div class="border rounded p-3"><small class="tf-muted d-block mb-1">Registered address</small>{{ $business->address ?: 'Not provided' }}</div></div>
            </div>
        </div>

        <div class="tf-card p-4 mb-4"><h2 class="h5 mb-3">Owner account</h2><div class="row g-3"><div class="col-md-4"><small class="tf-muted d-block">Name</small><strong>{{ $business->owner?->name ?? 'Not provided' }}</strong></div><div class="col-md-4"><small class="tf-muted d-block">Owner Login Email</small><strong class="text-break">{{ $business->owner?->email ?? 'Not provided' }}</strong></div><div class="col-md-4"><small class="tf-muted d-block">Private owner phone</small><strong>{{ $business->owner?->phone ?? 'Not provided' }}</strong></div></div></div>

        <div class="tf-card p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div><h2 class="h5 mb-1">Selected Subscription Plan</h2><p class="tf-muted mb-0">Plan information submitted with this registration.</p></div>
                <span class="tf-badge {{ $business->subscription_request_status === 'Activated' ? 'tf-badge-success' : 'tf-badge-info' }}">{{ $business->subscription_request_status ?? 'Pending Review' }}</span>
            </div>
            @if($planName)
                <div class="row g-3">
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Plan Name</small><strong>{{ $planName }}</strong></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Billing Cycle</small><strong>{{ $planCycle ?: 'Not recorded' }}</strong></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Selected Price</small><strong>Rs {{ number_format((int) $planPrice) }}</strong></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Selection Source</small><strong>{{ $planSelectionSource }}</strong></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Trial Days</small><strong>{{ $business->trial_eligible ? (int) ($business->requested_trial_days ?? $registrationSnapshot['trial_days'] ?? 0).' days' : 'No trial' }}</strong></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Product Limit</small><strong>{{ number_format((int) ($registrationSnapshot['product_limit'] ?? $registrationPlan?->product_limit ?? 0)) }}</strong></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Staff Limit</small><strong>{{ number_format((int) ($registrationSnapshot['staff_limit'] ?? $registrationPlan?->staff_limit ?? 0)) }}</strong></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Order Limit</small><strong>{{ number_format((int) ($registrationSnapshot['order_limit'] ?? $registrationPlan?->order_limit ?? 0)) }}</strong></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Plan Status</small><strong>{{ $planStatus ?: 'Not recorded' }}</strong></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Selected At</small><strong><x-date-time :value="$business->plan_selected_at" /></strong></div></div>
                    @if($planModules->isNotEmpty())<div class="col-12"><div class="border rounded p-3"><small class="tf-muted d-block mb-2">Included Modules</small><div class="d-flex flex-wrap gap-2">@foreach($planModules as $module)<span class="tf-badge tf-badge-info">{{ $module }}</span>@endforeach</div></div></div>@endif
                </div>
            @else
                <div class="alert alert-warning mb-0">No plan selection recorded.</div>
            @endif
        </div>

        <div class="tf-card p-4"><h2 class="h5 mb-3">Verification documents</h2><div class="row g-2">@forelse($business->documents as $document)<div class="col-md-6"><a href="{{ asset('storage/'.$document->file_path) }}" target="_blank" class="d-flex align-items-center gap-2 border rounded p-3 text-decoration-none"><i class="bi bi-file-earmark-text fs-4 text-primary"></i><span>{{ Str::headline($document->document_type) }}<small class="d-block tf-muted">Open document</small></span></a></div>@empty<div class="col-12"><p class="tf-muted mb-0">No verification documents were submitted.</p></div>@endforelse</div></div>
    </div>

    <div class="col-lg-4">
        <div class="tf-card p-4 mb-4"><h2 class="h5 mb-1">Decision control</h2><p class="tf-muted small">Your decision is recorded in the approval history and audit log.</p><form method="POST" action="{{ route('admin.companies.status', $business) }}" class="row g-3">@csrf @method('PATCH')<div class="col-12"><label for="review-status" class="form-label">Registration status</label><select id="review-status" name="status" class="form-select"><option value="pending" @selected(strtolower($business->status) === 'pending')>Keep pending</option><option value="approved" @selected(strtolower($business->status) === 'approved')>Approve and activate</option><option value="rejected" @selected(strtolower($business->status) === 'rejected')>Reject registration</option><option value="suspended" @selected(strtolower($business->status) === 'suspended')>Suspend account</option></select></div><div class="col-12"><label for="review-note" class="form-label">Decision note</label><textarea id="review-note" name="admin_note" class="form-control" rows="5" placeholder="Add the reason, requested changes, or approval note"></textarea></div><div class="col-12"><button class="btn btn-tf-primary w-100"><i class="bi bi-check2-circle me-1"></i>Save decision</button></div></form></div>
        @if(strtolower((string) $business->status) === 'pending')
            <div class="tf-card p-4 mb-4" data-registration-review-plan>
                <h2 class="h5 mb-1">Plan Review</h2><p class="tf-muted small">Confirm the registration plan before approving this business.</p>
                <form method="POST" action="{{ route('admin.companies.registration-plan.update', $business) }}" class="row g-3">@csrf @method('PATCH')
                    <div class="col-12"><label class="form-label">Review Action</label><select name="plan_action" class="form-select"><option value="keep">Keep Selected Plan</option><option value="change">Change Plan</option><option value="require_selection">Require Plan Selection</option></select></div>
                    <div class="col-12"><label class="form-label">Plan</label><select name="selected_plan_id" class="form-select" data-registration-review-plan-select><option value="" @selected(! $reviewPlanId)>Select plan</option>@if($registrationPlan && ! $adminPlans->contains('id', $registrationPlan->id))<option value="{{ $registrationPlan->id }}" selected disabled>{{ $registrationPlan->name }} (Unavailable)</option>@endif @foreach($adminPlans as $plan)<option value="{{ $plan->id }}" data-monthly="{{ $plan->priceFor('Monthly') }}" data-yearly="{{ $plan->priceFor('Yearly') }}" data-trial-days="{{ $plan->trial_days }}" @selected((string) $reviewPlanId === (string) $plan->id)>{{ $plan->name }}{{ $plan->is_public ? '' : ' (Private)' }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label">Billing Cycle</label><select name="billing_cycle" class="form-select" data-registration-review-cycle><option value="Monthly" @selected(old('billing_cycle', $planCycle) === 'Monthly')>Monthly</option><option value="Yearly" @selected(old('billing_cycle', $planCycle) === 'Yearly')>Yearly</option></select></div>
                    <div class="col-12"><label class="form-label">Confirmed Amount</label><div class="input-group"><span class="input-group-text">Rs</span><input class="form-control" readonly value="{{ number_format((int) $planPrice) }}" data-registration-review-amount></div></div>
                    <div class="col-12"><label class="form-label">Trial Days</label><input type="number" name="requested_trial_days" class="form-control" min="0" max="365" step="1" value="{{ old('requested_trial_days', $business->requested_trial_days ?? $registrationSnapshot['trial_days'] ?? 0) }}" data-registration-review-trial-days></div>
                    <div class="col-12"><input type="hidden" name="trial_eligible" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="trial_eligible" value="1" id="reviewTrialEligible" @checked(old('trial_eligible', $business->trial_eligible))><label class="form-check-label" for="reviewTrialEligible">Approve free trial</label></div></div>
                    <div class="col-12"><label class="form-label">Change Reason <span class="tf-muted">(required when changing plan or billing)</span></label><textarea name="change_reason" class="form-control" rows="2" maxlength="2000">{{ old('change_reason') }}</textarea></div>
                    <div class="col-12"><label class="form-label">Admin Note</label><textarea name="admin_note" class="form-control" rows="2" maxlength="3000">{{ old('admin_note', $business->subscription_admin_note) }}</textarea></div>
                    <div class="col-12"><button class="btn btn-outline-primary w-100">Save Plan Review</button></div>
                </form>
            </div>
        @endif
        <div class="tf-card p-4"><h2 class="h5 mb-3">Review history</h2><div class="d-grid gap-3">@forelse($business->approvalLogs->take(5) as $log)<div class="border-start border-3 border-primary ps-3"><strong class="d-block">{{ $log->new_status }}</strong><small class="tf-muted d-block"><x-date-time :value="$log->changed_at" /></small>@if($log->note)<small class="d-block mt-1">{{ $log->note }}</small>@endif</div>@empty<p class="tf-muted mb-0">No prior decisions have been recorded.</p>@endforelse</div></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const review = document.querySelector('[data-registration-review-plan]');
    if (!review || review.dataset.ready === '1') return;
    review.dataset.ready = '1';

    const plan = review.querySelector('[data-registration-review-plan-select]');
    const cycle = review.querySelector('[data-registration-review-cycle]');
    const amount = review.querySelector('[data-registration-review-amount]');
    const trialDays = review.querySelector('[data-registration-review-trial-days]');

    const refresh = function () {
        const option = plan.options[plan.selectedIndex];
        if (!option || !option.value) return;

        const price = cycle.value === 'Yearly' ? option.dataset.yearly : option.dataset.monthly;
        amount.value = new Intl.NumberFormat().format(Number(price || 0));
        if (option.dataset.trialDays) trialDays.value = option.dataset.trialDays;
    };

    plan.addEventListener('change', refresh);
    cycle.addEventListener('change', refresh);
});
</script>
@endpush
