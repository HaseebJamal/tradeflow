@extends('layouts.dashboard')
@section('page-title', 'POS Sales History')
@section('page-subtitle', 'Completed counter sales, receipts, and returns')
@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
    <form method="GET" class="row g-2"><div class="col-md-auto"><input name="search" class="form-control" value="{{ request('search') }}" placeholder="Receipt number"></div><div class="col-md-auto"><input name="date_from" type="date" class="form-control" value="{{ request('date_from', now()->startOfMonth()->toDateString()) }}"></div><div class="col-md-auto"><input name="date_to" type="date" class="form-control" value="{{ request('date_to', now()->toDateString()) }}"></div><div class="col-md-auto"><button class="btn btn-outline-primary">Filter</button></div></form>
    <a href="{{ route('business.pos.index') }}" class="btn btn-tf-primary"><i class="bi bi-upc-scan me-1"></i>New Sale</a>
</div>
<x-table><thead><tr><th>Receipt</th><th>Customer</th><th>Date</th><th>Total</th><th>Paid</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($orders as $order)
<tr><td>{{ $order->order_number }}</td><td>{{ $order->customer?->business_name ?: $order->customer?->name ?: 'Walk-in Customer' }}</td><td><x-date-time :value="$order->order_date" /></td><td>Rs {{ number_format($order->grand_total, 2) }}</td><td>Rs {{ number_format($order->paid_amount, 2) }}</td><td>{{ $order->status }}</td><td><div class="d-flex flex-wrap gap-1"><a class="btn btn-sm btn-outline-primary" href="{{ route('business.pos.receipt', $order) }}">View</a>@companyCan('pos.print_receipt')<a class="btn btn-sm btn-outline-secondary" href="{{ route('business.pos.receipt.pdf', $order) }}" target="_blank" rel="noopener">PDF</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('business.pos.receipt.pdf.download', $order) }}">Download</a>@endcompanyCan@if(in_array($order->status, ['New', 'Accepted'], true))<a class="btn btn-sm btn-outline-warning" href="{{ route('business.orders.edit', $order) }}">Edit Draft</a>@companyCan('pos.void_sale')<form method="POST" action="{{ route('business.pos.void', $order) }}" onsubmit="return confirm('Void this unpaid POS draft and restore stock?')">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger">Void</button></form>@endcompanyCan@endif@companyCan('pos.returns')@if(!in_array($order->status, ['Void', 'Cancelled'], true))<a class="btn btn-sm btn-outline-warning" href="{{ route('business.pos.returns', $order) }}">Return</a>@endif@endcompanyCan</div></td></tr>
@empty<tr><td colspan="7" class="text-center tf-muted py-4">No POS sales found.</td></tr>@endforelse
</tbody></x-table><div class="mt-3">{{ $orders->links() }}</div>
@endsection
