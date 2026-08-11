@extends('layouts.dashboard')

@section('page-title', 'Company Details')
@section('page-subtitle', $company->business_name)
@section('disable-dashboard-autofocus', 'true')

@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

@php
    $currentStatus = strtolower((string) $company->status);
    $statusBadge = match ($currentStatus) {
        'approved' => 'tf-badge-success',
        'pending' => 'tf-badge-warning',
        'suspended', 'rejected', 'archived' => 'tf-badge-danger',
        default => 'tf-badge',
    };
    $nextStatuses = [
        'pending' => ['approved' => 'Approve', 'rejected' => 'Reject'],
        'approved' => ['suspended' => 'Suspend'],
        'suspended' => ['approved' => 'Reactivate'],
    ][$currentStatus] ?? [];
    $registrationPlan = $company->selectedPlan;
    $registrationSnapshot = $company->selected_plan_snapshot ?? [];
    $planName = $registrationSnapshot['plan_name'] ?? $registrationPlan?->name;
    $planCycle = $registrationSnapshot['billing_cycle'] ?? $company->selected_billing_cycle;
    $planPrice = $registrationSnapshot['selected_price'] ?? $company->selected_plan_price;
    $activeSubscription = $company->subscription;
    $subscriptionPlan = $activeSubscription?->plan;
    $planModules = $registrationSnapshot['included_modules'] ?? $registrationPlan?->included_modules ?? [];
    $verificationLabels = [
        'cnic_image' => 'CNIC',
        'business_document' => 'Business Document',
        'shop_image' => 'Shop Image',
    ];
    $verificationDocuments = $company->documents->sortByDesc('id')->keyBy('document_type');
    $missingVerificationLabels = collect($verificationLabels)->filter(fn ($label, $type) => blank($verificationDocuments->get($type)?->file_path));
@endphp

<section class="tf-company-record-header mb-3" aria-label="Company summary">
    <div>
        <span class="tf-eyebrow">Company workspace</span>
        <h2>{{ $company->business_name }}</h2>
        <p>Owned by {{ $company->owner?->name ?: 'Not assigned' }}</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <span class="tf-badge {{ $statusBadge }}">{{ ucfirst($company->status) }}</span>
    </div>
</section>

<div class="tf-company-action-bar mb-4">
    <div class="dropdown">
        <button class="btn btn-tf-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-gear me-1"></i>Manage Company</button>
        <div class="dropdown-menu tf-company-actions-menu">
            <a class="dropdown-item" href="{{ route('admin.companies.edit', $company) }}"><i class="bi bi-building-gear me-2"></i>Company Details</a>
            <a class="dropdown-item" href="{{ route('admin.permissions.index', ['manage_company_id' => $company->id]) }}"><i class="bi bi-shield-lock me-2"></i>Permissions</a>
            <a class="dropdown-item" href="{{ route('admin.companies.document-footer.edit', $company) }}"><i class="bi bi-receipt me-2"></i>Receipt Settings</a>
        </div>
    </div>
    @if($currentStatus === 'approved')
        <form method="POST" action="{{ route('admin.companies.open-dashboard', $company) }}">@csrf<button class="btn btn-outline-primary"><i class="bi bi-person-workspace me-1"></i>Open Business Dashboard</button></form>
    @endif
    @if($pendingDetailRequestCount)
        <a class="btn btn-outline-primary" href="{{ route('admin.business-detail-change-requests.index', ['business_id' => $company->id, 'status' => 'Pending']) }}"><i class="bi bi-pencil-square me-1"></i>Review Requests <span class="badge text-bg-primary ms-1">{{ $pendingDetailRequestCount }}</span></a>
    @endif
    <div class="dropdown ms-sm-auto">
        <button class="btn btn-outline-secondary tf-company-more-action" type="button" data-bs-toggle="dropdown" aria-label="More company actions" title="More company actions"><i class="bi bi-three-dots"></i></button>
        <div class="dropdown-menu dropdown-menu-end tf-company-actions-menu">
            <h6 class="dropdown-header">More actions</h6>
            @if($currentStatus === 'approved')
                <form method="POST" action="{{ route('admin.companies.status', $company) }}" data-tf-confirm-message="Suspend {{ $company->business_name }}? Its workspace will no longer be active. This does not archive the company or remove its data." data-tf-confirm-title="Suspend {{ $company->business_name }}?" data-tf-confirm-button="Suspend Company" data-tf-confirm-color="#f59e0b">@csrf @method('PATCH')<input type="hidden" name="status" value="suspended"><button class="dropdown-item text-warning"><i class="bi bi-pause-circle me-2"></i>Suspend</button></form>
            @elseif($currentStatus === 'suspended')
                <form method="POST" action="{{ route('admin.companies.status', $company) }}" data-tf-confirm-message="Reactivate {{ $company->business_name }}? Its workspace access will be restored." data-tf-confirm-title="Reactivate {{ $company->business_name }}?" data-tf-confirm-button="Reactivate Company" data-tf-confirm-color="#2563eb">@csrf @method('PATCH')<input type="hidden" name="status" value="approved"><button class="dropdown-item text-success"><i class="bi bi-play-circle me-2"></i>Reactivate</button></form>
            @endif
            @if($currentStatus !== 'archived')
                <form method="POST" action="{{ route('admin.companies.archive', $company) }}" data-tf-confirm-message="Archive {{ $company->business_name }}? Its data will remain intact, but it will be removed from the active company list." data-tf-confirm-title="Archive {{ $company->business_name }}?" data-tf-confirm-button="Archive Company" data-tf-confirm-color="#f59e0b">@csrf @method('PATCH')<button class="dropdown-item text-warning"><i class="bi bi-box-seam me-2"></i>Archive</button></form>
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" data-tf-company-delete data-company-name="{{ $company->business_name }}">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="bi bi-trash3 me-2"></i>Permanently Delete Company</button></form>
            @else
                <form method="POST" action="{{ route('admin.companies.restore', $company) }}" data-tf-confirm-message="Restore {{ $company->business_name }} to {{ $company->archived_status ?: 'Pending' }} status?" data-tf-confirm-title="Restore {{ $company->business_name }}?" data-tf-confirm-button="Restore Company" data-tf-confirm-color="#2563eb">@csrf @method('PATCH')<button class="dropdown-item text-success"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore Company</button></form>
            @endif
        </div>
    </div>
</div>

<div class="row g-4 tf-company-details-page">
    <div class="col-lg-8">
        <section class="tf-card p-4 tf-company-overview">
            <h2 class="h5 mb-3">Company Overview</h2>
            <dl class="tf-company-info-grid mb-0">
                @foreach(['Company Name' => $company->business_name, 'Owner' => $company->owner?->name, 'Owner Login Email' => $company->owner?->email, 'Phone' => $company->phone, 'Business Type' => $company->display_business_type, 'City' => $company->city, 'Created At' => $company->created_at?->format('n/j/Y, g:i A')] as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd></div>
                @endforeach
            </dl>
        </section>

        @if(false)
        <section class="tf-card p-4 mt-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div><h2 class="h5 mb-1">Selected Subscription Plan</h2><p class="tf-muted mb-0">Current subscription information for this company.</p></div>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.subscriptions', ['manage_business_id' => $company->id]) }}">Manage Subscription</a>
            </div>
            @if($subscriptionPlan)
                <dl class="tf-company-info-grid tf-company-info-grid--subscription mb-0">
                    <div><dt>Plan Name</dt><dd>{{ $subscriptionPlan->name }}</dd></div>
                    <div><dt>Billing Cycle</dt><dd>{{ $activeSubscription->billing_cycle ?: 'Not recorded' }}</dd></div>
                    <div><dt>Price</dt><dd>@if(!is_null($activeSubscription->amount))Rs {{ number_format((int) $activeSubscription->amount) }}@else Not recorded @endif</dd></div>
                    <div><dt>Subscription Status</dt><dd><span class="tf-badge {{ strtolower((string) $activeSubscription->status) === 'active' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $activeSubscription->status ?: 'Not recorded' }}</span></dd></div>
                    <div><dt>Start Date</dt><dd>{{ $activeSubscription->starts_at?->format('n/j/Y') ?: 'Not recorded' }}</dd></div>
                    <div><dt>Expiry / Renewal Date</dt><dd>{{ $activeSubscription->ends_at?->format('n/j/Y') ?: 'Not recorded' }}</dd></div>
                    <div><dt>Trial</dt><dd>{{ $activeSubscription->trial_end_at ? 'Until '.$activeSubscription->trial_end_at->format('n/j/Y') : 'No active trial' }}</dd></div>
                </dl>
            @else
                <div class="tf-company-empty-state"><i class="bi bi-credit-card-2-front"></i><div><strong>No active subscription plan.</strong><span>@if($planName) A {{ $planName }} {{ $planCycle ? '('.$planCycle.')' : '' }} registration selection is {{ strtolower($company->subscription_request_status ?: 'pending review') }}.@else Assign a plan to activate this company’s subscription.@endif</span></div></div>
            @endif

            @if($currentStatus === 'pending')
                <details class="tf-company-review-plan mt-3">
                    <summary>Review submitted plan selection</summary>
                    <form method="POST" action="{{ route('admin.companies.registration-plan.update', $company) }}" data-registration-plan-review class="mt-3">
                        @csrf @method('PATCH')
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Review Action</label><select name="plan_action" class="form-select" data-registration-plan-action><option value="keep">Keep Selected Plan</option><option value="change">Change Plan</option><option value="require_selection">Require Plan Selection</option></select></div>
                            <div class="col-md-4"><label class="form-label">Plan</label><select name="selected_plan_id" class="form-select" data-registration-plan-select>@foreach($adminPlans as $plan)<option value="{{ $plan->id }}" data-monthly="{{ $plan->priceFor('Monthly') }}" data-yearly="{{ $plan->priceFor('Yearly') }}" data-trial-days="{{ $plan->trial_days }}" @selected(old('selected_plan_id', $company->selected_plan_id ?? $company->subscription?->subscription_plan_id) == $plan->id)>{{ $plan->name }}{{ $plan->is_public ? '' : ' (Private)' }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">Billing Cycle</label><select name="billing_cycle" class="form-select" data-registration-billing-cycle><option value="Monthly" @selected(old('billing_cycle', $company->selected_billing_cycle ?? $company->subscription?->billing_cycle) === 'Monthly')>Monthly</option><option value="Yearly" @selected(old('billing_cycle', $company->selected_billing_cycle ?? $company->subscription?->billing_cycle) === 'Yearly')>Yearly</option></select></div>
                            <div class="col-md-4"><label class="form-label">Confirmed Amount</label><div class="input-group"><span class="input-group-text">Rs</span><input class="form-control" value="{{ number_format((int) $planPrice) }}" readonly data-registration-plan-amount></div></div>
                            <div class="col-md-4"><label class="form-label">Trial Days</label><input type="number" name="requested_trial_days" class="form-control" min="0" max="365" step="1" value="{{ old('requested_trial_days', $company->requested_trial_days ?? $registrationSnapshot['trial_days'] ?? 0) }}" data-registration-trial-days></div>
                            <div class="col-md-4 d-flex align-items-end"><div class="form-check mb-2"><input type="hidden" name="trial_eligible" value="0"><input class="form-check-input" type="checkbox" name="trial_eligible" value="1" id="trialEligible" @checked(old('trial_eligible', $company->trial_eligible))><label class="form-check-label" for="trialEligible">Approve free trial</label></div></div>
                            <div class="col-md-6"><label class="form-label">Change Reason</label><textarea name="change_reason" class="form-control" rows="3" maxlength="2000" placeholder="Explain the plan or billing change">{{ old('change_reason') }}</textarea></div>
                            <div class="col-md-6"><label class="form-label">Admin Note</label><textarea name="admin_note" class="form-control" rows="3" maxlength="3000" placeholder="Optional note for the business owner">{{ old('admin_note', $company->subscription_admin_note) }}</textarea></div>
                        </div>
                        <button class="btn btn-tf-primary mt-3" type="submit">Save Plan Review</button>
                    </form>
                </details>
            @endif
        </section>

        @endif
        <section class="tf-card p-4 mt-4">
            <h2 class="h5 mb-3">Staff Accounts</h2>
            <div class="tf-company-detail-table"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Role</th><th>Status</th></tr></thead><tbody>
                @forelse($company->users->where('id', '!=', $company->owner_id) as $staff)
                    <tr><td>{{ $staff->name }}</td><td>{{ str($staff->role)->replace('_', ' ')->title() }}</td><td><span class="tf-badge {{ strtolower((string) $staff->status) === 'active' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ ucfirst($staff->status) }}</span></td></tr>
                @empty
                    <tr><td colspan="3" class="text-center tf-muted py-4">No staff accounts found.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>

        <section class="tf-card p-4 mt-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-1">Recent Activity and Login History</h2><p class="tf-muted small mb-0">Latest activity for this company.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.live-activity', ['business_id' => $company->id]) }}">View All Activity</a></div>
            <div class="tf-company-detail-table"><table class="table align-middle mb-0"><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Module</th></tr></thead><tbody>
                @forelse($loginHistory as $entry)
                    <tr><td><x-date-time :value="$entry->occurred_at" /></td><td>{{ $entry->actor?->name ?? 'System' }}</td><td><x-activity-label :activity="$entry" /></td><td><x-activity-label :activity="$entry" field="module" /></td></tr>
                @empty
                    <tr><td colspan="4" class="text-center tf-muted py-4">No activity recorded.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>

        <section class="tf-card p-4 mt-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-1">Approval History</h2><p class="tf-muted small mb-0">Most recent company decisions.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.approvals.history', ['company_id' => $company->id, 'date_from' => $company->created_at?->toDateString()]) }}">View Full Approval History</a></div>
            <div class="tf-company-detail-table"><table class="table align-middle mb-0"><thead><tr><th>From</th><th>To</th><th>Note</th><th>Changed By</th><th>When</th></tr></thead><tbody>
                @forelse($company->approvalLogs as $history)
                    <tr><td>{{ ucfirst($history->old_status ?: '—') }}</td><td><span class="tf-badge {{ strtolower((string) $history->new_status) === 'approved' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ ucfirst($history->new_status) }}</span></td><td class="tf-company-note">{{ $history->note ?: '—' }}</td><td>{{ $history->changedBy?->name ?: 'System / Public' }}</td><td><x-date-time :value="$history->changed_at" /></td></tr>
                @empty
                    <tr><td colspan="5" class="text-center tf-muted py-4">No approval changes yet.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </div>

    <aside class="col-lg-4">
        <section class="tf-card p-4" id="approval-control">
            <h2 class="h5 mb-2">Approval Control</h2>
            <div class="d-flex align-items-center gap-2 mb-3"><span class="tf-muted">Current status</span><span class="tf-badge {{ $statusBadge }}">{{ ucfirst($company->status) }}</span></div>
            @if($nextStatuses)
                <form method="POST" action="{{ route('admin.companies.status', $company) }}" data-company-status-form data-company-name="{{ $company->business_name }}" data-tf-confirm-message="Confirm this status change for {{ $company->business_name }}." data-tf-confirm-title="Confirm status change" data-tf-confirm-button="Confirm change" data-tf-confirm-color="#2563eb">@csrf @method('PATCH')
                    <label class="form-label" for="company-status">Change Status</label><select id="company-status" name="status" class="form-select mb-3" data-company-status-select>@foreach($nextStatuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    <label class="form-label" for="company-status-note">Admin Note</label><textarea id="company-status-note" name="admin_note" class="form-control mb-3" rows="3" placeholder="Reason or follow-up note"></textarea>
                    <button class="btn btn-tf-primary w-100">Save Status</button>
                    @if($currentStatus === 'pending')<button class="btn btn-outline-secondary w-100 mt-2" name="decision" value="request_changes" type="submit" data-company-request-changes>Request Changes</button>@endif
                </form>
            @else
                <p class="tf-muted mb-0">No status transition is available. Use the More Actions menu to restore an archived company.</p>
            @endif
        </section>

        <section class="tf-card p-4 mt-4">
            <h2 class="h5 mb-3">Business Owner</h2>
            <dl class="tf-company-owner-list mb-0"><div><dt>Name</dt><dd>{{ $company->owner?->name ?: '—' }}</dd></div><div><dt>Email</dt><dd>{{ $company->owner?->email ?: 'Not provided' }}</dd></div><div><dt>Phone</dt><dd>{{ $company->owner?->phone ?: 'Not provided' }}</dd></div><div><dt>Role</dt><dd>{{ str($company->owner?->role ?: 'business owner')->replace('_', ' ')->title() }}</dd></div><div><dt>Account Status</dt><dd><span class="tf-badge {{ strtolower((string) $company->owner?->status) === 'active' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ ucfirst($company->owner?->status ?: 'Unknown') }}</span></dd></div></dl>
        </section>

    </aside>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const review = document.querySelector('[data-registration-plan-review]');
    if (review) {
        const planSelect = review.querySelector('[data-registration-plan-select]');
        const billingCycle = review.querySelector('[data-registration-billing-cycle]');
        const amount = review.querySelector('[data-registration-plan-amount]');
        const trialDays = review.querySelector('[data-registration-trial-days]');
        const refreshPlanSummary = function () {
            const option = planSelect.options[planSelect.selectedIndex];
            if (!option) return;
            amount.value = new Intl.NumberFormat().format(Number((billingCycle.value === 'Yearly' ? option.dataset.yearly : option.dataset.monthly) || 0));
            trialDays.value = option.dataset.trialDays || 0;
        };
        planSelect.addEventListener('change', refreshPlanSummary);
        billingCycle.addEventListener('change', refreshPlanSummary);
    }

    const statusForm = document.querySelector('[data-company-status-form]');
    const statusSelect = statusForm?.querySelector('[data-company-status-select]');
    const updateStatusConfirmation = function (requestChanges) {
        if (!statusForm) return;
        const action = requestChanges ? 'request changes' : statusSelect.options[statusSelect.selectedIndex]?.textContent.trim().toLowerCase();
        statusForm.dataset.tfConfirmTitle = `${action ? action.charAt(0).toUpperCase() + action.slice(1) : 'Confirm'} ${statusForm.dataset.companyName}?`;
        statusForm.dataset.tfConfirmMessage = `Confirm ${action || 'this status change'} for ${statusForm.dataset.companyName}.`;
        statusForm.dataset.tfConfirmButton = action ? `Confirm ${action}` : 'Confirm change';
    };
    if (statusForm && statusSelect) {
        updateStatusConfirmation(false);
        statusSelect.addEventListener('change', () => updateStatusConfirmation(false));
        statusForm.querySelector('[data-company-request-changes]')?.addEventListener('click', () => updateStatusConfirmation(true));
    }
});
</script>
@endpush
