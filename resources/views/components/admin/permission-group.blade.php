@props(['module', 'label', 'permissions', 'selectedPermissions' => []])
@php($groupId = 'permission-group-'.\Illuminate\Support\Str::slug($module))
<details class="tf-permission-group border rounded h-100" data-permission-group>
    <summary class="d-flex align-items-center justify-content-between gap-2 p-3 fw-semibold">
        <span><i class="bi bi-diagram-3 me-1"></i>{{ $label }}</span><span class="tf-permission-count">{{ count($permissions) }}</span>
    </summary>
    <div class="d-grid gap-2 p-3 pt-0 border-top">
        <label class="form-check fw-semibold pt-3 mb-0" for="{{ $groupId }}-all"><input id="{{ $groupId }}-all" class="form-check-input me-2" type="checkbox" data-permission-module> Select all {{ $label }}</label>
        @foreach($permissions as $permission)
            <label class="form-check" for="{{ $groupId }}-{{ $permission->id }}">
                <input id="{{ $groupId }}-{{ $permission->id }}" class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->permission_key }}" data-permission-child @checked(in_array($permission->permission_key, $selectedPermissions, true))>
                {{ $permission->label }}
            </label>
        @endforeach
    </div>
</details>
