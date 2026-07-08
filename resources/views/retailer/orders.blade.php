@extends('layouts.dashboard')
@section('page-title', 'Retailer Orders')
@section('page-subtitle', 'Order history')
@section('content')
<x-table><thead><tr><th>Order</th><th>Supplier</th><th>Date</th><th>Total</th><th>Status</th></tr></thead><tbody>@forelse($orders ?? [] as $order)<tr><td>{{ $order->order_number }}</td><td>{{ $order->business?->business_name }}</td><td>{{ $order->created_at->format('M d, Y') }}</td><td>Rs {{ number_format($order->grand_total ?: $order->total) }}</td><td>{{ $order->status }}</td></tr>@empty<tr><td colspan="5" class="text-center tf-muted py-4">No orders yet.</td></tr>@endforelse</tbody></x-table>
@endsection
