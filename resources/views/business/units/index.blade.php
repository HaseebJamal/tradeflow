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

<x-table>
    <thead><tr><th>Unit Name</th><th>Short Code</th><th>Unit Type</th><th>Status</th><th>Created At</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
        @forelse($units as $unit)
            <tr>
                <td><strong>{{ $unit->unit_name }}</strong>@if($unit->description)<div class="small tf-muted text-truncate" style="max-width:280px">{{ $unit->description }}</div>@endif</td>
                <td><code>{{ $unit->short_code }}</code></td>
                <td>{{ $unit->unit_type }}</td>
                <td><span class="tf-badge {{ $unit->trashed() ? 'tf-badge-warning' : ($unit->status === 'Active' ? 'tf-badge-success' : 'tf-badge-secondary') }}">{{ $unit->trashed() ? 'Archived' : $unit->status }}</span></td>
                <td><x-date-time :value="$unit->created_at" /></td>
                <td class="text-end text-nowrap">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" data-bs-boundary="viewport">Actions</button>
                        <div class="dropdown-menu dropdown-menu-end shadow-sm">
                            <a class="dropdown-item" href="{{ route('business.units.show', $unit->id) }}"><i class="bi bi-eye me-2"></i>View</a>
                            @if(!$unit->trashed())
                                @companyCan('units.edit')<a class="dropdown-item" href="{{ route('business.units.edit', $unit) }}"><i class="bi bi-pencil me-2"></i>Edit</a>@endcompanyCan
                                @companyCan('units.status')<form method="POST" action="{{ route('business.units.status', $unit) }}">@csrf @method('PATCH')<button class="dropdown-item" type="submit"><i class="bi bi-toggle-{{ $unit->status === 'Active' ? 'on' : 'off' }} me-2"></i>{{ $unit->status === 'Active' ? 'Deactivate' : 'Activate' }}</button></form>@endcompanyCan
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
            <tr><td colspan="6" class="text-center text-muted py-4">No units found. Add the units used by this business before creating products.</td></tr>
        @endforelse
    </tbody>
</x-table>
<div class="mt-3">{{ $units->links() }}</div>
@endsection
@push('scripts')
@if(session('error'))<script>document.addEventListener('DOMContentLoaded', () => Swal.fire({icon:'warning', text:@json(session('error'))}));</script>@endif
@endpush
