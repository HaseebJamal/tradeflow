@extends('layouts.dashboard')
@section('page-title', 'Units')
@section('page-subtitle', 'Manage product measurement units')
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
        <input name="search" class="form-control" style="min-width:220px" value="{{ request('search') }}" placeholder="Search unit name or code">
        <select name="status" class="form-select" style="min-width:150px"><option value="">All</option><option value="Active" @selected(request('status') === 'Active')>Active</option><option value="Inactive" @selected(request('status') === 'Inactive')>Inactive</option><option value="Archived" @selected(request('status') === 'Archived' || request('archived'))>Archived</option></select>
        <button class="btn btn-outline-primary">Filter</button>
        <a class="btn btn-outline-secondary" href="{{ route('business.units.index') }}">Clear</a>
    </form>
    @companyCan('units.create')<a class="btn btn-tf-primary" href="{{ route('business.units.create') }}"><i class="bi bi-plus-lg me-1"></i>Add Unit</a>@endcompanyCan
</div>

<x-table class="tf-business-data-table" data-tf-datatable>
    <thead><tr><th>Unit Name</th><th>Short Code</th><th>Unit Type</th><th>Status</th><th>Created At</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
        @forelse($units as $unit)
            <tr>
                <td><strong>{{ $unit->unit_name }}</strong>@if($unit->description)<div class="small tf-muted text-truncate" style="max-width:280px">{{ $unit->description }}</div>@endif</td>
                <td><code>{{ $unit->short_code }}</code></td>
                <td>{{ $unit->unit_type }}</td>
                <td>@if($unit->trashed())<span class="tf-badge tf-badge-warning">Archived</span>@else @companyCan('units.status')<x-inline-status-switch :status="$unit->status" :action="route('business.units.status', $unit)" entity="unit {{ $unit->unit_name }}" />@else<span class="tf-badge {{ $unit->status === 'Active' ? 'tf-badge-success' : 'tf-badge-secondary' }}">{{ $unit->status }}</span>@endcompanyCan @endif</td>
                <td><x-date-time :value="$unit->created_at" /></td>
                <td class="text-end text-nowrap">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" data-bs-boundary="viewport">Actions</button>
                        <div class="dropdown-menu dropdown-menu-end shadow-sm">
                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#unitDetailsModal{{ $unit->id }}"><i class="bi bi-eye me-2"></i>View</button>
                            @if(!$unit->trashed())
                                @companyCan('units.edit')<a class="dropdown-item" href="{{ route('business.units.edit', $unit) }}"><i class="bi bi-pencil me-2"></i>Edit</a>@endcompanyCan
                                @companyCan('units.archive')<form method="POST" action="{{ route('business.units.archive', $unit) }}">@csrf @method('PATCH')<button class="dropdown-item text-warning" type="submit"><i class="bi bi-archive me-2"></i>Archive</button></form>@endcompanyCan
                                @companyCan('units.delete')<form method="POST" action="{{ route('business.units.destroy', $unit) }}" onsubmit="return confirm('Delete this unused unit permanently?')">@csrf @method('DELETE')<button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-2"></i>Delete</button></form>@endcompanyCan
                            @else
                                @companyCan('units.archive')<form method="POST" action="{{ route('business.units.restore', $unit->id) }}">@csrf @method('PATCH')<button class="dropdown-item text-success" type="submit"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore</button></form>@endcompanyCan
                                @companyCan('units.delete')<form method="POST" action="{{ route('business.units.destroy', $unit->id) }}" onsubmit="return confirm('Delete this archived unit permanently?')">@csrf @method('DELETE')<button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-2"></i>Permanently Delete</button></form>@endcompanyCan
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <tr data-tf-datatable-empty><td colspan="6" class="text-center text-muted py-4">No units found. Add the units used by this business before creating products.</td></tr>
        @endforelse
    </tbody>
</x-table>
@foreach($units as $unit)
    <x-record-details-modal :id="'unitDetailsModal'.$unit->id" :title="$unit->unit_name" :status="$unit->trashed() ? 'Archived' : $unit->status" :open-url="route('business.units.show', $unit)" open-label="Open unit record">
        <div class="tf-record-details-grid"><div><span>Short code</span><strong>{{ $unit->short_code }}</strong></div><div><span>Unit type</span><strong>{{ $unit->unit_type }}</strong></div><div><span>Created</span><strong><x-date-time :value="$unit->created_at" /></strong></div><div><span>Status</span><strong>{{ $unit->trashed() ? 'Archived' : $unit->status }}</strong></div><div class="tf-record-details-wide"><span>Description</span><strong>{{ $unit->description ?: 'Not provided' }}</strong></div></div>
    </x-record-details-modal>
@endforeach

@endsection
@push('scripts')
@if(session('error'))<script>document.addEventListener('DOMContentLoaded', () => Swal.fire({icon:'warning', text:@json(session('error'))}));</script>@endif
@endpush
