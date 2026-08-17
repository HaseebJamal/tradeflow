@extends('layouts.dashboard')

@section('page-title', 'Companies')
@section('page-subtitle', 'Manage registered businesses and account status across Profit Point')

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <header class="tf-module-header"><div><span class="tf-dashboard-eyebrow">Platform businesses</span><h2>Companies</h2><p>Manage registered businesses and account status across Profit Point.</p></div><div class="d-flex flex-wrap gap-2"><a href="{{ route('admin.approvals.history') }}" class="btn btn-outline-secondary tf-module-secondary-action"><i class="bi bi-clock-history"></i><span>Approval history</span></a><a href="{{ route('admin.companies.create') }}" class="btn btn-tf-primary tf-dashboard-primary-action"><i class="bi bi-plus-lg"></i>Create company</a></div></header>

    <section class="tf-module-summary" aria-label="Company summary">
        @foreach([['Total businesses', $summary->total ?? 0, 'bi-buildings', 'blue'], ['Approved', $summary->approved ?? 0, 'bi-check2-circle', 'green'], ['Pending', $summary->pending ?? 0, 'bi-hourglass-split', 'amber'], ['Suspended', $summary->suspended ?? 0, 'bi-pause-circle', 'red'], ['Rejected', $summary->rejected ?? 0, 'bi-x-circle', 'slate']] as [$label, $count, $icon, $tone])
            <div class="tf-module-summary-card"><span class="tf-module-summary-icon is-{{ $tone }}"><i class="bi {{ $icon }}"></i></span><span><small>{{ $label }}</small><strong>{{ number_format($count) }}</strong></span></div>
        @endforeach
    </section>

    <div class="tf-card tf-module-filter-card mb-3">
        <div class="tf-module-filter-heading"><div><strong>Search and filter</strong><small>Refine the registered business list.</small></div><small class="tf-muted d-none d-lg-block">Current time: <time data-current-time></time></small></div>
        <form method="GET" action="{{ route('admin.companies.index') }}" class="row g-3 align-items-end">
            <input type="hidden" name="filters_applied" value="1">
            <div class="col-md-3"><label class="form-label">Search</label><input name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Company, owner, email, phone, or registration no."></div>
            <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select" data-tom-select-inline="true"><option value="">All statuses</option>@foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended', 'archived' => 'Archived'] as $value => $label)<option value="{{ $value }}" @selected(($statusFilter ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Business Type</label><select name="business_type" class="form-select" data-tom-select-inline="true"><option value="">All types</option>@foreach($businessTypes as $type)<option value="{{ $type }}" @selected(($filters['business_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">City</label><input name="city" class="form-control" value="{{ $filters['city'] ?? '' }}" placeholder="Any city"></div>
            <div class="col-md-2"><label class="form-label">Sort</label><select name="sort" class="form-select" data-tom-select-inline="true"><option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest first</option><option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest first</option><option value="name_asc" @selected(($filters['sort'] ?? '') === 'name_asc')>Name A-Z</option><option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Name Z-A</option></select></div>
            <div class="col-md-2"><label class="form-label">Created From</label><input type="date" name="date_from" max="{{ now()->toDateString() }}" class="form-control" value="{{ $filters['date_from'] ?? now()->toDateString() }}"></div>
            <div class="col-md-2"><label class="form-label">Created To</label><input type="date" name="date_to" max="{{ now()->toDateString() }}" class="form-control" value="{{ $filters['date_to'] ?? now()->toDateString() }}"></div>
            <div class="col-md-1"><button class="btn btn-tf-primary w-100">Apply</button></div>
            <div class="col-md-1"><a href="{{ route('admin.companies.index') }}" class="btn btn-outline-secondary w-100">Clear</a></div>
        </form>
    </div>

    <section class="tf-module-table-card"><div class="tf-module-table-heading"><div><h3>Registered businesses</h3><p>Businesses currently registered on Profit Point.</p></div><span class="tf-table-result-count">{{ $companies->total() }} results</span></div>
    <x-table class="admin-company-table tf-module-table">
        <thead><tr><th>Business</th><th>Owner</th><th>Contact</th><th>Business status</th><th>Registered</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
            @forelse($companies as $company)
                @php
                    $companyStatus = strtolower((string) $company->status);
                    // This column is the company approval status. Trial and
                    // paid access belong exclusively to Trial & Access.
                    $companyDisplayStatus = $company->status;
                    $companyLogoPath = preg_replace('#^(?:public/|storage/)#', '', ltrim((string) $company->logo, '/'));
                    $hasCompanyLogo = filled($companyLogoPath) && \Illuminate\Support\Facades\Storage::disk('public')->exists($companyLogoPath);
                    $companyInitials = str($company->business_name)->trim()->explode(' ')->filter()->take(2)->map(fn ($part) => str($part)->substr(0, 1)->upper())->implode('');
                @endphp
                <tr>
                    <td><div class="tf-company-cell">@if($hasCompanyLogo)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($companyLogoPath) }}" alt="{{ $company->business_name }} logo">@else<span>{{ $companyInitials ?: 'B' }}</span>@endif<div><strong>{{ $company->business_name }}</strong><small>{{ $company->display_business_type ?: 'Business' }}</small></div></div></td>
                    <td><div class="tf-table-person"><strong>{{ $company->owner?->name ?: 'Owner not assigned' }}</strong><small>{{ $company->owner?->email ?: 'No email provided' }}</small></div></td>
                    <td><span class="tf-table-contact">{{ $company->phone ?: 'Not provided' }}</span></td>
                    <td><span class="tf-badge {{ $companyStatus === 'approved' ? 'tf-badge-success' : ($companyStatus === 'pending' ? 'tf-badge-warning' : ($companyStatus === 'archived' ? 'tf-badge-info' : 'tf-badge-danger')) }}">{{ $companyDisplayStatus }}</span></td>
                    <td><span class="tf-table-date"><x-date-time :value="$company->created_at" /></span></td>
                    <td class="text-end">
                        <div class="d-inline-flex align-items-center gap-2 tf-company-actions"><a href="{{ route('admin.companies.show', $company) }}" class="btn btn-sm btn-outline-primary tf-table-view-action">Review</a><div class="dropdown">
                            <button type="button" class="btn btn-sm btn-outline-secondary tf-table-more-action" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="More actions for {{ $company->business_name }}"><i class="bi bi-three-dots"></i></button>
                            <div class="dropdown-menu dropdown-menu-end shadow tf-company-actions-menu">
                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#company-details-{{ $company->id }}"><i class="bi bi-eye me-2"></i>Quick View</button>
                                @if($companyStatus !== 'archived')
                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#company-manage-{{ $company->id }}"><i class="bi bi-gear me-2"></i>Manage Company</button>
                                @endif
                                @if($companyStatus === 'approved')
                                    <form method="POST" action="{{ route('admin.companies.open-dashboard', $company) }}">@csrf<button type="submit" class="dropdown-item"><i class="bi bi-building me-2"></i>Open Business Dashboard</button></form>
                                @endif
                                @if($companyStatus === 'approved' || $companyStatus === 'pending' || $companyStatus === 'rejected' || $companyStatus === 'suspended')
                                    <div class="dropdown-divider"></div>
                                    @if($companyStatus === 'approved')
                                        <form method="POST" action="{{ route('admin.companies.status', $company) }}" data-tf-confirm-message="Suspend {{ $company->business_name }}? Its workspace will no longer be active. This does not archive the company or remove its data." data-tf-confirm-title="Suspend {{ $company->business_name }}?" data-tf-confirm-button="Suspend Business" data-tf-confirm-color="#f59e0b">@csrf @method('PATCH')<input type="hidden" name="status" value="suspended"><button type="submit" class="dropdown-item text-warning"><i class="bi bi-pause-circle me-2"></i>Suspend</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.companies.archive', $company) }}" data-tf-confirm-message="Archive {{ $company->business_name }}? Its data will remain intact, but it will be removed from the active company list." data-tf-confirm-title="Archive {{ $company->business_name }}?" data-tf-confirm-button="Archive Company" data-tf-confirm-color="#f59e0b">@csrf @method('PATCH')<button type="submit" class="dropdown-item text-warning"><i class="bi bi-box-seam me-2"></i>Archive</button></form>
                                @endif
                                <div class="dropdown-divider"></div>
                                <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" data-tf-company-delete data-company-name="{{ $company->business_name }}">@csrf @method('DELETE')<button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash3 me-2"></i>Permanently Delete</button></form>
                            </div>
                        </div></div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center tf-muted py-5"><i class="bi bi-buildings d-block fs-4 mb-2 text-blue"></i>No businesses match your filters.</td></tr>
            @endforelse
        </tbody>
    </x-table>
    </section>
    <div class="mt-3">{{ $companies->links() }}</div>

    @foreach($companies as $company)
        @php
            $companyStatus = strtolower((string) $company->status);
            $staffCount = $company->users->where('role', '!=', 'business_owner')->count();
            $rolesCount = $company->users->pluck('role')->filter()->unique()->count();
        @endphp
        <div class="modal fade tf-company-details-modal" id="company-details-{{ $company->id }}" tabindex="-1" aria-labelledby="company-details-title-{{ $company->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div><h2 class="modal-title fs-5" id="company-details-title-{{ $company->id }}">{{ $company->business_name }}</h2><p class="tf-muted small mb-0">Company details</p></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <section class="mb-4">
                            <h3 class="h6 mb-3">Company Information</h3>
                            <div class="row g-3">
                                @foreach(['Company Name' => $company->business_name, 'Business Type' => $company->business_type, 'Registration Number' => $company->registration_number, 'Status' => $company->status] as $label => $value)
                                    <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">{{ $label }}</small><strong>{{ $value ?: '—' }}</strong></div></div>
                                @endforeach
                                <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Registration Date</small><strong><x-date-time :value="$company->created_at" /></strong></div></div>
                            </div>
                        </section>
                        <section class="mb-4">
                            <h3 class="h6 mb-3">Owner Information</h3>
                            <div class="row g-3">
                                @foreach(['Owner Name' => $company->owner?->name ?: 'Not provided', 'Owner Login Email' => $company->owner?->email ?: 'Not provided', 'Owner Phone' => $company->owner?->phone ?: 'Not provided'] as $label => $value)
                                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">{{ $label }}</small><strong class="text-break">{{ $value ?: '—' }}</strong></div></div>
                                @endforeach
                            </div>
                        </section>
                        <section class="mb-4">
                            <h3 class="h6 mb-3">Business Information</h3>
                            <div class="row g-3">
                                @foreach(['Address' => $company->address, 'City' => $company->city, 'Country' => $company->country ?? 'Pakistan'] as $label => $value)
                                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">{{ $label }}</small><strong class="text-break">{{ $value ?: '—' }}</strong></div></div>
                                @endforeach
                            </div>
                        </section>
                        <section class="mb-4">
                            <h3 class="h6 mb-3">Statistics</h3>
                            <div class="row g-3">
                                @foreach(['Staff Count' => $staffCount, 'Roles Count' => $rolesCount, 'Products' => $company->products_count, 'Customers' => $company->customers_count, 'Suppliers' => $company->suppliers_count, 'Sales' => $company->orders_count, 'Purchases' => $company->purchases_count] as $label => $value)
                                    <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">{{ $label }}</small><strong>{{ number_format($value ?? 0) }}</strong></div></div>
                                @endforeach
                            </div>
                        </section>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
                </div>
            </div>
        </div>
        @if($companyStatus !== 'archived')
            <div class="modal fade tf-company-manage-modal" id="company-manage-{{ $company->id }}" tabindex="-1" aria-labelledby="company-manage-title-{{ $company->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header"><div><span class="tf-dashboard-eyebrow">Company management</span><h2 class="modal-title" id="company-manage-title-{{ $company->id }}">{{ $company->business_name }}</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body">
                            <p class="tf-muted small mb-3">Choose the area you want to manage. Each option opens the existing secure management screen.</p>
                            <ul class="nav nav-pills tf-company-manage-tabs" id="company-manage-tabs-{{ $company->id }}" role="tablist">
                                <li class="nav-item" role="presentation"><button class="nav-link active" id="company-details-tab-{{ $company->id }}" data-bs-toggle="tab" data-bs-target="#company-details-pane-{{ $company->id }}" type="button" role="tab" aria-controls="company-details-pane-{{ $company->id }}" aria-selected="true">Company Details</button></li>
                                <li class="nav-item" role="presentation"><button class="nav-link" id="company-permissions-tab-{{ $company->id }}" data-bs-toggle="tab" data-bs-target="#company-permissions-pane-{{ $company->id }}" type="button" role="tab" aria-controls="company-permissions-pane-{{ $company->id }}" aria-selected="false">Permissions</button></li>
                                <li class="nav-item" role="presentation"><button class="nav-link" id="company-receipt-tab-{{ $company->id }}" data-bs-toggle="tab" data-bs-target="#company-receipt-pane-{{ $company->id }}" type="button" role="tab" aria-controls="company-receipt-pane-{{ $company->id }}" aria-selected="false">Receipt Settings</button></li>
                            </ul>
                            <div class="tab-content tf-company-manage-tab-content">
                                <section class="tab-pane fade show active" id="company-details-pane-{{ $company->id }}" role="tabpanel" aria-labelledby="company-details-tab-{{ $company->id }}"><i class="bi bi-building-gear" aria-hidden="true"></i><div><strong>Company Details</strong><p>Update the company profile, owner details, logo, and registration information.</p><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.companies.edit', $company) }}">Open Company Details<i class="bi bi-arrow-up-right ms-1"></i></a></div></section>
                                <section class="tab-pane fade" id="company-permissions-pane-{{ $company->id }}" role="tabpanel" aria-labelledby="company-permissions-tab-{{ $company->id }}"><i class="bi bi-shield-lock" aria-hidden="true"></i><div><strong>Permissions</strong><p>Review roles, module access, and the company’s workspace permissions.</p><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.permissions.index', ['manage_company_id' => $company->id]) }}">Open Permissions<i class="bi bi-arrow-up-right ms-1"></i></a></div></section>
                                <section class="tab-pane fade" id="company-receipt-pane-{{ $company->id }}" role="tabpanel" aria-labelledby="company-receipt-tab-{{ $company->id }}"><i class="bi bi-receipt" aria-hidden="true"></i><div><strong>Receipt Settings</strong><p>Configure the information printed on this company’s invoices and receipts.</p><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.companies.document-footer.edit', $company) }}">Open Receipt Settings<i class="bi bi-arrow-up-right ms-1"></i></a></div></section>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const clock = document.querySelector('[data-current-time]');
    if (!clock) return;
    const update = () => clock.textContent = new Intl.DateTimeFormat('en-GB', { timeZone: '{{ config('app.timezone') }}', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true }).format(new Date());
    update();
    setInterval(update, 1000);
});
</script>
@endpush
