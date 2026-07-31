@props(['plan'])

<div class="dropdown" data-tf-plan-action-dropdown>
    <button
        class="btn btn-sm btn-outline-primary dropdown-toggle"
        type="button"
        data-bs-toggle="dropdown"
        data-tf-plan-actions
        aria-expanded="false"
    >
        Actions
    </button>
    <ul class="dropdown-menu dropdown-menu-end tf-plan-actions-menu">
        <li>
            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#editPlanModal-{{ $plan->id }}">
                <i class="bi bi-pencil-square me-2"></i>Edit
            </button>
        </li>

        @if($plan->archived_at)
            <li>
                <form method="POST" action="{{ route('admin.subscription-plans.restore', $plan) }}">
                    @csrf
                    @method('PATCH')
                    <button class="dropdown-item"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore</button>
                </form>
            </li>
        @else
            <li>
                <form method="POST" action="{{ route('admin.subscription-plans.status', $plan) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $plan->status === 'Active' ? 'Inactive' : 'Active' }}">
                    <button class="dropdown-item">{{ $plan->status === 'Active' ? 'Deactivate' : 'Activate' }}</button>
                </form>
            </li>
            <li>
                <form method="POST" action="{{ route('admin.subscription-plans.visibility', $plan) }}">
                    @csrf
                    @method('PATCH')
                    <button class="dropdown-item">Make {{ $plan->is_public ? 'Private' : 'Public' }}</button>
                </form>
            </li>
            @if($plan->status === 'Active')
                <li>
                    <form method="POST" action="{{ route('admin.subscription-plans.recommended', $plan) }}">
                        @csrf
                        @method('PATCH')
                        <button class="dropdown-item">{{ $plan->is_recommended ? 'Remove Recommended' : 'Mark Recommended' }}</button>
                    </form>
                </li>
            @endif
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('admin.subscription-plans.archive', $plan) }}">
                    @csrf
                    @method('PATCH')
                    <button class="dropdown-item text-warning">Archive</button>
                </form>
            </li>
        @endif

        <li><hr class="dropdown-divider"></li>
        <li>
            <form
                method="POST"
                action="{{ route('admin.subscription-plans.destroy', $plan) }}"
                data-tf-confirm-message="Delete {{ $plan->name }}? This cannot be undone."
            >
                @csrf
                @method('DELETE')
                <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
            </form>
        </li>
    </ul>
</div>
