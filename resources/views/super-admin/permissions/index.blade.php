@extends('layouts.dashboard')

@section('page-title', $title)
@section('page-subtitle', 'Manage complete module and action access for every company in one place')

@section('content')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="tf-card p-4 mb-4">
        <form method="GET" class="row g-3 align-items-end">
            @if($lockedCompany)
                <div class="col-md-6">
                    <label class="form-label" for="companyPermissionCompany">Selected Company</label>
                    <input id="companyPermissionCompany" class="form-control bg-light" value="{{ $lockedCompany->business_name }}" readonly aria-readonly="true">
                    <input type="hidden" name="manage_company_id" value="{{ $lockedCompany->id }}">
                    <small class="tf-muted">This company was selected from the Companies actions menu and cannot be changed here.</small>
                </div>
            @else
                <div class="col-md-6">
                    <label class="form-label" for="companyPermissionCompany">Select Company</label>
                    <select id="companyPermissionCompany" name="company_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Choose a company</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected($selectedCompany?->id === $company->id)>{{ $company->business_name }} - {{ $company->owner?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto"><button class="btn btn-outline-primary">Load Permissions</button></div>
            @endif
            <div class="col-md-auto"><a href="{{ route('admin.permissions.templates') }}" class="btn btn-outline-secondary">Permission Templates</a></div>
        </form>
    </div>

    @if($selectedCompany)
        <form method="POST" action="{{ route('admin.permissions.company.update') }}" class="tf-card p-4" data-company-permission-form data-tf-confirm-message="Save these permission changes for {{ $selectedCompany->business_name }}? Access changes will apply immediately." data-tf-confirm-icon="question" data-tf-confirm-color="#2563eb">
            @csrf
            @method('PUT')
            <input type="hidden" name="company_id" value="{{ $selectedCompany->id }}">
            <input type="hidden" name="scope" value="{{ $scope }}">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">{{ $selectedCompany->business_name }}</h2>
                    <p class="tf-muted mb-0">Select a module to enable it, then choose the features and actions available beneath it.</p>
                </div>
                <button class="btn btn-tf-primary">Save Permissions</button>
            </div>
            <label class="form-check border rounded p-3 mb-3 fw-semibold" for="permissionMaster">
                <input id="permissionMaster" class="form-check-input me-2" type="checkbox" data-permission-master>
                Select All Permissions <span class="tf-muted" data-permission-total-selected></span>
            </label>
            <div class="row g-3">
                @foreach($definitions->groupBy('module') as $module => $permissions)
                    <div class="col-md-6 col-xl-4">
                        <x-admin.permission-group :module="$module" :label="$module === 'staff' ? 'Roles & Users' : ucwords(str_replace('_', ' ', $module))" :permissions="$permissions" :selected-permissions="$selectedPermissions" />
                    </div>
                @endforeach
            </div>
        </form>
    @else
        <div class="tf-card p-5 text-center tf-muted">Choose a company to manage its complete permission tree.</div>
    @endif
@endsection
