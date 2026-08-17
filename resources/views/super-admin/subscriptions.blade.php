@extends('layouts.dashboard')

@section('title', 'Trial & Access')
@section('page-title', 'Trial & Access')
@section('page-subtitle', 'Manage trial periods, business access, and paid access renewals')

@section('content')
<div class="tf-subscriptions-page">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="mb-1">Default trial period</h5>
                <p class="text-muted mb-0">New businesses receive this trial period automatically.</p>
            </div>
            <form method="POST" action="{{ route('admin.subscriptions.trial-settings.update') }}" class="d-flex align-items-end gap-2">
                @csrf
                @method('PUT')
                <div>
                    <label class="form-label mb-1" for="default-trial-days">Trial days</label>
                    <input id="default-trial-days" class="form-control" type="number" name="trial_days" min="1" max="365" value="{{ old('trial_days', $settings->trial_days) }}" required>
                </div>
                <button class="btn btn-primary" type="submit">Save</button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => 'Active trials', 'value' => $stats['trial'], 'icon' => 'bi-hourglass-split', 'tone' => 'primary'],
            ['label' => 'Expiring soon', 'value' => $stats['expiring'], 'icon' => 'bi-calendar-event', 'tone' => 'warning'],
            ['label' => 'Expired', 'value' => $stats['expired'], 'icon' => 'bi-x-circle', 'tone' => 'danger'],
            ['label' => 'Paid access', 'value' => $stats['paid'], 'icon' => 'bi-credit-card', 'tone' => 'success'],
            ['label' => 'Restricted', 'value' => $stats['restricted'], 'icon' => 'bi-shield-lock', 'tone' => 'secondary'],
        ] as $stat)
            <div class="col-12 col-sm-6 col-xl">
                <div class="card h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted">{{ $stat['label'] }}</div>
                            <div class="fs-3 fw-bold">{{ $stat['value'] }}</div>
                        </div>
                        <i class="bi {{ $stat['icon'] }} text-{{ $stat['tone'] }} fs-4"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card tf-access-table-card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label" for="access-search">Search</label>
                    <input id="access-search" class="form-control" type="search" name="search" value="{{ $filters['search'] }}" placeholder="Business or owner">
                </div>
                <div class="col-sm-3 col-lg-2">
                    <label class="form-label" for="access-status">Status</label>
                    <select id="access-status" class="form-select" name="status" data-tom-select-inline="true">
                        <option value="">All statuses</option>
                        @foreach(['trial_active' => 'Trial active', 'trial_expiring' => 'Trial expiring', 'paid_scheduled' => 'Paid scheduled', 'paid_active' => 'Paid active', 'paid_expiring' => 'Paid expiring', 'restricted' => 'Restricted'] as $value => $label)
                            <option value="{{ $value }}" @selected($filters['access_status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3 col-lg-2">
                    <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
                </div>
            </form>
        </div>

        <div class="tf-dropdown-safe-scroll">
            <table class="table table-hover align-middle mb-0 tf-access-table tf-has-actions-column">
                <thead>
                    <tr>
                        <th>Business</th>
                        <th>Owner</th>
                        <th>Status</th>
                        <th>Starts</th>
                        <th>Paid end</th>
                        <th class="text-end">Remaining days</th>
                        <th class="text-end">Extended days </th>
                        <th>Effective end</th>
                        <th class="text-end tf-table-action-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($businesses as $business)
                        @php
                            $access = $accessStates[$business->id] ?? [];
                            $subscription = $access['subscription'] ?? null;
                            $tone = in_array($access['kind'] ?? '', ['trial_active', 'paid_active'], true) ? 'success' : (($access['kind'] ?? '') === 'paid_scheduled' ? 'primary' : (in_array($access['kind'] ?? '', ['trial_expiring', 'paid_expiring'], true) ? 'warning' : 'danger'));
                        @endphp
                        <tr data-access-business="{{ $business->id }}" tabindex="-1">
                            <td>
                                <div class="fw-semibold">{{ $business->business_name }}</div>
                                <small class="text-muted">{{ $business->business_type ?: 'Business' }}</small>
                            </td>
                            <td>
                                <div>{{ $business->owner?->name ?: '—' }}</div>
                                <small class="text-muted">{{ $business->owner?->email ?: '' }}</small>
                            </td>
                            <td><span class="badge text-bg-{{ $tone }}">{{ $access['label'] ?? 'Restricted' }}</span></td>
                            <td>{{ !empty($access['start_date']) ? $access['start_date']->format('n/j/Y') : '—' }}</td>
                            <td>{{ !empty($access['original_paid_access_end']) ? $access['original_paid_access_end']->format('n/j/Y') : '—' }}</td>
                            <td class="text-end text-nowrap">{{ $access['paid_remaining_label'] ?? '—' }}</td>
                            <td class="text-end text-nowrap">@if(($access['extra_access_days'] ?? 0) > 0)<span class="tf-access-extension-badge">{{ $access['extended_days_label'] }}</span>@else—@endif</td>
                            <td>{{ !empty($access['effective_access_end']) ? $access['effective_access_end']->format('n/j/Y') : (!empty($access['end_date']) ? $access['end_date']->format('n/j/Y') : '—') }}</td>
                            <td class="text-end tf-table-action-cell">
                                <div class="btn-group tf-table-action-group">
                                    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#manage-access-{{ $business->id }}">Manage</button>
                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle tf-access-more-button" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-label="More access actions"><i class="bi bi-three-dots"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#access-details-{{ $business->id }}">View access details</button></li>
                                        <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#access-details-{{ $business->id }}">Trial / access history</button></li>
                                        @if(!empty($access['can_reactivate_paid']))
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item text-success" type="button" data-bs-toggle="modal" data-bs-target="#reactivate-paid-access-{{ $business->id }}"><i class="bi bi-arrow-clockwise me-2"></i>Reactivate Access</button></li>
                                            <li><a class="dropdown-item" href="{{ route('admin.payments', ['business_id' => $business->id]) }}"><i class="bi bi-credit-card me-2"></i>Record Payment</a></li>
                                        @elseif(!empty($access['can_restore_restricted']))
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item text-success" type="button" data-bs-toggle="modal" data-bs-target="#restore-restricted-access-{{ $business->id }}"><i class="bi bi-arrow-clockwise me-2"></i>Restore Access</button></li>
                                        @endif
                                        @if(!empty($access['can_end_trial']))
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item text-danger" type="button" data-trial-end-trigger="{{ $business->id }}">End Trial Now</button></li>
                                        @elseif(!empty($access['can_end_paid']))
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item text-danger" type="button" data-bs-toggle="modal" data-bs-target="#end-paid-access-{{ $business->id }}">End Paid Access</button></li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No businesses match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body border-top">{{ $businesses->links() }}</div>
    </div>

    @foreach($businesses as $business)
        @php
            $access = $accessStates[$business->id] ?? [];
            $subscription = $access['subscription'] ?? null;
        @endphp
        <div class="modal fade tf-access-modal tf-access-history-modal" id="access-details-{{ $business->id }}" tabindex="-1" aria-hidden="true" aria-labelledby="access-details-title-{{ $business->id }}">
            <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><div><h5 class="modal-title" id="access-details-title-{{ $business->id }}">{{ $business->business_name }} access</h5><p class="tf-access-history-subtitle mb-0">Lifecycle records are retained when a free trial is converted to paid access.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Current status</dt><dd class="col-sm-7">{{ $access['label'] ?? 'Restricted' }}</dd>
                        @if($subscription?->payment_status === 'Received')
                            <dt class="col-sm-5">Paid access start</dt><dd class="col-sm-7">{{ !empty($access['paid_access_start']) ? $access['paid_access_start']->format('n/j/Y') : '—' }}</dd>
                            <dt class="col-sm-5">Original paid access end</dt><dd class="col-sm-7">{{ !empty($access['original_paid_access_end']) ? $access['original_paid_access_end']->format('n/j/Y') : '—' }}</dd>
                            <dt class="col-sm-5">Paid duration</dt><dd class="col-sm-7">{{ $access['paid_duration_days'] ?? 0 }} days</dd>
                            <dt class="col-sm-5">Extra access</dt><dd class="col-sm-7">+{{ $access['extra_access_days'] ?? 0 }} days</dd>
                            <dt class="col-sm-5">Effective access end</dt><dd class="col-sm-7">{{ !empty($access['effective_access_end']) ? $access['effective_access_end']->format('n/j/Y') : '—' }}</dd>
                            <dt class="col-sm-5">Effective days remaining</dt><dd class="col-sm-7">{{ $access['remaining_label'] ?? '—' }}</dd>
                        @else
                            <dt class="col-sm-5">Start date</dt><dd class="col-sm-7">{{ !empty($access['start_date']) ? $access['start_date']->format('n/j/Y') : '—' }}</dd>
                            <dt class="col-sm-5">End date</dt><dd class="col-sm-7">{{ !empty($access['end_date']) ? $access['end_date']->format('n/j/Y') : '—' }}</dd>
                            <dt class="col-sm-5">Remaining</dt><dd class="col-sm-7">{{ $access['remaining_label'] ?? '—' }}</dd>
                        @endif
                    </dl>
                    @if($subscription)
                        <hr>
                        @php
                            $history = collect($access['history'] ?? [])->sortBy('occurred_at')->values();
                        @endphp
                        <h6 class="tf-access-history-heading mb-1">Trial / access history</h6>
                        <p class="tf-access-history-description text-muted small mb-3">Lifecycle records are retained when a free trial is converted to paid access.</p>
                        <div class="tf-access-history-table-wrap">
                            <table class="table tf-access-history-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Access type</th>
                                        <th>Start</th>
                                        <th>Scheduled end</th>
                                        <th>Actual end</th>
                                        <th>Duration</th>
                                        <th>Outcome</th>
                                        <th>Recorded</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($history as $event)
                                        @php
                                            $oldValues = $event->old_values ?? [];
                                            $newValues = $event->new_values ?? [];
                                            $isTrialConversion = $event->action === 'trial converted to paid';
                                        @endphp
                                        <tr>
                                            <td><span class="tf-access-type-badge {{ $isTrialConversion ? 'is-trial' : 'is-event' }}">{{ $isTrialConversion ? 'Free Trial' : 'Access Event' }}</span></td>
                                            <td>{{ !empty($oldValues['trial_start']) ? \Illuminate\Support\Carbon::parse($oldValues['trial_start'])->format('n/j/Y') : '—' }}</td>
                                            <td>{{ !empty($oldValues['scheduled_trial_end']) ? \Illuminate\Support\Carbon::parse($oldValues['scheduled_trial_end'])->format('n/j/Y') : '—' }}</td>
                                            <td>{{ !empty($newValues['actual_end']) ? \Illuminate\Support\Carbon::parse($newValues['actual_end'])->format('n/j/Y') : '—' }}</td>
                                            <td>{{ isset($oldValues['trial_duration_days']) ? $oldValues['trial_duration_days'].' days' : '—' }}</td>
                                            <td><div class="tf-access-outcome">
                                                @if($isTrialConversion)
                                                    <span class="badge text-bg-success">Converted to Paid</span>
                                                @else
                                                    <span class="tf-access-outcome-title">{{ \Illuminate\Support\Str::headline($event->action) }}</span>
                                                @endif
                                                @if($event->actor?->name)
                                                    <div class="tf-access-outcome-actor">by {{ $event->actor->name }}</div>
                                                @endif
                                            </div></td>
                                            <td class="text-nowrap">{{ optional($event->occurred_at)->format('n/j/Y, g:i A') }}</td>
                                        </tr>
                                    @empty
                                        @if($subscription->payment_status !== 'Received')
                                            <tr><td colspan="7" class="tf-access-history-empty">No completed access lifecycle events yet.</td></tr>
                                        @endif
                                    @endforelse
                                    @if($subscription->payment_status === 'Received')
                                        <tr>
                                            <td><span class="tf-access-type-badge is-paid">Paid Access</span></td>
                                            <td>{{ !empty($access['paid_access_start']) ? $access['paid_access_start']->format('n/j/Y') : '—' }}</td>
                                            <td>{{ !empty($access['original_paid_access_end']) ? $access['original_paid_access_end']->format('n/j/Y') : '—' }}</td>
                                            <td>—</td>
                                            <td>{{ $access['paid_duration_days'] ?? 0 }} days</td>
                                            <td><div class="tf-access-outcome"><span class="badge text-bg-success">{{ $access['label'] ?? 'Paid access' }}</span></div></td>
                                            <td class="text-nowrap">{{ optional($subscription->updated_at)->format('n/j/Y, g:i A') }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
            </div></div>
        </div>

        @if(!empty($access['can_reactivate_paid']) && $subscription)
            <div class="modal fade tf-access-modal" id="reactivate-paid-access-{{ $business->id }}" tabindex="-1" aria-hidden="true" aria-labelledby="reactivate-paid-access-title-{{ $business->id }}">
                <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
                    <form method="POST" action="{{ route('admin.subscriptions.paid-access.reactivate', $subscription) }}" data-access-confirm data-access-reactivate data-access-confirm="Reactivate this business with the new access period?" data-access-confirm-button="Reactivate Access">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header"><h5 class="modal-title" id="reactivate-paid-access-title-{{ $business->id }}">Reactivate Business Access</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body">
                            <dl class="row small mb-4">
                                <dt class="col-6">Business</dt><dd class="col-6">{{ $business->business_name }}</dd>
                                <dt class="col-6">Previous paid start</dt><dd class="col-6">{{ optional($access['paid_access_start'])->format('n/j/Y') ?: '—' }}</dd>
                                <dt class="col-6">Previous paid end</dt><dd class="col-6">{{ optional($access['original_paid_access_end'])->format('n/j/Y') ?: '—' }}</dd>
                                <dt class="col-6">Previous paid duration</dt><dd class="col-6">{{ $access['paid_duration_days'] ?? 0 }} days</dd>
                                <dt class="col-6">Previous extra days</dt><dd class="col-6">+{{ $access['extra_access_days'] ?? 0 }} days</dd>
                                <dt class="col-6">Expired at</dt><dd class="col-6">{{ optional($access['effective_access_end'] ?? $access['end_date'] ?? null)->format('n/j/Y') ?: '—' }}</dd>
                            </dl>
                            <div class="alert alert-info small">Manual reactivation restores access only. It does not create a payment transaction; use Record Payment for payment-based renewal.</div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label" for="reactivate-start-{{ $business->id }}">New Access Start</label><input id="reactivate-start-{{ $business->id }}" class="form-control" type="date" name="starts_at" value="{{ now(config('app.timezone'))->toDateString() }}" min="{{ now(config('app.timezone'))->toDateString() }}" required></div>
                                <div class="col-md-6"><label class="form-label" for="reactivate-duration-{{ $business->id }}">Paid Duration Days</label><input id="reactivate-duration-{{ $business->id }}" class="form-control" type="number" name="paid_duration_days" min="1" max="3650" value="{{ $access['reactivation_duration_days'] }}" required></div>
                                <div class="col-md-6"><label class="form-label" for="reactivate-extra-{{ $business->id }}">Extra Days</label><input id="reactivate-extra-{{ $business->id }}" class="form-control" type="number" name="extra_days" min="0" max="3650" value="0"></div>
                                <div class="col-12"><label class="form-label" for="reactivate-note-{{ $business->id }}">Admin Note <span class="text-muted">(optional)</span></label><textarea id="reactivate-note-{{ $business->id }}" class="form-control" name="note" rows="3" maxlength="2000"></textarea></div>
                            </div>
                        </div>
                        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" type="submit"><i class="bi bi-arrow-clockwise me-1"></i>Reactivate Access</button></div>
                    </form>
                </div></div>
            </div>
        @endif

        @if(!empty($access['can_restore_restricted']))
            <div class="modal fade tf-access-modal" id="restore-restricted-access-{{ $business->id }}" tabindex="-1" aria-hidden="true" aria-labelledby="restore-restricted-access-title-{{ $business->id }}">
                <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
                    <form method="POST" action="{{ route('admin.businesses.access.restore', $business) }}" data-access-confirm data-access-restore data-access-confirm="Restore this business with its {{ !empty($access['restore_paid_access']) ? 'paid subscription' : 'trial' }} access period?" data-access-confirm-button="Restore Access">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header"><h5 class="modal-title" id="restore-restricted-access-title-{{ $business->id }}">{{ !empty($access['restore_paid_access']) ? 'Restore Paid Business Access' : 'Restore Business Access' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body">
                            <dl class="row small mb-4">
                                <dt class="col-6">Business</dt><dd class="col-6">{{ $business->business_name }}</dd>
                                <dt class="col-6">Current status</dt><dd class="col-6">{{ $access['label'] ?? 'Access Restricted' }}</dd>
                                <dt class="col-6">Previous access end</dt><dd class="col-6">{{ optional($access['end_date'])->format('n/j/Y') ?: '—' }}</dd>
                                @if(!empty($access['restore_paid_access']))
                                    <dt class="col-6">Paid plan</dt><dd class="col-6">{{ $access['restore_plan_name'] ?: 'Previous paid plan' }}</dd>
                                @endif
                            </dl>
                            <div class="alert alert-info small">
                                @if(!empty($access['restore_paid_access']))
                                    This company has paid subscription history. Restoring access resumes its paid plan; it does not start a trial or create a new payment.
                                @else
                                    Restoring access starts a new trial period. It does not create or record a payment.
                                @endif
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label" for="restore-start-{{ $business->id }}">Access Start</label><input id="restore-start-{{ $business->id }}" class="form-control" type="date" name="starts_at" value="{{ now(config('app.timezone'))->toDateString() }}" min="{{ now(config('app.timezone'))->toDateString() }}" required></div>
                                <div class="col-md-6"><label class="form-label" for="restore-duration-{{ $business->id }}">{{ !empty($access['restore_paid_access']) ? 'Paid Duration Days' : 'Trial Duration Days' }}</label><input id="restore-duration-{{ $business->id }}" class="form-control" type="number" name="access_duration_days" min="1" max="{{ !empty($access['restore_paid_access']) ? 3650 : 365 }}" value="{{ !empty($access['restore_paid_access']) ? $access['restore_paid_duration_days'] : $access['restore_duration_days'] }}" required></div>
                                <div class="col-12"><label class="form-label" for="restore-note-{{ $business->id }}">Admin Note <span class="text-muted">(optional)</span></label><textarea id="restore-note-{{ $business->id }}" class="form-control" name="note" rows="3" maxlength="2000"></textarea></div>
                            </div>
                        </div>
                        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" type="submit"><i class="bi bi-arrow-clockwise me-1"></i>Restore Access</button></div>
                    </form>
                </div></div>
            </div>
        @endif

        <div class="modal fade tf-access-modal" id="manage-access-{{ $business->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Manage Trial &amp; Access</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <h6 class="mb-1">{{ $business->business_name }}</h6>
                    <p class="text-muted small mb-3">{{ $business->owner?->name ?: 'No owner assigned' }} · {{ $access['label'] ?? 'Access Restricted' }}</p>
                    <dl class="row small mb-4">
                        @if($subscription?->payment_status !== 'Received')
                            <dt class="col-6">Trial start</dt><dd class="col-6">{{ !empty($access['trial_start']) ? $access['trial_start']->format('n/j/Y') : '—' }}</dd>
                            <dt class="col-6">Trial end</dt><dd class="col-6">{{ !empty($access['trial_end']) ? $access['trial_end']->format('n/j/Y') : '—' }}</dd>
                        @endif
                        @if($subscription?->payment_status === 'Received')
                            <dt class="col-6">Paid access start</dt><dd class="col-6">{{ !empty($access['paid_access_start']) ? $access['paid_access_start']->format('n/j/Y') : '—' }}</dd>
                            <dt class="col-6">Original paid access end</dt><dd class="col-6">{{ !empty($access['original_paid_access_end']) ? $access['original_paid_access_end']->format('n/j/Y') : '—' }}</dd>
                            <dt class="col-6">Paid duration</dt><dd class="col-6">{{ $access['paid_duration_days'] ?? 0 }} days</dd>
                            <dt class="col-6">Extra access</dt><dd class="col-6">+{{ $access['extra_access_days'] ?? 0 }} days</dd>
                            <dt class="col-6">Effective access end</dt><dd class="col-6">{{ !empty($access['effective_access_end']) ? $access['effective_access_end']->format('n/j/Y') : '—' }}</dd>
                            <dt class="col-6">Effective days remaining</dt><dd class="col-6">{{ $access['remaining_label'] ?? '—' }}</dd>
                        @else
                            <dt class="col-6">Days remaining</dt><dd class="col-6">{{ $access['remaining_label'] ?? '—' }}</dd>
                        @endif
                    </dl>

                    @if(!empty($access['can_restore_restricted']))
                        <div class="alert alert-warning small">
                            @if(!empty($access['restore_paid_access']))
                                This business has paid subscription history. Restore its paid plan without starting a trial or creating a payment transaction.
                            @else
                                This business is access restricted. Restore a new trial period without creating a payment transaction.
                            @endif
                        </div>
                        <button class="btn btn-success" type="button" data-restore-launch="{{ $business->id }}"><i class="bi bi-arrow-clockwise me-1"></i>Restore Access</button>
                    @elseif($subscription && !empty($access['can_manage_trial']))
                        <h6>{{ ($access['kind'] ?? '') === 'trial_expired' ? 'Restart / extend trial' : 'Trial controls' }}</h6>
                        <div class="row g-2">
                            <div class="col-md-6"><form method="POST" action="{{ route('admin.subscriptions.trial.adjust', $subscription) }}" data-access-trial-confirm data-access-confirm="Extend this trial?" data-access-current-end="{{ optional($access['trial_end'])->format('Y-m-d') }}">@csrf @method('PATCH')<input type="hidden" name="action" value="extend"><label class="form-label small">Extend by days</label><div class="input-group"><input class="form-control" type="number" name="days" min="1" max="365" required><button class="btn btn-primary" type="submit">Extend</button></div></form></div>
                            <div class="col-md-6"><form method="POST" action="{{ route('admin.subscriptions.trial.adjust', $subscription) }}" data-access-trial-confirm data-access-confirm="Reduce this trial?" data-access-current-end="{{ optional($access['trial_end'])->format('Y-m-d') }}">@csrf @method('PATCH')<input type="hidden" name="action" value="reduce"><label class="form-label small">Reduce by days</label><div class="input-group"><input class="form-control" type="number" name="days" min="1" max="365" required><button class="btn btn-outline-warning" type="submit">Reduce</button></div></form></div>
                            @if(!empty($access['can_end_trial']))<div class="col-12"><button class="btn btn-outline-danger" type="button" data-trial-end-trigger="{{ $business->id }}">End Trial Now</button></div>@endif
                        </div>
                    @elseif($subscription && !empty($access['can_reactivate_paid']))
                        <div class="alert alert-warning small">This paid access period has ended or is restricted. Reactivation creates a new valid access period and does not create a payment record.</div>
                        <button class="btn btn-success" type="button" data-reactivate-launch="{{ $business->id }}"><i class="bi bi-arrow-clockwise me-1"></i>Reactivate Access</button>
                    @elseif($subscription && !empty($access['can_manage_paid']))
                        <h6>Paid Duration Controls</h6>
                        <div class="row g-2">
                            <div class="col-md-6"><form method="POST" action="{{ route('admin.subscriptions.paid-access.adjust', $subscription) }}" data-access-confirm="Extend the original paid duration? Payment and invoice history will remain unchanged." data-access-confirm-button="Extend Paid Duration" data-access-current-end="{{ optional($access['original_paid_access_end'])->format('Y-m-d') }}">@csrf @method('PATCH')<input type="hidden" name="action" value="paid_duration_extend"><label class="form-label small">Extend paid duration by days</label><div class="input-group"><input class="form-control" type="number" name="days" min="1" max="365" required><button class="btn btn-primary" type="submit">Extend Paid Duration</button></div></form></div>
                            <div class="col-md-6"><form method="POST" action="{{ route('admin.subscriptions.paid-access.adjust', $subscription) }}" data-access-confirm="Reduce the original paid duration? Payment and invoice history will remain unchanged." data-access-confirm-button="Reduce Paid Duration" data-access-current-end="{{ optional($access['original_paid_access_end'])->format('Y-m-d') }}" data-access-has-extra="{{ ($access['extra_access_days'] ?? 0) > 0 ? '1' : '0' }}">@csrf @method('PATCH')<input type="hidden" name="action" value="paid_duration_reduce"><label class="form-label small">Reduce paid duration by days</label><div class="input-group"><input class="form-control" type="number" name="days" min="1" max="365" required><button class="btn btn-outline-warning" type="submit">Reduce Paid Duration</button></div></form></div>
                        </div>

                        <hr class="my-4">
                        <h6>Complimentary / Extra Access Controls</h6>
                        <div class="row g-2">
                            <div class="col-md-6"><form method="POST" action="{{ route('admin.subscriptions.paid-access.adjust', $subscription) }}" data-access-confirm="Grant complimentary access days? The original paid period will remain unchanged." data-access-confirm-button="Grant Extra Days" data-access-current-end="{{ optional($access['effective_access_end'])->format('Y-m-d') }}">@csrf @method('PATCH')<input type="hidden" name="action" value="extra_extend"><label class="form-label small">Extra days</label><div class="input-group"><input class="form-control" type="number" name="days" min="1" max="365" required><button class="btn btn-primary" type="submit">Grant Extra Days</button></div></form></div>
                            <div class="col-md-6"><form method="POST" action="{{ route('admin.subscriptions.paid-access.adjust', $subscription) }}" data-access-confirm="Reduce only the complimentary extra-access allowance? The original paid period will remain unchanged." data-access-confirm-button="Reduce Extra Days" data-access-current-end="{{ optional($access['effective_access_end'])->format('Y-m-d') }}">@csrf @method('PATCH')<input type="hidden" name="action" value="extra_reduce"><label class="form-label small">Reduce extra days</label><div class="input-group"><input class="form-control" type="number" name="days" min="1" max="365" required><button class="btn btn-outline-warning" type="submit">Reduce Extra Days</button></div></form></div>
                            @if(!empty($access['can_end_paid']))<div class="col-12"><button class="btn btn-outline-danger" type="button" data-paid-end-trigger="{{ $business->id }}">End Access Now</button></div>@endif
                        </div>
                    @else
                        <p class="text-muted mb-0">There is no access record available to manage for this business.</p>
                    @endif
                </div>
                <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
            </div></div>
        </div>

        @if(!empty($access['can_end_trial']) && $subscription)
            <form id="end-trial-form-{{ $business->id }}" method="POST" action="{{ route('admin.subscriptions.trial.adjust', $subscription) }}" class="d-none" data-access-trial-confirm data-access-confirm="End this trial now? This will restrict workspace access immediately." data-access-current-end="{{ optional($access['trial_end'])->format('Y-m-d') }}" data-access-parent-modal="manage-access-{{ $business->id }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="end_now">
                <button type="submit">End trial now</button>
            </form>
        @endif

        @if(!empty($access['can_end_paid']) && $subscription)
            <form id="end-paid-access-form-{{ $business->id }}" method="POST" action="{{ route('admin.subscriptions.paid-access.adjust', $subscription) }}" class="d-none" data-access-confirm="End paid access now? Workspace access will be restricted immediately. Business data will not be deleted." data-access-confirm-button="End Access" data-access-confirm-danger data-access-parent-modal="manage-access-{{ $business->id }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="action" value="end_now">
                <button type="submit">End Access</button>
            </form>
        @endif
    @endforeach
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var completedMessage = window.sessionStorage.getItem('tf-trial-access-success');
    if (completedMessage) {
        window.sessionStorage.removeItem('tf-trial-access-success');
        window.Swal?.fire({ toast: true, position: 'top-end', icon: 'success', title: completedMessage, showConfirmButton: false, timer: 3000, timerProgressBar: true });
    }

    var businessId = @json($filters['manage'] && $filters['business_id'] ? (int) $filters['business_id'] : null);
    if (businessId) {
        var row = document.querySelector('[data-access-business="' + businessId + '"]');
        var modalElement = document.getElementById('manage-access-' + businessId);
        if (row && modalElement && window.bootstrap?.Modal) {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.focus({ preventScroll: true });
            window.setTimeout(function () {
                window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }, 250);
        }
    }

    document.querySelectorAll('[data-trial-end-trigger]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var form = document.getElementById('end-trial-form-' + trigger.dataset.trialEndTrigger);
            if (form) {
                form._accessReturnFocus = trigger;
                form.requestSubmit();
            }
        });
    });
    document.querySelectorAll('[data-paid-end-trigger]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var form = document.getElementById('end-paid-access-form-' + trigger.dataset.paidEndTrigger);
            if (form) {
                form._accessReturnFocus = trigger;
                form.requestSubmit();
            }
        });
    });
    document.querySelectorAll('[data-reactivate-launch]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var parentModal = trigger.closest('.modal');
            var target = document.getElementById('reactivate-paid-access-' + trigger.dataset.reactivateLaunch);
            if (!parentModal || !target || !window.bootstrap?.Modal) return;
            parentModal.addEventListener('hidden.bs.modal', function openReactivation() {
                parentModal.removeEventListener('hidden.bs.modal', openReactivation);
                window.bootstrap.Modal.getOrCreateInstance(target).show();
            });
            window.bootstrap.Modal.getOrCreateInstance(parentModal).hide();
        });
    });
    document.querySelectorAll('[data-restore-launch]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var parentModal = trigger.closest('.modal');
            var target = document.getElementById('restore-restricted-access-' + trigger.dataset.restoreLaunch);
            if (!parentModal || !target || !window.bootstrap?.Modal) return;
            parentModal.addEventListener('hidden.bs.modal', function openRestore() {
                parentModal.removeEventListener('hidden.bs.modal', openRestore);
                window.bootstrap.Modal.getOrCreateInstance(target).show();
            });
            window.bootstrap.Modal.getOrCreateInstance(parentModal).hide();
        });
    });
});
document.querySelectorAll('[data-access-confirm]').forEach(function (form) {
    if (form.dataset.accessConfirmationReady === '1') return;
    form.dataset.accessConfirmationReady = '1';

    form.addEventListener('submit', function (event) {
        if (form.dataset.accessProcessing === '1' || form.dataset.accessConfirming === '1') {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        if (!window.Swal) {
            HTMLFormElement.prototype.submit.call(form);
            return;
        }
        form.dataset.accessConfirming = '1';

        // SweetAlert is portaled above the Bootstrap Manage modal. Suspend
        // the background modal's focus trap while the foreground confirmation
        // is visible; otherwise it pulls keyboard focus back to its controls.
        var manageModalElement = form.closest('.modal')
            || document.getElementById(form.dataset.accessParentModal || '');
        var manageModal = manageModalElement && window.bootstrap?.Modal
            ? window.bootstrap.Modal.getInstance(manageModalElement)
            : null;
        var manageFocusTrap = manageModal?._focustrap;

        var action = form.querySelector('[name="action"]')?.value || '';
        var operation = action.replace('paid_duration_', '').replace('extra_', '');
        var days = Number(form.querySelector('[name="days"]')?.value || 0);
        var currentValue = form.dataset.accessCurrentEnd;
        var serverTodayValue = @json(now(config('app.timezone'))->toDateString());
        var parseDate = function (value) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '')) return null;
            var parts = value.split('-').map(Number);
            return new Date(parts[0], parts[1] - 1, parts[2]);
        };
        var formatDate = function (date) { return (date.getMonth() + 1) + '/' + date.getDate() + '/' + date.getFullYear(); };
        var currentEnd = parseDate(currentValue);
        var newEnd = currentEnd && new Date(currentEnd.getTime());
        if (newEnd && operation === 'extend') newEnd.setDate(newEnd.getDate() + days);
        if (newEnd && operation === 'reduce') newEnd.setDate(newEnd.getDate() - days);
        var preview = currentEnd && newEnd
            ? '<div class="text-start small"><div><strong>Current End:</strong> ' + formatDate(currentEnd) + '</div>'
                + (operation === 'reduce' ? '<div><strong>Days Removed:</strong> ' + days + '</div>' : '')
                + (operation === 'extend' ? '<div><strong>Days Added:</strong> ' + days + '</div>' : '')
                + '<div><strong>New End:</strong> ' + formatDate(newEnd) + '</div></div>'
            : '';
        var today = parseDate(serverTodayValue) || new Date(); today.setHours(0, 0, 0, 0);
        if (action === 'end_now') {
            preview += '<p class="text-danger small mb-0 mt-2">This change will restrict workspace access immediately.</p>';
        } else if (action === 'paid_duration_reduce' && newEnd && newEnd <= today) {
            preview += '<p class="text-danger small mb-0 mt-2">' + (form.dataset.accessHasExtra === '1'
                ? 'The paid period will expire immediately; separately granted extra access remains in effect.'
                : 'This change will expire paid access immediately and restrict workspace access.') + '</p>';
        } else if (action === 'reduce' && newEnd && newEnd <= today) {
            preview += '<p class="text-danger small mb-0 mt-2">This change will expire the trial immediately and restrict workspace access.</p>';
        }

        var submitTrialChange = async function () {
            form.dataset.accessProcessing = '1';
            try {
                // Forms carry a hidden field named "action", which shadows
                // the DOM form.action property. Read the HTML attribute so
                // the request always targets the Laravel trial route.
                var endpoint = form.getAttribute('action');
                var response = await window.fetch(endpoint, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                    },
                    credentials: 'same-origin',
                });
                var payload = await response.json().catch(function () { return {}; });
                if (!response.ok) {
                    window.Swal.showValidationMessage(payload.message || 'The access change could not be saved. Please review the values and try again.');
                    return false;
                }
                return payload;
            } catch (_) {
                window.Swal.showValidationMessage('The access change could not be saved. Check your connection and try again.');
                return false;
            } finally {
                form.dataset.accessProcessing = '0';
            }
        };

        var isReactivation = form.hasAttribute('data-access-reactivate');
        var isRestore = form.hasAttribute('data-access-restore');
        Swal.fire({
            icon: 'warning',
            title: action === 'end_now' ? 'End access?' : (isReactivation ? 'Reactivate Business Access?' : (isRestore ? 'Restore Business Access?' : 'Confirm access change')),
            html: preview + '<p class="mb-0 mt-3">' + form.dataset.accessConfirm + '</p>',
            showCancelButton: true,
            confirmButtonText: form.dataset.accessConfirmButton || 'Confirm',
            cancelButtonText: 'Cancel',
            confirmButtonColor: form.hasAttribute('data-access-confirm-danger') ? '#dc3545' : '#2563eb',
            reverseButtons: true,
            allowEnterKey: true,
            allowEscapeKey: true,
            allowOutsideClick: function () { return !Swal.isLoading(); },
            stopKeydownPropagation: true,
            focusConfirm: true,
            returnFocus: false,
            showLoaderOnConfirm: true,
            preConfirm: submitTrialChange,
            willOpen: function () {
                manageFocusTrap?.deactivate();
                if (manageModalElement) {
                    manageModalElement.inert = true;
                    manageModalElement.setAttribute('aria-hidden', 'true');
                }
            },
            didOpen: function () {
                // Use the foreground action as the sole Enter-key target.
                window.setTimeout(function () {
                    Swal.getConfirmButton()?.focus({ preventScroll: true });
                }, 0);
            },
            didClose: function () {
                if (manageModalElement) {
                    manageModalElement.inert = false;
                    manageModalElement.removeAttribute('aria-hidden');
                }
                manageFocusTrap?.activate();
                window.setTimeout(function () {
                    var focusTarget = form._accessReturnFocus || form.querySelector('[type="submit"]');
                    focusTarget?.focus({ preventScroll: true });
                    form._accessReturnFocus = null;
                }, 0);
            },
        }).then(function (result) {
            if (!result.isConfirmed) {
                form.dataset.accessConfirming = '0';
                return;
            }
            window.sessionStorage.setItem('tf-trial-access-success', result.value?.message || 'Access updated successfully.');
            window.location.reload();
        });
    });
});
</script>
@endpush
