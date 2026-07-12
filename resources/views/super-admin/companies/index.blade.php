@extends('layouts.dashboard')
@section('page-title', 'Companies')
@section('page-subtitle', 'Manage company approvals, access, and operations')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="btn-group flex-wrap">@foreach(['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended', 'archived' => 'Archived'] as $status => $label)<a class="btn btn-sm {{ ($statusFilter ?: 'all') === $status ? 'btn-tf-primary' : 'btn-outline-secondary' }}" href="{{ $status === 'all' ? route('admin.companies.index') : route('admin.companies.'.$status) }}">{{ $label }}</a>@endforeach</div>
    <a href="{{ route('admin.companies.create') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>Create Company</a>
</div>

<div class="tf-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-6"><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Search company, owner, or email"></div><div class="col-md-auto"><button class="btn btn-outline-primary w-100">Search</button></div><div class="col-md-auto"><a href="{{ request()->url() }}" class="btn btn-outline-secondary w-100">Clear</a></div></form></div>

<x-table class="admin-company-table">
    <thead><tr><th>Company</th><th>Owner</th><th>Business Type</th><th>Plan</th><th>Status</th><th>Staff</th><th>Customers</th><th>Created At</th><th>Last Activity</th><th>Actions</th></tr></thead>
    <tbody>
    @forelse($companies as $company)
        @php($companyStatus = strtolower((string) $company->status))
        <tr>
            <td><strong>{{ $company->business_name }}</strong><small class="d-block tf-muted">{{ $company->category ?: 'Uncategorized' }}</small></td>
            <td>{{ $company->owner?->name ?? '—' }}<small class="d-block tf-muted">{{ $company->owner?->email }}</small></td>
            <td>{{ $company->business_type }}</td>
            <td>{{ $company->subscription?->plan?->name ?? 'No plan' }}</td>
            <td><span class="tf-badge {{ $companyStatus === 'approved' ? 'tf-badge-success' : ($companyStatus === 'pending' ? 'tf-badge-warning' : 'tf-badge-danger') }}">{{ $company->status }}</span></td>
            <td>{{ $company->users_count }}</td><td>{{ $company->customers_count }}</td>
            <td><x-date-time :value="$company->created_at" /></td>
            <td><x-date-time :value="\App\Models\ActivityLog::where('business_id', $company->id)->latest('occurred_at')->value('occurred_at')" /></td>
            <td>
                <div class="dropdown">
                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Actions</button>
                    <div class="dropdown-menu dropdown-menu-end shadow">
                        <a class="dropdown-item" href="{{ route('admin.companies.show', $company) }}"><i class="bi bi-eye me-2"></i>View Company</a>
                        <a class="dropdown-item" href="{{ route('admin.companies.edit', $company) }}"><i class="bi bi-pencil me-2"></i>Edit Company</a>
                        <a class="dropdown-item" href="{{ route('admin.permissions.modules', ['company_id' => $company->id]) }}"><i class="bi bi-shield-lock me-2"></i>Manage Permissions</a>
                        <a class="dropdown-item" href="{{ route('admin.subscriptions', ['business_id' => $company->id]) }}"><i class="bi bi-credit-card me-2"></i>Manage Subscription</a>
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
                        <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" onsubmit="return confirm('Delete this company only when it has no operational records?')">@csrf @method('DELETE')<button type="submit" class="dropdown-item text-danger">Delete when safe</button></form>
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
