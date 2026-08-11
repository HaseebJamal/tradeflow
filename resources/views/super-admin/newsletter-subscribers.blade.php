@extends('layouts.dashboard')

@section('page-title', 'Newsletter Subscribers')
@section('page-subtitle', 'Manage public newsletter subscriptions')

@section('content')
@php($hasFilters = filled($filters['search'] ?? null) || filled($filters['status'] ?? null))

@if($errors->any())
    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

<header class="tf-module-header tf-newsletter-header">
    <div>
        <span class="tf-dashboard-eyebrow"><i class="bi bi-megaphone"></i>Marketing / audience</span>
        <h2>Newsletter subscribers</h2>
        <p>Manage public newsletter subscriptions.</p>
    </div>
</header>

<section class="tf-module-summary tf-newsletter-summary" aria-label="Newsletter subscriber summary">
    @foreach([
        ['Total subscribers', $summary['total'], 'bi-people', 'blue', 'total'],
        ['Active', $summary['active'], 'bi-person-check', 'green', 'active'],
        ['Inactive', $summary['inactive'], 'bi-person-dash', 'slate', 'inactive'],
        ['New this month', $summary['new_this_month'], 'bi-person-plus', 'violet', 'new_this_month'],
    ] as [$label, $count, $icon, $tone, $key])
        <article class="tf-module-summary-card">
            <span class="tf-module-summary-icon is-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
            <span><small>{{ $label }}</small><strong data-newsletter-stat="{{ $key }}">{{ number_format($count) }}</strong></span>
        </article>
    @endforeach
</section>

<section class="tf-card tf-module-filter-card tf-newsletter-filter-card mb-3" aria-labelledby="newsletter-filter-title">
    <div class="tf-module-filter-heading">
        <div><strong id="newsletter-filter-title">Search and filter</strong><small>Find a subscriber by email or audience status.</small></div>
    </div>
    <form method="GET" action="{{ route('admin.newsletter-subscribers.index') }}" class="row g-3 align-items-end">
        <div class="col-md-7 col-lg-6">
            <label class="form-label" for="subscriber-search">Search</label>
            <div class="tf-newsletter-search"><i class="bi bi-search" aria-hidden="true"></i><input id="subscriber-search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Search by subscriber email"></div>
        </div>
        <div class="col-md-3 col-lg-3">
            <label class="form-label" for="subscriber-status">Status</label>
            <select id="subscriber-status" name="status" class="form-select"><option value="">All statuses</option>@foreach(['Active', 'Inactive'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select>
        </div>
        <div class="col-md-2 col-lg-3 d-flex gap-2">
            <button class="btn btn-tf-primary flex-grow-1" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="{{ route('admin.newsletter-subscribers.index') }}" class="btn btn-outline-secondary tf-newsletter-clear">Clear</a>
        </div>
    </form>
</section>

<section class="tf-module-table-card tf-newsletter-table-card" aria-labelledby="newsletter-table-title">
    <div class="tf-module-table-heading">
        <div><h3 id="newsletter-table-title">Subscribers</h3><p>Subscriptions collected through the Profit Point website.</p></div>
        <span class="tf-table-result-count">{{ number_format($subscribers->total()) }} {{ \Illuminate\Support\Str::plural('subscriber', $subscribers->total()) }}</span>
    </div>
    <x-table class="tf-module-table tf-newsletter-table">
        <thead><tr><th>Subscriber</th><th>Status</th><th>Source</th><th>Subscribed</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
            @forelse($subscribers as $subscriber)
                @php($subscriptionDate = $subscriber->subscribed_at ?: $subscriber->created_at)
                <tr data-newsletter-row data-newsletter-status="{{ $subscriber->status }}">
                    <td>
                        <div class="tf-newsletter-subscriber"><span aria-hidden="true"><i class="bi bi-envelope"></i></span><strong>{{ $subscriber->email }}</strong></div>
                    </td>
                    <td>
                        @php($subscriberIsActive = $subscriber->status === 'Active')
                        <form method="POST" action="{{ route('admin.newsletter-subscribers.update', $subscriber) }}" class="tf-inline-status-form" data-tf-status-switch-form data-tf-status-entity="newsletter subscriber {{ $subscriber->email }}" data-newsletter-status-form data-subscriber-email="{{ $subscriber->email }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="{{ $subscriberIsActive ? 'Inactive' : 'Active' }}">
                            <button type="submit" class="tf-inline-status-switch {{ $subscriberIsActive ? 'is-active' : 'is-inactive' }}" role="switch" aria-checked="{{ $subscriberIsActive ? 'true' : 'false' }}" aria-label="{{ $subscriberIsActive ? 'Deactivate' : 'Activate' }} newsletter subscriber {{ $subscriber->email }}" data-newsletter-state-action>
                                <span class="tf-inline-status-track" aria-hidden="true"><span class="tf-inline-status-thumb"></span></span>
                                <span class="tf-inline-status-text">{{ $subscriberIsActive ? 'Active' : 'Inactive' }}</span>
                            </button>
                        </form>
                    </td>
                    <td><span class="tf-newsletter-source"><i class="bi bi-globe2" aria-hidden="true"></i>Website footer</span></td>
                    <td><span class="tf-table-date" data-newsletter-subscribed>{{ $subscriptionDate?->format('n/j/Y, g:i A') ?: 'Not available' }}</span></td>
                    <td class="text-end"><div class="dropdown"><button type="button" class="btn btn-sm btn-outline-secondary tf-table-more-action" data-bs-toggle="dropdown" aria-label="More actions for {{ $subscriber->email }}"><i class="bi bi-three-dots"></i></button><div class="dropdown-menu dropdown-menu-end"><button type="button" class="dropdown-item" data-newsletter-details data-email="{{ $subscriber->email }}" data-status="{{ $subscriber->status }}" data-subscribed="{{ $subscriptionDate?->format('n/j/Y, g:i A') ?: 'Not available' }}" data-updated="{{ $subscriber->updated_at?->format('n/j/Y, g:i A') ?: 'Not available' }}" data-bs-toggle="modal" data-bs-target="#newsletterDetailsModal"><i class="bi bi-eye me-2"></i>View details</button><div class="dropdown-divider"></div><button type="button" class="dropdown-item text-danger" data-newsletter-delete data-email="{{ $subscriber->email }}" data-action="{{ route('admin.newsletter-subscribers.destroy', $subscriber) }}" data-bs-toggle="modal" data-bs-target="#newsletterDeleteModal"><i class="bi bi-trash3 me-2"></i>Delete Permanently</button></div></div></td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="p-0">
                        <div class="tf-newsletter-empty">
                            <span><i class="bi {{ $hasFilters ? 'bi-search' : 'bi-envelope-paper' }}"></i></span>
                            <strong>{{ $hasFilters ? 'No subscribers match your filters.' : 'No newsletter subscribers yet.' }}</strong>
                            <p>{{ $hasFilters ? 'Try adjusting your search or status filter.' : 'Subscribers from the Profit Point website will appear here.' }}</p>
                            @if($hasFilters)<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.newsletter-subscribers.index') }}">Clear filters</a>@endif
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-table>
</section>

@if($subscribers->hasPages())
    <div class="tf-newsletter-pagination mt-3">{{ $subscribers->links() }}</div>
@endif

<div class="modal fade tf-newsletter-modal" id="newsletterDetailsModal" tabindex="-1" aria-labelledby="newsletterDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header"><div><span class="tf-dashboard-eyebrow">Subscriber details</span><h2 class="modal-title" id="newsletterDetailsTitle">Newsletter subscriber</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><dl class="tf-newsletter-detail-list"><div><dt>Email</dt><dd data-newsletter-detail="email"></dd></div><div><dt>Status</dt><dd><span class="tf-badge" data-newsletter-detail="status"></span></dd></div><div><dt>Source</dt><dd>Website footer</dd></div><div><dt>Subscribed</dt><dd data-newsletter-detail="subscribed"></dd></div><div><dt>Last updated</dt><dd data-newsletter-detail="updated"></dd></div></dl></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="newsletterDeleteModal" tabindex="-1" aria-labelledby="newsletterDeleteTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form method="POST" class="modal-content" data-newsletter-delete-form>
        @csrf @method('DELETE')
        <div class="modal-header border-danger"><div><span class="text-danger small text-uppercase fw-semibold">Irreversible action</span><h2 class="modal-title" id="newsletterDeleteTitle">Delete subscriber permanently</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><p>This removes this subscriber from the platform mailing list and cannot be undone.</p><p class="mb-3">Subscriber: <strong data-newsletter-delete-email></strong></p><label class="form-label" for="newsletter-delete-confirmation">Type the exact email address to confirm</label><input id="newsletter-delete-confirmation" name="confirmation" class="form-control" autocomplete="off" required data-newsletter-delete-confirmation></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger" disabled data-newsletter-delete-submit>Delete Permanently</button></div>
    </form></div>
</div>

@endsection

@push('scripts')
<script>
(() => {
    const detailModal = document.getElementById('newsletterDetailsModal');
    const showToast = (icon, title) => {
        if (window.Swal) return window.Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2400, timerProgressBar: true });
        window.alert(title);
    };
    const setStatusBadge = (badge, status) => {
        badge.textContent = status;
        badge.classList.toggle('tf-badge-success', status === 'Active');
        badge.classList.toggle('tf-badge-secondary', status !== 'Active');
    };
    const updateSummary = (summary) => Object.entries(summary || {}).forEach(([key, value]) => {
        const stat = document.querySelector(`[data-newsletter-stat="${key}"]`);
        if (stat) stat.textContent = Number(value || 0).toLocaleString();
    });

    document.querySelectorAll('[data-newsletter-details]').forEach((button) => button.addEventListener('click', () => {
        detailModal.querySelector('[data-newsletter-detail="email"]').textContent = button.dataset.email;
        const badge = detailModal.querySelector('[data-newsletter-detail="status"]');
        setStatusBadge(badge, button.dataset.status);
        detailModal.querySelector('[data-newsletter-detail="subscribed"]').textContent = button.dataset.subscribed;
        detailModal.querySelector('[data-newsletter-detail="updated"]').textContent = button.dataset.updated;
    }));

    const deleteForm = document.querySelector('[data-newsletter-delete-form]');
    const deleteInput = deleteForm?.querySelector('[data-newsletter-delete-confirmation]');
    const deleteSubmit = deleteForm?.querySelector('[data-newsletter-delete-submit]');
    document.querySelectorAll('[data-newsletter-delete]').forEach((button) => button.addEventListener('click', () => {
        deleteForm.action = button.dataset.action;
        deleteForm.dataset.email = button.dataset.email;
        deleteInput.value = '';
        deleteForm.querySelector('[data-newsletter-delete-email]').textContent = button.dataset.email;
        deleteSubmit.disabled = true;
    }));
    deleteInput?.addEventListener('input', () => { deleteSubmit.disabled = deleteInput.value !== deleteForm.dataset.email; });
    deleteForm?.addEventListener('submit', (event) => {
        // The typed-email modal is the single irreversible-action confirmation.
        // Do not layer a browser confirm over it; that can double-submit or
        // steal focus from the foreground dialog.
        if (deleteInput.value !== deleteForm.dataset.email) { event.preventDefault(); return; }
        deleteSubmit.disabled = true;
        deleteSubmit.textContent = 'Deleting…';
    });

    document.addEventListener('tf:status-updated', (event) => {
        const form = event.target.closest?.('[data-newsletter-status-form]');
        const payload = event.detail || {};
        if (!form || !payload.subscriber) return;
        const row = form.closest('[data-newsletter-row]');
        const status = payload.subscriber.status;
        row.dataset.newsletterStatus = status;
        const details = row.querySelector('[data-newsletter-details]');
        details.dataset.status = status;
        if (payload.subscriber.updated_at) details.dataset.updated = new Date(payload.subscriber.updated_at).toLocaleString();
        updateSummary(payload.summary);
    });

    @if(session('success'))showToast('success', @json(session('success')));@endif
})();
</script>
@endpush
