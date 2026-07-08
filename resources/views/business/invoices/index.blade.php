@extends('layouts.dashboard')
@section('page-title', 'Invoices')
@section('page-subtitle', 'Generate, print, and download order invoices')
@section('content')
<x-table><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>@forelse($orders as $order)<tr><td>{{ $order->order_number }}</td><td>{{ $order->customer?->business_name ?? $order->customer?->name }}</td><td>Rs {{ number_format($order->grand_total ?: $order->total) }}</td><td>{{ $order->status }}</td><td class="d-flex gap-2"><a href="{{ route('business.invoices.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a><a href="{{ route('business.invoices.pdf', $order) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">PDF</a></td></tr>@empty<tr><td colspan="5" class="text-center tf-muted py-4">No invoice-ready orders.</td></tr>@endforelse</tbody></x-table>
@endsection
