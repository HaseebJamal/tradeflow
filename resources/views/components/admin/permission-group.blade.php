@props(['module', 'label', 'permissions', 'selectedPermissions' => []])
@php($groupId = 'permission-group-'.\Illuminate\Support\Str::slug($module))
<section class="tf-permission-group border rounded p-3 h-100" data-permission-group>
    <div class="d-flex align-items-center justify-content-between gap-2 mb-2"><label class="form-check fw-semibold mb-0" for="{{ $groupId }}-all">
        <input id="{{ $groupId }}-all" class="form-check-input" type="checkbox" data-permission-module>
        Select All {{ $label }}
    </label><span class="tf-permission-count">{{ count($permissions) }}</span></div>
    <div class="d-grid gap-2 mt-2">
        @foreach($permissions as $permission)
            <label class="form-check" for="{{ $groupId }}-{{ $permission->id }}">
                <input id="{{ $groupId }}-{{ $permission->id }}" class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->permission_key }}" data-permission-child @checked(in_array($permission->permission_key, $selectedPermissions, true))>
                {{ $permission->label }}
            </label>
        @endforeach
    </div>
</section>
