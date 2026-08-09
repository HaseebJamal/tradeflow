@props([
    'status',
    'action',
    'entity' => 'Record',
])

@php($isActive = strcasecmp((string) $status, 'Active') === 0)
@php($nextStatus = $isActive ? 'Inactive' : 'Active')

<form method="POST" action="{{ $action }}" class="tf-inline-status-form" data-tf-status-switch-form data-tf-status-entity="{{ $entity }}">
    @csrf
    @method('PATCH')
    <input type="hidden" name="status" value="{{ $nextStatus }}">
    <button
        type="submit"
        class="tf-inline-status-switch {{ $isActive ? 'is-active' : 'is-inactive' }}"
        role="switch"
        aria-checked="{{ $isActive ? 'true' : 'false' }}"
        aria-label="{{ $isActive ? 'Deactivate' : 'Activate' }} {{ $entity }}"
    >
        <span class="tf-inline-status-track" aria-hidden="true"><span class="tf-inline-status-thumb"></span></span>
        <span class="tf-inline-status-text">{{ $isActive ? 'Active' : 'Inactive' }}</span>
    </button>
</form>
