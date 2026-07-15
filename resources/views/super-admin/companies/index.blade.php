@extends('layouts.dashboard')
@section('page-title', 'Companies')
@section('page-subtitle', 'Manage company approvals, access, and operations')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="btn-group flex-wrap">@foreach(['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended', 'archived' => 'Archived'] as $status => $label)<a class="btn btn-sm {{ ($statusFilter ?: 'all') === $status ? 'btn-tf-primary' : 'btn-outline-secondary' }}" href="{{ $status === 'all' ? route('admin.companies.index') : route('admin.companies.'.$status) }}">{{ $label }}</a>@endforeach</div>
    <div class="d-flex gap-2"><a href="{{ route('admin.approvals.history') }}" class="btn btn-outline-secondary"><i class="bi bi-clock-history me-1"></i>Approval History</a><a href="{{ route('admin.companies.create') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>Create Company</a></div>
</div>

<div class="tf-card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2"><strong>Filter Companies</strong><small class="tf-muted">Current time: <time data-current-time></time></small></div>
    <form method="GET" action="{{ route('admin.companies.index') }}" class="row g-2 align-items-end"><input type="hidden" name="filters_applied" value="1">
        <div class="col-md-3"><label class="form-label">Search</label><input name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Company, owner, email, phone"></div>
        <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(($statusFilter ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Business Type</label><select name="business_type" class="form-select"><option value="">All types</option>@foreach($businessTypes as $type)<option value="{{ $type }}" @selected(($filters['business_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">City</label><input name="city" class="form-control" value="{{ $filters['city'] ?? '' }}" placeholder="Any city"></div>
        <div class="col-md-2"><label class="form-label">Plan</label><select name="plan_id" class="form-select"><option value="">All plans</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((int)($filters['plan_id'] ?? 0) === $plan->id)>{{ $plan->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Sort</label><select name="sort" class="form-select"><option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest first</option><option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest first</option><option value="name_asc" @selected(($filters['sort'] ?? '') === 'name_asc')>Name A-Z</option><option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Name Z-A</option></select></div>
        <div class="col-md-2"><label class="form-label">Created From</label><input type="date" name="date_from" max="{{ now()->toDateString() }}" class="form-control" value="{{ $filters['date_from'] ?? now()->toDateString() }}"></div>
        <div class="col-md-2"><label class="form-label">Created To</label><input type="date" name="date_to" max="{{ now()->toDateString() }}" class="form-control" value="{{ $filters['date_to'] ?? now()->toDateString() }}"></div>
        <div class="col-md-1"><button class="btn btn-outline-primary w-100">Filter</button></div>
        <div class="col-md-1"><a href="{{ route('admin.companies.index') }}" class="btn btn-outline-secondary w-100">Clear</a></div>
    </form>
</div>

<x-table class="admin-company-table">
    <thead><tr><th>Company</th><th>Owner</th><th>Business Type</th><th>Plan</th><th>Status</th><th>Staff</th><th>Permissions</th><th>Created At</th><th>Last Activity</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($companies as $company)
        @php($companyStatus = strtolower((string) $company->status))
        <tr>
            <td><strong>{{ $company->business_name }}</strong></td>
            <td>{{ $company->owner?->name ?? '—' }}<small class="d-block tf-muted">{{ $company->owner?->email }}</small></td>
            <td>{{ $company->business_type }}</td>
            <td>{{ $company->subscription?->plan?->name ?? 'No plan' }}</td>
            <td><span class="tf-badge {{ $companyStatus === 'approved' ? 'tf-badge-success' : ($companyStatus === 'pending' ? 'tf-badge-warning' : 'tf-badge-danger') }}">{{ $company->status }}</span></td>
            <td>{{ $company->users_count }}</td><td>{{ $company->permissions_count }}</td>
            <td><x-date-time :value="$company->created_at" /></td>
            <td><x-date-time :value="\App\Models\ActivityLog::where('business_id', $company->id)->latest('occurred_at')->value('occurred_at')" /></td>
            <td>
                <div class="dropdown">
                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                    <div class="dropdown-menu dropdown-menu-end shadow">
                        <a class="dropdown-item" href="{{ route('admin.companies.show', $company) }}"><i class="bi bi-eye me-2"></i>View Company</a>
                        <a class="dropdown-item" href="{{ route('admin.companies.edit', $company) }}"><i class="bi bi-pencil me-2"></i>Edit Company</a>
                        <a class="dropdown-item" href="{{ route('admin.permissions.index', ['company_id' => $company->id]) }}"><i class="bi bi-shield-lock me-2"></i>Manage Permissions</a>
                        <a class="dropdown-item" href="{{ route('admin.subscriptions', ['business_id' => $company->id]) }}#assign-subscription"><i class="bi bi-credit-card me-2"></i>Manage Subscription</a>
                        <form method="POST" action="{{ route('admin.companies.open-dashboard', $company) }}">@csrf<button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-up-right me-2"></i>Open Business Dashboard</button></form>
                        <div class="dropdown-divider"></div>
                        @foreach(['approved' => 'Approve', 'rejected' => 'Reject', 'suspended' => 'Suspend', 'pending' => 'Activate / Pending'] as $status => $label)
                            @if($companyStatus !== $status)<form method="POST" action="{{ route('admin.companies.status', $company) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $status }}"><button type="submit" class="dropdown-item {{ $status === 'rejected' ? 'text-danger' : ($status === 'suspended' ? 'text-warning' : '') }}">{{ $label }}</button></form>@endif
                        @endforeach
                        <div class="dropdown-divider"></div>
                        @if($companyStatus === 'archived')
                            <form method="POST" action="{{ route('admin.companies.restore', $company) }}">@csrf @method('PATCH')<button type="submit" class="dropdown-item text-success">Restore</button></form>
                        @else
                            <form method="POST" action="{{ route('admin.companies.archive', $company) }}" onsubmit="return confirm('Archive this company? Its data will remain intact.')">@csrf @method('PATCH')<button type="submit" class="dropdown-item text-warning">Archive</button></form>
                        @endif
                        <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" data-tf-company-delete data-company-name="{{ $company->business_name }}">@csrf @method('DELETE')<button type="submit" class="dropdown-item text-danger">Permanently Delete Company</button></form>
                    </div>
                </div>
            </td>
        </tr>
    @empty
        <tr><td colspan="10" class="text-center tf-muted py-4">No companies found.</td></tr>
    @endforelse
    </tbody>
</x-table>
<div class="mt-3">{{ $companies->links() }}</div>
@endsection
@push('scripts')<script>document.addEventListener('DOMContentLoaded',()=>{const clock=document.querySelector('[data-current-time]');if(!clock)return;const update=()=>clock.textContent=new Intl.DateTimeFormat('en-GB',{timeZone:'{{ config('app.timezone') }}',day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true}).format(new Date());update();setInterval(update,1000)});</script>@endpush
