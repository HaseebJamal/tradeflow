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
        'rejected' => ['pending' => 'Move to Pending'],
    ][$currentStatus] ?? [];
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        <div class="tf-card p-4">
            <h2 class="h5 mb-3">Company Overview</h2>
            <div class="row g-3">
                @foreach(['Company Name' => $company->business_name, 'Owner' => $company->owner?->name, 'Phone' => $company->phone, 'Business Type' => $company->business_type, 'Category' => $company->category, 'City' => $company->city] as $label => $value)
                    <div class="col-md-6"><div class="border rounded p-3"><small class="tf-muted">{{ $label }}</small><strong class="d-block">{{ $value ?: '—' }}</strong></div></div>
                @endforeach
                <div class="col-md-6"><div class="border rounded p-3"><small class="tf-muted">Created At</small><strong class="d-block"><x-date-time :value="$company->created_at" /></strong></div></div>
            </div>
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
