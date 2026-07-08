@extends('layouts.dashboard')
@section('page-title', 'Payments')
@section('page-subtitle', 'Manual platform payment records')
@section('content')
<x-table><thead><tr><th>Business</th><th>Customer</th><th>Method</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>{{ $payment->customer?->business?->business_name }}</td><td>{{ $payment->customer?->business_name ?? $payment->customer?->name }}</td><td>{{ $payment->method }}</td><td>Rs {{ number_format($payment->amount) }}</td><td>{{ $payment->status }}</td><td>{{ $payment->payment_date?->format('M d, Y') }}</td></tr>@empty<tr><td colspan="6" class="text-center tf-muted py-4">No payments.</td></tr>@endforelse</tbody></x-table>
@endsection
