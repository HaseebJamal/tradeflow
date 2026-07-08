@extends('layouts.dashboard')
@section('page-title', 'Orders')
@section('page-subtitle', 'Wholesale order tracking')
@section('content')
<div class="d-flex justify-content-end mb-3"><a href="{{ route('business.orders.create') }}" class="btn btn-tf-primary"><i class="bi bi-plus-lg me-1"></i>Create Order</a></div>
<x-table><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>@forelse($orders ?? [] as $order)<tr><td>{{ $order->order_number }}</td><td>{{ $order->customer?->business_name ?? $order->customer?->name }}</td><td>{{ $order->created_at->format('M d, Y') }}</td><td>Rs {{ number_format($order->grand_total ?: $order->total) }}</td><td>{{ $order->status }}</td><td><a href="{{ route('business.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a></td></tr>@empty<tr><td colspan="6" class="text-center tf-muted py-4">No orders yet.</td></tr>@endforelse</tbody></x-table>
@if(isset($orders) && method_exists($orders, 'links'))<div class="mt-3">{{ $orders->links() }}</div>@endif
@endsection
