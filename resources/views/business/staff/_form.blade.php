@php
    $staffMember = $staffMember ?? null;
    $editing = $staffMember instanceof \App\Models\User;
    $selectedPermissions = collect(old('permissions', $editing ? ($staffMember->permissions ?? []) : []))->map(fn ($value) => strtolower($value))->all();
    $passwordId = $editing ? 'staffPassword'.$staffMember->id : 'staffPasswordCreate';
    $passwordIconId = $editing ? 'staffPasswordIcon'.$staffMember->id : 'staffPasswordCreateIcon';
    $confirmId = $editing ? 'staffPasswordConfirm'.$staffMember->id : 'staffPasswordConfirmCreate';
    $confirmIconId = $editing ? 'staffPasswordConfirmIcon'.$staffMember->id : 'staffPasswordConfirmCreateIcon';
@endphp
<form method="POST" action="{{ $editing ? route('business.staff.update', $staffMember) : route('business.staff.store') }}" enctype="multipart/form-data" class="row g-3" data-staff-form>
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="col-12"><h3 class="h6 mb-0">Personal Information</h3></div>
    <div class="col-md-3">
        <label class="form-label">Profile Image</label>
        <input name="profile_image" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="form-control">
    </div>
    <div class="col-md-3"><label class="form-label">Full Name</label><input name="name" class="form-control" value="{{ old('name', $staffMember->name ?? '') }}" required></div>
    <div class="col-md-3"><label class="form-label">Father Name</label><input name="father_name" class="form-control" value="{{ old('father_name', $staffMember->staffProfile->father_name ?? '') }}"></div>
    <div class="col-md-3"><label class="form-label">Phone Number</label><input name="phone" class="form-control" value="{{ old('phone', $staffMember->phone ?? '') }}"></div>
    <div class="col-md-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="{{ old('email', $staffMember->email ?? '') }}" required></div>
    <div class="col-md-3"><label class="form-label">CNIC Number</label><input name="cnic" class="form-control" value="{{ old('cnic', $staffMember->staffProfile->cnic ?? '') }}"></div>
    <div class="col-md-3"><label class="form-label">City</label><input name="city" class="form-control" value="{{ old('city', $staffMember->staffProfile->city ?? '') }}"></div>
    <div class="col-md-3"><label class="form-label">Address</label><input name="address" class="form-control" value="{{ old('address', $staffMember->staffProfile->address ?? '') }}"></div>

    <div class="col-12 mt-2"><h3 class="h6 mb-0">Job Information</h3></div>
    <div class="col-md-3"><label class="form-label">Employee ID</label><input name="employee_id" class="form-control" value="{{ old('employee_id', $staffMember->staffProfile->employee_id ?? '') }}"></div>
    <div class="col-md-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select" required data-staff-role>
            @foreach($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $staffMember->role ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Employment Type</label>
        <select name="employment_type" class="form-select" required>
            @foreach(['Full Time','Part Time','Temporary'] as $type)
                <option @selected(old('employment_type', $staffMember->staffProfile->employment_type ?? 'Full Time') === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3"><label class="form-label">Joining Date</label><input name="joining_date" type="date" class="form-control" value="{{ old('joining_date', $editing ? optional($staffMember->staffProfile->joining_date ?? null)->format('Y-m-d') : now()->format('Y-m-d')) }}"></div>
    <div class="col-md-3"><label class="form-label">Salary</label><input name="salary" type="number" step="0.01" class="form-control" value="{{ old('salary', $staffMember->staffProfile->salary ?? '') }}"></div>
    <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
            @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $staffMember->status ?? 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 mt-2"><h3 class="h6 mb-0">Login Information</h3></div>
    @if($editing)<div class="col-12"><small class="tf-muted">Leave password blank to keep current password.</small></div>@endif
    <div class="col-md-3">
        <label class="form-label">Password</label>
        <div class="input-group">
            <input id="{{ $passwordId }}" name="password" type="password" class="form-control" @required(!$editing)>
            <button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#{{ $passwordId }}" data-tf-password-icon="#{{ $passwordIconId }}"><i id="{{ $passwordIconId }}" class="bi bi-eye"></i></button>
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Confirm Password</label>
        <div class="input-group">
            <input id="{{ $confirmId }}" name="password_confirmation" type="password" class="form-control" @required(!$editing)>
            <button class="btn btn-outline-secondary tf-password-toggle" type="button" data-tf-password-toggle="#{{ $confirmId }}" data-tf-password-icon="#{{ $confirmIconId }}"><i id="{{ $confirmIconId }}" class="bi bi-eye"></i></button>
        </div>
    </div>

    <div class="col-12 mt-2">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 mb-0">Permissions</h3>
            <button type="button" class="btn btn-sm btn-outline-primary" data-apply-role-defaults>Apply Role Defaults</button>
        </div>
        <label class="form-check border rounded p-3 mb-3 fw-semibold">
            <input class="form-check-input ms-0 me-2" type="checkbox" data-permission-global>
            Select All Permissions
        </label>
        <div class="row g-3">
            @foreach($permissionGroups as $group => $permissions)
                <div class="col-md-6 col-xl-4">
                    <div class="border rounded p-3 h-100" data-permission-group>
                        <label class="form-check fw-semibold mb-2">
                            <input class="form-check-input" type="checkbox" data-permission-parent>
                            Select All {{ ucwords(str_replace('_', ' ', $group)) }}
                        </label>
                        <div class="d-grid gap-2 mt-2">
                            @foreach($permissions as $value => $label)
                                <label class="form-check">
                                    <input class="form-check-input" name="permissions[]" value="{{ $value }}" type="checkbox" data-permission-value="{{ $value }}" data-permission-child @checked(in_array($value, $selectedPermissions, true))>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="col-12">
        <button class="btn btn-tf-primary">{{ $editing ? 'Update Staff Account' : 'Create Staff Account' }}</button>
        @if($editing)<a href="{{ route('business.staff.show', $staffMember) }}" class="btn btn-outline-secondary">Cancel</a>@endif
    </div>
</form>

<script>
document.querySelectorAll('[data-staff-form]').forEach((form) => {
    const defaults = @json($roleDefaults);
    const role = form.querySelector('[data-staff-role]');
    const apply = () => {
        const selected = defaults[role.value] || [];
        form.querySelectorAll('[data-permission-value]').forEach((input) => {
            input.checked = selected.includes(input.value);
        });
        window.TradeFlowPermissions?.syncForm(form);
    };
    form.querySelector('[data-apply-role-defaults]')?.addEventListener('click', apply);
});
</script>
