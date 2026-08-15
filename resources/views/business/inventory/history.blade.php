@extends('layouts.dashboard')

@section('page-title', 'Stock History')
@section('page-subtitle', 'Track every inventory movement and stock adjustment.')

@section('content')
@php
    $months = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
    $today = now(config('app.timezone'))->toDateString();
@endphp
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h2 class="h5 mb-1">Stock History</h2><p class="tf-muted mb-0">Review product movements across this business.</p></div>
    <a href="{{ route('business.inventory') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Inventory</a>
</div>

<form method="GET" action="{{ route('business.inventory.history') }}" class="tf-card p-3 mb-3 row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Search Product</label><input name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="Product name"></div>
    <div class="col-md-2"><label class="form-label">Movement Type</label><select name="type" class="form-select"><option value="">All types</option>@foreach($movementTypes as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ str_replace('_', ' ', $type) }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">User</label><select name="user_id" class="form-select"><option value="">All users</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Date From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? $today }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Date To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? $today }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Month</label><select name="month" class="form-select"><option value="">All months</option>@foreach($months as $number => $month)<option value="{{ $number }}" @selected((string) ($filters['month'] ?? '') === (string) $number)>{{ $month }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Year</label><select name="year" class="form-select"><option value="">All years</option>@foreach(range(now()->year, 2020) as $year)<option value="{{ $year }}" @selected((string) ($filters['year'] ?? '') === (string) $year)>{{ $year }}</option>@endforeach</select></div>
    <div class="col-md-4 d-flex flex-wrap gap-2"><button class="btn btn-outline-primary">Filter</button><a href="{{ route('business.inventory.history') }}" class="btn btn-outline-secondary">Clear</a></div>
</form>

<div class="tf-business-data-table">
    <x-table>
        <thead><tr><th>Date &amp; Time</th><th>Product</th><th>Movement Type</th><th>Stock Before</th><th>Quantity</th><th>Operation</th><th>Stock After</th><th>Reference</th><th>User</th></tr></thead>
        <tbody>
        @forelse($movements as $move)
            @php($isReturn = in_array($move->type, ['PURCHASE_RETURN', 'SALES_RETURN'], true))
            @php($operation = $move->type === 'PURCHASE_RETURN' ? '-' : ($move->type === 'SALES_RETURN' ? '+' : '---'))
            <tr>
                <td><x-date-time :value="$move->movement_date ?? $move->created_at" /></td>
                <td>{{ $move->product?->name ?? 'Deleted Product' }}</td>
                <td>{{ $move->type === 'PURCHASE_RETURN' ? 'Purchase Return' : ($move->type === 'SALES_RETURN' ? 'Sales Return' : str_replace('_', ' ', $move->type)) }}</td>
                <td><x-quantity :value="$move->previous_stock" /></td>
                <td><x-quantity :value="abs((float) $move->quantity)" /></td>
                <td>{{ $operation }}</td>
                <td><x-quantity :value="$move->new_stock" /></td>
                <td>{{ $isReturn ? $move->note : '---' }}</td>
                <td>{{ $move->creator?->name ?? 'System' }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center tf-muted py-4">No stock history found.</td></tr>
        @endforelse
        </tbody>
    </x-table>
    <div class="mt-3"><x-table-result-summary :paginator="$movements" />{{ $movements->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
