@extends('layouts.dashboard')
@section('page-title', 'Businesses')
@section('page-subtitle', 'Business approval and plan status')
@section('content')
<x-table>
<thead><tr><th>Business</th><th>Owner</th><th>Type</th><th>Plan</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>@forelse($businesses ?? [] as $business)<tr><td>{{ $business->business_name }}</td><td>{{ $business->owner?->name }}</td><td>{{ $business->business_type }}</td><td>{{ $business->subscription?->plan?->name ?? '-' }}</td><td>{{ ucfirst(strtolower($business->status)) }}</td><td><a href="{{ route('admin.businesses.show',$business) }}" class="btn btn-sm btn-outline-primary">View Details</a></td></tr>@empty<tr><td colspan="6" class="text-center tf-muted py-4">No businesses yet.</td></tr>@endforelse</tbody>
</x-table>
@if(isset($businesses) && method_exists($businesses, 'links'))<div class="mt-3">{{ $businesses->links() }}</div>@endif
@endsection
