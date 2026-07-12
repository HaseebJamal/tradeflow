@php
    $staffMember = $staffMember ?? null;
    $editing = $staffMember instanceof \App\Models\User;
    $permissionService = app(\App\Services\CompanyPermissionService::class);
    $selectedPermissions = collect(old('permissions', $editing ? ($staffMember->permissions ?? []) : []))
        ->map(fn ($value) => $permissionService->normalise((string) $value))
        ->all();
    $passwordId = $editing ? 'staffPassword'.$staffMember->id : 'staffPasswordCreate';
    $passwordIconId = $editing ? 'staffPasswordIcon'.$staffMember->id : 'staffPasswordCreateIcon';
    $confirmId = $editing ? 'staffPasswordConfirm'.$staffMember->id : 'staffPasswordConfirmCreate';
    $confirmIconId = $editing ? 'staffPasswordConfirmIcon'.$staffMember->id : 'staffPasswordConfirmCreateIcon';
    $draftKey = 'tradeflow_staff_draft_'.auth()->user()->business_id.'_'.auth()->id();
    $currentRole = old('role', $staffMember->role ?? '');
@endphp

<form method="POST" action="{{ $editing ? route('business.staff.update', $staffMember) : route('business.staff.store') }}" enctype="multipart/form-data" class="row g-3" data-staff-form @unless($editing) data-staff-create-form data-staff-draft-key="{{ $draftKey }}" data-staff-draft-created="{{ session('staff_draft_created') ? '1' : '0' }}" @endunless novalidate>
    @csrf
    @if($editing) @method('PUT') @endif

    @unless($editing)
        <div class="col-12 d-none" data-staff-draft-alert>
            <div class="alert alert-info mb-0">Your unfinished staff draft has been restored. For security, please re-enter the password and any uploaded files.</div>
        </div>
    @endunless
    <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <p class="small tf-muted mb-0">Fields marked <span class="text-danger" aria-hidden="true">*</span> are required.</p>
        @unless($editing)<button type="button" class="btn btn-sm btn-outline-secondary" data-clear-staff-draft>Clear Saved Draft</button>@endunless
    </div>

    <div class="col-12"><h3 class="h6 mb-0">Personal Information</h3></div>
    <div class="col-md-3">
        <label for="staff-profile-image" class="form-label">Profile Image <span class="tf-muted small">Optional</span></label>
        <input id="staff-profile-image" name="profile_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control @error('profile_image') is-invalid @enderror">
        @error('profile_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3"><label for="staff-name" class="form-label">Full Name <span class="text-danger" aria-hidden="true">*</span></label><input id="staff-name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $staffMember->name ?? '') }}" maxlength="255" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label for="staff-father-name" class="form-label">Father Name <span class="tf-muted small">Optional</span></label><input id="staff-father-name" name="father_name" class="form-control @error('father_name') is-invalid @enderror" value="{{ old('father_name', $staffMember?->staffProfile?->father_name ?? '') }}" maxlength="255">@error('father_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label for="staff-phone" class="form-label">Phone Number <span class="text-danger" aria-hidden="true">*</span></label><input id="staff-phone" name="phone" type="tel" inputmode="tel" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $staffMember->phone ?? '') }}" maxlength="30" required>@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label for="staff-email" class="form-label">Email <span class="text-danger" aria-hidden="true">*</span></label><input id="staff-email" name="email" type="email" autocomplete="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $staffMember->email ?? '') }}" maxlength="255" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label for="staff-cnic" class="form-label">CNIC Number <span class="tf-muted small">Optional</span></label><input id="staff-cnic" name="cnic" class="form-control @error('cnic') is-invalid @enderror" value="{{ old('cnic', $staffMember?->staffProfile?->cnic ?? '') }}" maxlength="30">@error('cnic')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label for="staff-city" class="form-label">City <span class="tf-muted small">Optional</span></label><input id="staff-city" name="city" class="form-control @error('city') is-invalid @enderror" value="{{ old('city', $staffMember?->staffProfile?->city ?? '') }}" maxlength="100">@error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label for="staff-address" class="form-label">Address <span class="tf-muted small">Optional</span></label><input id="staff-address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $staffMember?->staffProfile?->address ?? '') }}" maxlength="1000">@error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>

    <div class="col-12 mt-2"><h3 class="h6 mb-0">Job Information</h3></div>
    <div class="col-md-3"><label for="staff-employee-id" class="form-label">Employee ID <span class="text-danger" aria-hidden="true">*</span></label><input id="staff-employee-id" name="employee_id" class="form-control @error('employee_id') is-invalid @enderror" value="{{ old('employee_id', $staffMember?->staffProfile?->employee_id ?? '') }}" maxlength="100" required>@error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3">
        <label for="staff-role" class="form-label">Role <span class="text-danger" aria-hidden="true">*</span></label>
        <select id="staff-role" name="role" class="form-select @error('role') is-invalid @enderror" required data-staff-role>
            <option value="" disabled @selected($currentRole === '')>Select role</option>
            @foreach($roles as $value => $label)<option value="{{ $value }}" @selected($currentRole === $value)>{{ $label }}</option>@endforeach
        </select>
        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 {{ $currentRole === 'custom_staff' ? '' : 'd-none' }}" data-custom-role-field>
        <label for="staff-custom-role" class="form-label">Custom Role Name <span class="text-danger" aria-hidden="true">*</span></label>
        <input id="staff-custom-role" name="custom_role_name" class="form-control @error('custom_role_name') is-invalid @enderror" value="{{ old('custom_role_name', $staffMember?->staffProfile?->custom_role_name ?? '') }}" maxlength="100" data-custom-role-input>
        @error('custom_role_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3"><label for="staff-employment-type" class="form-label">Employment Type <span class="text-danger" aria-hidden="true">*</span></label><select id="staff-employment-type" name="employment_type" class="form-select @error('employment_type') is-invalid @enderror" required>@foreach(['Full Time','Part Time','Temporary'] as $type)<option @selected(old('employment_type', $staffMember?->staffProfile?->employment_type ?? 'Full Time') === $type)>{{ $type }}</option>@endforeach</select>@error('employment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label for="staff-joining-date" class="form-label">Joining Date <span class="text-danger" aria-hidden="true">*</span></label><input id="staff-joining-date" name="joining_date" type="date" class="form-control @error('joining_date') is-invalid @enderror" value="{{ old('joining_date', $editing ? optional($staffMember?->staffProfile?->joining_date)->format('Y-m-d') : now()->toDateString()) }}" required>@error('joining_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label for="staff-salary" class="form-label">Salary <span class="tf-muted small">Optional</span></label><input id="staff-salary" name="salary" type="number" min="0" step="0.01" class="form-control @error('salary') is-invalid @enderror" value="{{ old('salary', $staffMember?->staffProfile?->salary ?? '') }}">@error('salary')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-md-3"><label for="staff-status" class="form-label">Status <span class="text-danger" aria-hidden="true">*</span></label><select id="staff-status" name="status" class="form-select @error('status') is-invalid @enderror" required>@foreach($editing ? ['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended','archived'=>'Archived'] : ['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended'] as $value=>$label)<option value="{{ $value }}" @selected(old('status', $staffMember->status ?? 'active') === $value)>{{ $label }}</option>@endforeach</select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>

    <div class="col-12 mt-2"><h3 class="h6 mb-0">Login Information</h3></div>
    @if($editing)<div class="col-12"><small class="tf-muted">Leave password fields empty to keep the current password.</small></div>@endif
    <div class="col-md-4">
        <label for="{{ $passwordId }}" class="form-label">{{ $editing ? 'New Password' : 'Password' }} <span @class(['text-danger' => !$editing, 'd-none' => $editing]) aria-hidden="true">*</span></label>
        <div class="input-group"><input id="{{ $passwordId }}" name="password" type="password" autocomplete="new-password" minlength="8" class="form-control @error('password') is-invalid @enderror" @required(!$editing) data-staff-password><button class="btn btn-outline-secondary tf-password-toggle" type="button" aria-label="Show password" data-tf-password-toggle="#{{ $passwordId }}" data-tf-password-icon="#{{ $passwordIconId }}"><i id="{{ $passwordIconId }}" class="bi bi-eye"></i></button></div>
        <small class="tf-muted">At least 8 characters with uppercase, lowercase, number, and special character.</small>
        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="{{ $confirmId }}" class="form-label">{{ $editing ? 'Confirm New Password' : 'Confirm Password' }} <span @class(['text-danger' => !$editing, 'd-none' => $editing]) aria-hidden="true">*</span></label>
        <div class="input-group"><input id="{{ $confirmId }}" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" class="form-control" @required(!$editing) data-staff-password-confirmation><button class="btn btn-outline-secondary tf-password-toggle" type="button" aria-label="Show confirm password" data-tf-password-toggle="#{{ $confirmId }}" data-tf-password-icon="#{{ $confirmIconId }}"><i id="{{ $confirmIconId }}" class="bi bi-eye"></i></button></div>
        <div class="invalid-feedback" data-staff-password-match-error>Password and confirm password do not match.</div>
    </div>

    <div class="col-12 mt-2" id="permissions">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><h3 class="h6 mb-0">Permissions</h3><button type="button" class="btn btn-sm btn-outline-primary" data-apply-role-defaults>Apply Role Defaults</button></div>
        <label class="form-check border rounded p-3 mb-3 fw-semibold"><input class="form-check-input ms-0 me-2" type="checkbox" data-permission-global> Select All Permissions</label>
        <div class="row g-3">
            @forelse($permissionGroups as $group => $permissions)
                <div class="col-md-6 col-xl-4"><div class="border rounded p-3 h-100" data-permission-group><label class="form-check fw-semibold mb-2"><input class="form-check-input" type="checkbox" data-permission-parent> Select All {{ ucwords(str_replace('_', ' ', $group)) }}</label><div class="d-grid gap-2 mt-2">@foreach($permissions as $value => $label)<label class="form-check"><input class="form-check-input" name="permissions[]" value="{{ $value }}" type="checkbox" data-permission-value="{{ $value }}" data-permission-child @checked(in_array($value, $selectedPermissions, true))> {{ $label }}</label>@endforeach</div></div></div>
            @empty
                <div class="col-12"><div class="alert alert-warning mb-0">No company permissions are enabled for staff assignment yet.</div></div>
            @endforelse
        </div>
    </div>

    <div class="col-12"><button class="btn btn-tf-primary" data-staff-submit>{{ $editing ? 'Update Staff Account' : 'Create Staff Account' }}</button>@if($editing)<a href="{{ route('business.staff.show', $staffMember) }}" class="btn btn-outline-secondary">Cancel</a>@endif</div>
</form>

<script>
document.querySelectorAll('[data-staff-form]').forEach((form) => {
    const defaults = @json($roleDefaults);
    const role = form.querySelector('[data-staff-role]');
    const customField = form.querySelector('[data-custom-role-field]');
    const customInput = form.querySelector('[data-custom-role-input]');
    const password = form.querySelector('[data-staff-password]');
    const confirmation = form.querySelector('[data-staff-password-confirmation]');
    const passwordError = form.querySelector('[data-staff-password-match-error]');
    const refreshRole = () => {
        const custom = role?.value === 'custom_staff';
        customField?.classList.toggle('d-none', !custom);
        if (customInput) customInput.required = custom;
    };
    const validatePasswords = () => {
        if (!password || !confirmation) return true;
        const mismatch = Boolean(confirmation.value) && password.value !== confirmation.value;
        confirmation.classList.toggle('is-invalid', mismatch);
        passwordError?.classList.toggle('d-block', mismatch);
        confirmation.setCustomValidity(mismatch ? 'Password and confirm password do not match.' : '');
        return !mismatch;
    };
    const apply = () => {
        const selected = defaults[role?.value] || [];
        form.querySelectorAll('[data-permission-value]').forEach((input) => { input.checked = selected.includes(input.value); });
        window.TradeFlowPermissions?.syncForm(form);
    };
    role?.addEventListener('change', refreshRole);
    password?.addEventListener('input', validatePasswords);
    confirmation?.addEventListener('input', validatePasswords);
    form.querySelector('[data-apply-role-defaults]')?.addEventListener('click', apply);
    form.addEventListener('submit', (event) => { if (!validatePasswords() || !form.checkValidity()) { event.preventDefault(); form.reportValidity(); } });
    refreshRole();
});
</script>
