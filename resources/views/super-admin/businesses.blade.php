@extends('layouts.dashboard')
@section('page-title', 'Businesses')
@section('page-subtitle', 'Business portfolio, approval, plan status, and activity')
@section('content')
<x-table>
<thead><tr><th>Business</th><th>Owner</th><th>Type</th><th>Plan</th><th>Subscription</th><th>Status</th><th>Staff</th><th>Customers</th><th>Orders</th><th>Revenue</th><th>Last Activity</th><th>Created At</th><th>Actions</th></tr></thead>
<tbody>@forelse($businesses ?? [] as $business)
<tr>
<td>{{ $business->business_name }}</td>
<td>{{ $business->owner?->name }}</td>
<td>{{ $business->business_type }}</td>
<td>{{ $business->subscription?->plan?->name ?? '-' }}</td>
<td>{{ $business->subscription?->status ?? '-' }}</td>
<td>{{ ucfirst(strtolower($business->status)) }}</td>
<td>{{ $business->users_count }}</td>
<td>{{ $business->customers_count }}</td>
<td>{{ $business->orders_count }}</td>
<td>Rs {{ number_format($business->orders()->whereNotIn('status', ['Cancelled','Void'])->sum('grand_total')) }}</td>
<td><x-date-time :value="\App\Models\ActivityLog::where('business_id', $business->id)->latest('occurred_at')->value('occurred_at')" /></td>
<td><x-date-time :value="$business->created_at" /></td>
<td><a href="{{ route('admin.businesses.show',$business) }}" class="btn btn-sm btn-outline-primary">View</a></td>
</tr>
@empty<tr><td colspan="13" class="text-center tf-muted py-4">No businesses yet.</td></tr>@endforelse</tbody>
</x-table>
@if(isset($businesses) && method_exists($businesses, 'links'))<div class="mt-3">{{ $businesses->links() }}</div>@endif
@endsection
