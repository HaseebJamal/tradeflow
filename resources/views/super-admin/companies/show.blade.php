@extends('layouts.dashboard')

@section('page-title', 'Company Details')
@section('page-subtitle', $company->business_name)

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="d-flex flex-wrap gap-2 mb-4">
    <a class="btn btn-outline-primary" href="{{ route('admin.companies.edit', $company) }}"><i class="bi bi-pencil me-1"></i>Edit Company</a>
    <a class="btn btn-outline-primary" href="{{ route('admin.permissions.index', ['manage_company_id' => $company->id]) }}"><i class="bi bi-shield-lock me-1"></i>Manage Permissions</a>
    <a class="btn btn-outline-primary" href="{{ route('admin.business-detail-change-requests.index', ['business_id' => $company->id]) }}"><i class="bi bi-pencil-square me-1"></i>Review Detail Requests</a>
    <a class="btn btn-outline-primary" href="{{ route('admin.subscriptions', ['manage_business_id' => $company->id]) }}"><i class="bi bi-credit-card me-1"></i>Manage Subscription</a>
    <a class="btn btn-outline-primary" href="{{ route('admin.companies.document-footer.edit', $company) }}"><i class="bi bi-receipt me-1"></i>Receipt Footer</a>
    @if(strtolower((string) $company->status) === 'approved')
        <form method="POST" action="{{ route('admin.companies.open-dashboard', $company) }}">@csrf<button class="btn btn-outline-warning"><i class="bi bi-person-workspace me-1"></i>Open Business Dashboard</button></form>
    @endif
    @if(strtolower($company->status) !== 'archived')
        <form method="POST" action="{{ route('admin.companies.archive', $company) }}">@csrf @method('PATCH')<button class="btn btn-outline-warning">Archive</button></form>
        <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" data-tf-company-delete data-company-name="{{ $company->business_name }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">Permanently Delete Company</button></form>
    @else
        <form method="POST" action="{{ route('admin.companies.restore', $company) }}">@csrf @method('PATCH')<button class="btn btn-outline-success">Restore</button></form>
    @endif
</div>

@php
    $currentStatus = strtolower((string) $company->status);
    $nextStatuses = [
        'pending' => ['approved' => 'Approve', 'rejected' => 'Reject'],
        'approved' => ['suspended' => 'Suspend'],
        'suspended' => ['approved' => 'Activate'],
    ][$currentStatus] ?? [];
    $registrationPlan = $company->selectedPlan;
    $registrationSnapshot = $company->selected_plan_snapshot ?? [];
    $planName = $registrationSnapshot['plan_name'] ?? $registrationPlan?->name;
    $planCycle = $registrationSnapshot['billing_cycle'] ?? $company->selected_billing_cycle;
    $planPrice = $registrationSnapshot['selected_price'] ?? $company->selected_plan_price;
    $planStatus = $registrationSnapshot['plan_status'] ?? $registrationPlan?->status;
    $planModules = $registrationSnapshot['included_modules'] ?? $registrationPlan?->included_modules ?? [];
    $planSelectionSource = $company->plan_selection_source === 'pricing' ? 'Landing Pricing' : 'Registration Form';
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="tf-card p-4">
            <h2 class="h5 mb-3">Company Overview</h2>
            <div class="row g-3">
                @foreach(['Company Name' => $company->business_name, 'Owner' => $company->owner?->name, 'Owner Login Email' => $company->owner?->email ?: 'Not provided', 'Phone' => $company->phone, 'Business Type' => $company->display_business_type, 'Category' => $company->category, 'City' => $company->city] as $label => $value)
                    <div class="col-md-6"><div class="border rounded p-3"><small class="tf-muted">{{ $label }}</small><strong class="d-block">{{ $value ?: '—' }}</strong></div></div>
                @endforeach
                <div class="col-md-6"><div class="border rounded p-3"><small class="tf-muted">Created At</small><strong class="d-block"><x-date-time :value="$company->created_at" /></strong></div></div>
            </div>
        </div>

        <div class="tf-card p-4 mt-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Selected Subscription Plan</h2>
                    <p class="tf-muted mb-0">Plan information submitted with this registration.</p>
                </div>
                <span class="badge text-bg-{{ $company->subscription_request_status === 'Activated' ? 'success' : ($company->subscription_request_status === 'Rejected' ? 'danger' : 'secondary') }}">{{ $company->subscription_request_status ?? 'Pending Review' }}</span>
            </div>

            @if($planName)
                <div class="row g-3">
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted">Plan</small><strong class="d-block">{{ $planName }}</strong>@if($registrationPlan?->is_recommended)<span class="badge text-bg-primary mt-1">Recommended</span>@endif</div></div>
                    <div class="col-md-2"><div class="border rounded p-3 h-100"><small class="tf-muted">Billing Cycle</small><strong class="d-block">{{ $planCycle ?: 'Not recorded' }}</strong></div></div>
                    <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="tf-muted">Selected Price</small><strong class="d-block">Rs {{ number_format((int) $planPrice) }}</strong></div></div>
                    <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="tf-muted">Plan Status</small><strong class="d-block">{{ $planStatus ?: 'Not recorded' }}</strong></div></div>
                    <div class="col-md-3"><div class="border rounded p-3 h-100"><small class="tf-muted">Selection Source</small><strong class="d-block">{{ $planSelectionSource }}</strong></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted">Trial</small><strong class="d-block">{{ $company->trial_eligible ? ((int) ($company->requested_trial_days ?? $registrationSnapshot['trial_days'] ?? 0)).'-day trial' : 'Payment required' }}</strong></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted">Limits</small><strong class="d-block">{{ number_format((int) ($registrationSnapshot['product_limit'] ?? $registrationPlan?->product_limit ?? 0)) }} products, {{ number_format((int) ($registrationSnapshot['staff_limit'] ?? $registrationPlan?->staff_limit ?? 0)) }} staff</strong><small class="tf-muted">{{ number_format((int) ($registrationSnapshot['order_limit'] ?? $registrationPlan?->order_limit ?? 0)) }} orders</small></div></div>
                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted">Selected At</small><strong class="d-block"><x-date-time :value="$company->plan_selected_at" /></strong></div></div>
                    @if(count($planModules))
                        <div class="col-12"><div class="border rounded p-3"><small class="tf-muted d-block mb-2">Included Modules</small><div class="d-flex flex-wrap gap-2">@foreach($planModules as $module)<span class="badge text-bg-light border text-dark">{{ $module }}</span>@endforeach</div></div></div>
                    @endif
                </div>
            @else
                <div class="alert alert-warning mb-0">No plan selection recorded. Assign and confirm a plan before approving this registration.</div>
            @endif

            @if($currentStatus === 'pending')
                <hr class="my-4">
                <h3 class="h6 mb-3">Review Plan Selection</h3>
                <form method="POST" action="{{ route('admin.companies.registration-plan.update', $company) }}" data-registration-plan-review>
                    @csrf @method('PATCH')
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Review Action</label><select name="plan_action" class="form-select" data-registration-plan-action><option value="keep">Keep Selected Plan</option><option value="change">Change Plan</option><option value="require_selection">Require Plan Selection</option></select></div>
                        <div class="col-md-4"><label class="form-label">Plan</label><select name="selected_plan_id" class="form-select" data-registration-plan-select>@foreach($adminPlans as $plan)<option value="{{ $plan->id }}" data-monthly="{{ $plan->priceFor('Monthly') }}" data-yearly="{{ $plan->priceFor('Yearly') }}" data-trial-days="{{ $plan->trial_days }}" @selected(old('selected_plan_id', $company->selected_plan_id ?? $company->subscription?->subscription_plan_id) == $plan->id)>{{ $plan->name }}{{ $plan->is_public ? '' : ' (Private)' }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">Billing Cycle</label><select name="billing_cycle" class="form-select" data-registration-billing-cycle><option value="Monthly" @selected(old('billing_cycle', $company->selected_billing_cycle ?? $company->subscription?->billing_cycle) === 'Monthly')>Monthly</option><option value="Yearly" @selected(old('billing_cycle', $company->selected_billing_cycle ?? $company->subscription?->billing_cycle) === 'Yearly')>Yearly</option></select></div>
                        <div class="col-md-4"><label class="form-label">Confirmed Amount</label><div class="input-group"><span class="input-group-text">Rs</span><input class="form-control" value="{{ number_format((int) $planPrice) }}" readonly data-registration-plan-amount></div></div>
                        <div class="col-md-4"><label class="form-label">Trial Days</label><input type="number" name="requested_trial_days" class="form-control" min="0" max="365" step="1" value="{{ old('requested_trial_days', $company->requested_trial_days ?? $registrationSnapshot['trial_days'] ?? 0) }}" data-registration-trial-days></div>
                        <div class="col-md-4 d-flex align-items-end"><div class="form-check mb-2"><input type="hidden" name="trial_eligible" value="0"><input class="form-check-input" type="checkbox" name="trial_eligible" value="1" id="trialEligible" @checked(old('trial_eligible', $company->trial_eligible))><label class="form-check-label" for="trialEligible">Approve free trial</label></div></div>
                        <div class="col-md-6"><label class="form-label">Change Reason <span class="tf-muted">(required when changing plan or billing)</span></label><textarea name="change_reason" class="form-control" rows="3" maxlength="2000" placeholder="Explain the plan or billing change">{{ old('change_reason') }}</textarea></div>
                        <div class="col-md-6"><label class="form-label">Admin Note</label><textarea name="admin_note" class="form-control" rows="3" maxlength="3000" placeholder="Optional note for the business owner">{{ old('admin_note', $company->subscription_admin_note) }}</textarea></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-tf-primary" type="submit">Save Plan Review</button><a class="btn btn-outline-primary" href="{{ route('admin.subscriptions', ['manage_business_id' => $company->id]) }}">Manage Subscription</a></div>
                </form>
            @endif
        </div>

        <div class="tf-card p-4 mt-4">
            <h2 class="h5">Staff Accounts</h2>
            <x-table><thead><tr><th>Name</th><th>Role</th><th>Status</th></tr></thead><tbody>
                @forelse($company->users->where('id', '!=', $company->owner_id) as $staff)
                    <tr><td>{{ $staff->name }}</td><td>{{ str_replace('_', ' ', $staff->role) }}</td><td>{{ ucfirst($staff->status) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center tf-muted py-4">No staff accounts found.</td></tr>
                @endforelse
            </tbody></x-table>
        </div>

        <div class="tf-card p-4 mt-4">
            <h2 class="h5">Recent Activity and Login History</h2>
            <x-table><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Module</th></tr></thead><tbody>
                @forelse($loginHistory as $entry)
                    <tr><td><x-date-time :value="$entry->occurred_at" /></td><td>{{ $entry->actor?->name ?? 'System' }}</td><td><x-activity-label :activity="$entry" /></td><td><x-activity-label :activity="$entry" field="module" /></td></tr>
                @empty
                    <tr><td colspan="4" class="text-center tf-muted py-4">No activity recorded.</td></tr>
                @endforelse
            </tbody></x-table>
            <div class="d-flex flex-wrap gap-2 mt-3"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.audit-logs', ['business_id' => $company->id]) }}">View Audit Logs</a><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.live-activity', ['business_id' => $company->id]) }}">View Activity Logs</a><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.notifications.index') }}">View Notifications</a></div>
        </div>

        <div class="tf-card p-4 mt-4">
            <h2 class="h5">Approval History</h2>
            <x-table><thead><tr><th>From</th><th>To</th><th>Note</th><th>Changed By</th><th>When</th></tr></thead><tbody>
                @forelse($company->approvalLogs as $history)
                    <tr><td>{{ ucfirst($history->old_status ?: '—') }}</td><td>{{ ucfirst($history->new_status) }}</td><td>{{ $history->note ?: '—' }}</td><td>{{ $history->changedBy?->name ?: 'System / Public' }}</td><td><x-date-time :value="$history->changed_at" /></td></tr>
                @empty
                    <tr><td colspan="5" class="text-center tf-muted">No approval changes yet.</td></tr>
                @endforelse
            </tbody></x-table>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="tf-card p-4">
            <h2 class="h5">Approval Control</h2>
            <p class="tf-muted">Current status: <strong>{{ $company->status }}</strong></p>
            @if($nextStatuses)
                <form method="POST" action="{{ route('admin.companies.status', $company) }}">@csrf @method('PATCH')
                    <label class="form-label">Select Status</label><select name="status" class="form-select mb-3">@foreach($nextStatuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    <label class="form-label">Admin Note</label><textarea name="admin_note" class="form-control mb-3" rows="3" placeholder="Reason or follow-up note"></textarea>
                    <button class="btn btn-tf-primary w-100">Save Status</button>
                    @if($currentStatus === 'pending')<button class="btn btn-outline-secondary w-100 mt-2" name="decision" value="request_changes">Request Changes</button>@endif
                </form>
            @else
                <p class="tf-muted mb-0">No status transition is available. Use Restore for archived companies.</p>
            @endif
        </div>
        <div class="tf-card p-4 mt-4"><h2 class="h5">Business Owner</h2><p class="mb-1"><strong>{{ $company->owner?->name ?? '—' }}</strong></p><p class="tf-muted small mb-0">Owner login credentials are private and managed only by the owner.</p></div>
        <div class="tf-card p-4 mt-4"><h2 class="h5">Verification Documents</h2>@forelse($company->documents as $document)<a class="d-block border rounded p-2 mb-2" href="{{ asset('storage/'.$document->file_path) }}" target="_blank" rel="noopener">{{ str_replace('_', ' ', ucfirst($document->document_type)) }}</a>@empty<p class="tf-muted mb-0">No documents uploaded.</p>@endforelse</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const review = document.querySelector('[data-registration-plan-review]');
    if (!review) return;

    const planSelect = review.querySelector('[data-registration-plan-select]');
    const billingCycle = review.querySelector('[data-registration-billing-cycle]');
    const amount = review.querySelector('[data-registration-plan-amount]');
    const trialDays = review.querySelector('[data-registration-trial-days]');

    const refreshPlanSummary = function () {
        const option = planSelect.options[planSelect.selectedIndex];
        if (!option) return;
        const price = billingCycle.value === 'Yearly' ? option.dataset.yearly : option.dataset.monthly;
        amount.value = new Intl.NumberFormat().format(Number(price || 0));
        trialDays.value = option.dataset.trialDays || 0;
    };

    planSelect.addEventListener('change', refreshPlanSummary);
    billingCycle.addEventListener('change', refreshPlanSummary);
});
</script>
@endpush
