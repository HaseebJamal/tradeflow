@extends('layouts.dashboard')

@section('page-title', 'Subscription')
@section('page-subtitle', 'Review your plan, usage, and upgrade options')

@section('content')
    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @php
        $selectedPlanId = (int) request('plan');
        $selectedBillingCycle = in_array(request('billing_cycle'), ['Monthly', 'Yearly'], true) ? request('billing_cycle') : '';
        $permissions = app(\App\Services\CompanyPermissionService::class);
        $canViewHistory = auth()->user()?->role === 'business_owner' || $permissions->allowsUser(auth()->user(), 'subscriptions.view_history', $business);
        $isLiveSubscription = in_array($subscription?->status, ['Trial', 'Active', 'Expiring'], true);
        $expiry = $subscription?->status === 'Trial' ? $subscription?->trial_end_at : $subscription?->ends_at;
        $daysRemaining = $expiry ? max(0, now()->startOfDay()->diffInDays($expiry->copy()->startOfDay(), false)) : null;
        $canSubscriptionAction = fn(string $key) => auth()->user()?->role === 'business_owner' || $permissions->allowsUser(auth()->user(), 'subscriptions.manage', $business) || $permissions->allowsUser(auth()->user(), $key, $business);
    @endphp

    <div class="tf-card p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <div class="tf-muted small">Current Plan</div>
                <h2 class="h4 mb-1">{{ $subscription?->plan?->name ?? 'No active plan' }}</h2><span
                    class="tf-badge {{ $isLiveSubscription ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $subscription?->status ?? 'Not assigned' }}</span>
            </div>
            <div class="text-lg-end">
                @if($daysRemaining !== null)
                    <div class="tf-muted small">Days Remaining</div><strong>{{ $daysRemaining }}
                day{{ $daysRemaining === 1 ? '' : 's' }}</strong>@endif
                @if($subscription)
                    <div class="mt-2"><button class="btn btn-tf-primary btn-sm" type="button" data-bs-toggle="modal"
                data-bs-target="#manageSubscriptionModal">Manage Subscription</button></div>@endif
            </div>
        </div>
        <div class="row g-3">
            <div class="col-sm-6 col-lg-3">
                <div class="border rounded p-3 h-100"><small class="tf-muted d-block">Billing
                        Cycle</small><strong>{{ $subscription?->billing_cycle ?? '-' }}</strong></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="border rounded p-3 h-100"><small class="tf-muted d-block">Start
                        Date</small><strong>{{ $subscription?->starts_at?->format('d M, Y') ?? '-' }}</strong></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="border rounded p-3 h-100"><small class="tf-muted d-block">Trial /
                        Expiry</small><strong>{{ $expiry?->format('d M, Y') ?? '-' }}</strong></div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="border rounded p-3 h-100"><small class="tf-muted d-block">Payment
                        Status</small><strong>{{ $subscription?->payment_status ?? 'Pending' }}</strong></div>
            </div>
            @if($subscription?->cancellation_scheduled_at)
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded p-3 h-100"><small class="tf-muted d-block">Scheduled
                            Cancellation</small><strong>{{ $subscription->cancellation_scheduled_at->format('d M, Y') }}</strong>
                    </div>
            </div>@endif
            @if($requests->where('status', 'Pending')->isNotEmpty())
                <div class="col-sm-6 col-lg-3">
                    <div class="border rounded p-3 h-100"><small class="tf-muted d-block">Pending
                            Changes</small><strong>{{ $requests->where('status', 'Pending')->count() }}</strong></div>
            </div>@endif
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">Available Plans</h2>
            <p class="tf-muted mb-0">Select a plan, billing cycle, and payment method to submit a request.</p>
        </div>
    </div>
    <div class="row g-4">
        @foreach($plans as $plan)
            @php
                $isCurrentPlan = $subscription?->subscription_plan_id === $plan->id;
                $relation = !$subscription?->plan
                    ? 'Subscription'
                    : ($isCurrentPlan && in_array($subscription->status, ['Expired', 'Cancelled'], true)
                        ? 'Renew'
                        : ($plan->priceFor('Monthly') > $subscription->plan->priceFor('Monthly') ? 'Upgrade' : 'Downgrade'));
                $actionPermission = 'subscriptions.' . strtolower($relation);
                if ($relation === 'Subscription')
                    $actionPermission = 'subscriptions.request';
                $canRequest = $canSubscriptionAction($actionPermission);
            @endphp
            <div class="col-md-6 col-xl-4">
                <article
                    class="tf-card p-4 h-100 d-flex flex-column {{ $plan->is_recommended || $plan->id === $selectedPlanId ? 'border-primary' : '' }}">
                    <div class="d-flex justify-content-between gap-2">
                        <h2 class="h5 mb-0">{{ $plan->name }}</h2>@if($plan->is_recommended)<span
                        class="tf-badge tf-badge-info">Recommended</span>@endif
                    </div>
                    <p class="tf-muted small mt-2">{{ $plan->short_description }}</p>
                    <div class="h4 mb-3">Rs {{ number_format($plan->priceFor('Monthly')) }} <small class="tf-muted fs-6">/
                            month</small></div>
                    <ul class="small ps-3 mb-3">
                        <li>{{ number_format($plan->product_limit) }} products</li>
                        <li>{{ number_format($plan->staff_limit) }} staff</li>
                        <li>{{ number_format($plan->order_limit) }} orders</li>
                        <li>{{ (int) $plan->trial_days }} trial days</li>
                        @foreach(array_slice($plan->features ?? [], 0, 3) as $feature)<li>{{ $feature }}</li>@endforeach
                    </ul>
                    @if($isCurrentPlan && $isLiveSubscription)
                        <button class="btn btn-outline-secondary w-100 mt-auto"
                            disabled>{{ $subscription?->status === 'Trial' ? 'Current Trial' : 'Current Plan' }}</button>
                    @elseif(in_array($relation, ['Upgrade', 'Downgrade'], true) && $canRequest)
                        <button type="button"
                            class="btn {{ $relation === 'Upgrade' ? 'btn-tf-primary' : 'btn-outline-primary' }} w-100 mt-auto"
                            data-bs-toggle="modal" data-bs-target="#planChangeRequestModal"
                            data-subscription-plan-request data-request-type="{{ $relation }}"
                            data-plan-id="{{ $plan->id }}" data-plan-name="{{ $plan->name }}"
                            data-monthly-price="{{ $plan->priceFor('Monthly') }}"
                            data-yearly-price="{{ $plan->priceFor('Yearly') }}"
                            data-effective-at="{{ $relation === 'Downgrade' ? ($subscription?->ends_at?->format('d M, Y') ?? now()->format('d M, Y')) : now()->format('d M, Y') }}">
                            {{ $relation }} Plan
                        </button>
                    @elseif($canRequest)
                        <form method="POST" action="{{ route('business.subscription.requests.store') }}" class="mt-auto"
                            data-subscription-request-form novalidate>
                            @csrf
                            <input type="hidden" name="requested_plan_id" value="{{ $plan->id }}">
                            <div class="row g-2">
                                <div class="col-6"><label class="visually-hidden">Billing Cycle</label><select name="billing_cycle"
                                        class="form-select form-select-sm" required data-subscription-billing>
                                        <option value="">Billing cycle</option>
                                        <option value="Monthly" @selected($selectedBillingCycle === 'Monthly')>Monthly</option>
                                        <option value="Yearly" @selected($selectedBillingCycle === 'Yearly')>Yearly</option>
                                    </select></div>
                                <div class="col-6"><label class="visually-hidden">Payment Method</label><select
                                        name="payment_method" class="form-select form-select-sm" required data-subscription-payment>
                                        <option value="">Payment method</option>
                                        <option value="Cash">Cash</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="JazzCash Manual">JazzCash Manual</option>
                                        <option value="Easypaisa Manual">Easypaisa Manual</option>
                                    </select></div>
                            </div>
                            <button class="btn {{ $relation === 'Upgrade' ? 'btn-tf-primary' : 'btn-outline-primary' }} w-100 mt-2"
                                type="submit" disabled data-subscription-submit>{{ $relation }} Plan</button>
                        </form>
                    @else
                        <span class="btn btn-outline-secondary w-100 mt-auto disabled">Permission required</span>
                    @endif
                </article>
            </div>
        @endforeach
    </div>

    @if($subscription)
        <div class="modal fade" id="manageSubscriptionModal" tabindex="-1" aria-labelledby="manageSubscriptionTitle"
            aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('business.subscription.requests.store') }}" class="modal-content"
                    data-manage-subscription-form>@csrf
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="manageSubscriptionTitle">Manage Subscription</h2><button type="button"
                            class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Action</label><select class="form-select"
                                name="request_type" data-manage-action required>
                                <option value="">Select action</option>
                                @if($canSubscriptionAction('subscriptions.change_billing_cycle'))
                                <option value="Billing Cycle Change">Change Billing Cycle</option>@endif
                                @if($canSubscriptionAction('subscriptions.change_payment_method'))
                                <option value="Payment Method Change">Change Payment Method</option>@endif
                                @if($canSubscriptionAction('subscriptions.renew'))
                                <option value="Renewal">Renew Subscription</option>@endif
                                @if($subscription->cancellation_scheduled_at)
                                    @if($canSubscriptionAction('subscriptions.resume_cancellation'))
                                    <option value="Resume Cancellation">Resume Scheduled Cancellation</option>@endif
                                @else
                                    @if($canSubscriptionAction('subscriptions.cancel'))
                                    <option value="Cancellation">Schedule Cancellation</option>@endif
                                @endif
                            </select></div>
                        <input type="hidden" name="requested_plan_id" value="{{ $subscription->subscription_plan_id }}">
                        <div class="mb-3" data-manage-cycle><label class="form-label">Billing Cycle</label><select
                                class="form-select" name="billing_cycle">
                                <option value="Monthly" @selected($subscription->billing_cycle === 'Monthly')>Monthly</option>
                                <option value="Yearly" @selected($subscription->billing_cycle === 'Yearly')>Yearly</option>
                            </select></div>
                        <div class="mb-3" data-manage-payment><label class="form-label">Payment Method</label><select
                                class="form-select" name="payment_method">
                                <option value="">Select method</option>
                                @foreach(['Cash', 'Bank Transfer', 'JazzCash Manual', 'Easypaisa Manual'] as $method)<option
                                    value="{{ $method }}" @selected($subscription->payment_method === $method)>{{ $method }}
                                </option>@endforeach
                            </select></div>
                        <div class="mb-0" data-manage-reason><label class="form-label">Cancellation Reason</label><textarea
                                class="form-control" name="note" rows="3" maxlength="500"></textarea></div>
                        <p class="small tf-muted mb-0 mt-3">Plan changes are submitted separately from the available plan cards
                            and require Super Admin review.</p>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Close</button><button class="btn btn-tf-primary" type="submit">Submit
                            Request</button></div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="planChangeRequestModal" tabindex="-1" aria-labelledby="planChangeRequestTitle"
            aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('business.subscription.requests.store') }}" class="modal-content"
                    data-plan-change-request-form>@csrf
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="planChangeRequestTitle">Plan Change</h2><button type="button"
                            class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="request_type" data-plan-change-type>
                        <input type="hidden" name="requested_plan_id" data-plan-change-plan>
                        <div class="border rounded p-3 mb-3 small">
                            <div class="d-flex justify-content-between gap-3"><span class="tf-muted">Current plan</span><strong>{{ $subscription->plan?->name }}</strong></div>
                            <div class="d-flex justify-content-between gap-3 mt-2"><span class="tf-muted">Target plan</span><strong data-plan-change-target>-</strong></div>
                            <div class="d-flex justify-content-between gap-3 mt-2"><span class="tf-muted">Authoritative price</span><strong data-plan-change-price>-</strong></div>
                            <div class="d-flex justify-content-between gap-3 mt-2"><span class="tf-muted">Effective date</span><strong data-plan-change-effective>-</strong></div>
                        </div>
                        <div class="mb-3"><label class="form-label" for="planChangeBillingCycle">Billing Cycle</label><select
                                class="form-select" id="planChangeBillingCycle" name="billing_cycle" data-plan-change-cycle>
                                <option value="Monthly" @selected($subscription->billing_cycle === 'Monthly')>Monthly</option>
                                <option value="Yearly" @selected($subscription->billing_cycle === 'Yearly')>Yearly</option>
                            </select></div>
                        <div class="mb-0"><label class="form-label" for="planChangePaymentMethod">Payment Method</label><select
                                class="form-select" id="planChangePaymentMethod" name="payment_method" data-plan-change-payment required>
                                <option value="">Select method</option>
                                @foreach(['Cash', 'Bank Transfer', 'JazzCash Manual', 'Easypaisa Manual'] as $method)<option
                                    value="{{ $method }}" @selected($subscription->payment_method === $method)>{{ $method }}</option>@endforeach
                            </select></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Close</button><button class="btn btn-tf-primary" type="submit">Submit
                            Request</button></div>
                </form>
            </div>
        </div>
    @endif

    @if($canViewHistory)
        <div class="tf-card p-0 mt-4">
            <div class="p-3 border-bottom">
                <h2 class="h5 mb-0">Billing History</h2>
            </div>
            <x-table>
                <thead>
                    <tr>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Paid At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($billingHistory as $payment)
                        <tr>
                            <td>Rs {{ number_format($payment->amount, 2) }}</td>
                            <td>{{ $payment->method }}</td>
                            <td>{{ $payment->reference_number ?: '-' }}</td>
                            <td><span class="tf-badge tf-badge-info">{{ $payment->status }}</span></td>
                            <td><x-date-time :value="$payment->paid_at" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center tf-muted py-4">No billing payments recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </x-table>
            <div class="p-3">{{ $billingHistory->links('pagination::bootstrap-5') }}</div>
        </div>

        <div class="tf-card p-0 mt-4">
            <div class="p-3 border-bottom">
                <h2 class="h5 mb-0">Request History</h2>
            </div>
            <x-table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Current / Requested Plan</th>
                        <th>Cycle</th>
                        <th>Payment Method</th>
                        <th>Amount</th>
                        <th>Effective Date</th>
                        <th>Status</th>
                        <th>Requested / Reviewed</th>
                        <th>Admin Note</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $change)
                        <tr>
                            <td>{{ $change->type }}</td>
                            <td>{{ $change->currentPlan?->name ?? '-' }}<span
                                    class="d-block small tf-muted">{{ $change->requestedPlan?->name ?? '-' }}</span></td>
                            <td>{{ $change->billing_cycle }}</td>
                            <td>{{ $change->payment_method ?: '-' }}</td>
                            <td>Rs {{ number_format($change->expected_amount) }}</td>
                            <td>{{ $change->effective_at?->format('d M, Y') ?? '-' }}</td>
                            <td><span class="tf-badge tf-badge-info">{{ $change->status }}</span></td>
                            <td><x-date-time :value="$change->created_at" /><span
                                    class="d-block small tf-muted">{{ $change->reviewed_at ? $change->reviewed_at->format('d M, Y') : 'Not reviewed' }}{{ $change->reviewer?->name ? ' · ' . $change->reviewer->name : '' }}</span>
                            </td>
                            <td>{{ $change->admin_note ?: '-' }}</td>
                            <td class="text-end">
                                @if($change->status === 'Pending' && $permissions->allowsUser(auth()->user(), 'subscriptions.cancel', $business))
                                    <form method="POST" action="{{ route('business.subscription.requests.cancel', $change) }}">@csrf
                                        @method('PATCH')<button class="btn btn-sm btn-outline-danger">Cancel Request</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center tf-muted py-4">No subscription requests yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </x-table>
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
                    window.Swal ? Swal.fire({ icon: 'warning', text: message }) : window.alert(message);
                });
                sync();
            });

            const manageForm = document.querySelector('[data-manage-subscription-form]');
            if (manageForm) {
                const action = manageForm.querySelector('[data-manage-action]');
                const cycle = manageForm.querySelector('[data-manage-cycle]');
                const payment = manageForm.querySelector('[data-manage-payment]');
                const reason = manageForm.querySelector('[data-manage-reason]');
                const syncManageFields = function () {
                    const value = action.value;
                    cycle.classList.toggle('d-none', ['Payment Method Change', 'Cancellation', 'Resume Cancellation'].includes(value));
                    payment.classList.toggle('d-none', ['Billing Cycle Change', 'Cancellation', 'Resume Cancellation'].includes(value));
                    reason.classList.toggle('d-none', value !== 'Cancellation');
                    reason.querySelector('textarea').required = value === 'Cancellation';
                };
                action.addEventListener('change', syncManageFields);
                syncManageFields();
            }

            const planChangeForm = document.querySelector('[data-plan-change-request-form]');
            if (!planChangeForm) return;
            const title = planChangeForm.querySelector('#planChangeRequestTitle');
            const type = planChangeForm.querySelector('[data-plan-change-type]');
            const plan = planChangeForm.querySelector('[data-plan-change-plan]');
            const target = planChangeForm.querySelector('[data-plan-change-target]');
            const price = planChangeForm.querySelector('[data-plan-change-price]');
            const effective = planChangeForm.querySelector('[data-plan-change-effective]');
            const cycle = planChangeForm.querySelector('[data-plan-change-cycle]');
            const payment = planChangeForm.querySelector('[data-plan-change-payment]');
            let selectedCard = null;
            const money = (value) => `Rs ${Number(value || 0).toLocaleString()}`;
            const syncPlanChange = function () {
                if (!selectedCard) return;
                price.textContent = money(selectedCard.dataset[cycle.value === 'Yearly' ? 'yearlyPrice' : 'monthlyPrice']);
            };

            document.querySelectorAll('[data-subscription-plan-request]').forEach(function (button) {
                button.addEventListener('click', function () {
                    selectedCard = button;
                    type.value = button.dataset.requestType;
                    plan.value = button.dataset.planId;
                    title.textContent = `${button.dataset.requestType} to ${button.dataset.planName}`;
                    target.textContent = button.dataset.planName;
                    effective.textContent = button.dataset.effectiveAt;
                    window.syncTradeFlowTomSelect?.(cycle);
                    window.syncTradeFlowTomSelect?.(payment);
                    syncPlanChange();
                });
            });
            cycle.addEventListener('change', syncPlanChange);
            planChangeForm.addEventListener('submit', function (event) {
                if (plan.value && payment.value) return;
                event.preventDefault();
                const missing = !plan.value ? 'Please choose a plan from an available plan card.' : 'Please select a payment method.';
                window.Swal ? Swal.fire({ icon: 'warning', text: missing }) : window.alert(missing);
            });
        });
    </script>
@endpush
