@extends('layouts.dashboard')
@section('page-title', 'Purchase Return')
@section('page-subtitle', $return->return_number)
@section('content')
<div class="d-flex justify-content-end gap-2 mb-3"><a href="{{ route('business.purchase-returns.edit', $return) }}" class="btn btn-outline-primary">View workflow</a><a href="{{ route('business.purchase-returns.index') }}" class="btn btn-outline-secondary">Back</a></div>
<section class="tf-card p-4 mb-3"><div class="row g-3"><div class="col-md-3"><small class="tf-muted d-block">Purchase order</small><strong>{{ $return->purchase?->purchase_number }}</strong></div><div class="col-md-3"><small class="tf-muted d-block">Supplier</small><strong>{{ $return->supplier?->supplier_name }}</strong></div><div class="col-md-3"><small class="tf-muted d-block">Return date</small><strong><x-date-time :value="$return->return_date" /></strong></div><div class="col-md-3"><small class="tf-muted d-block">Total</small><strong>Rs {{ number_format($return->total_amount, 2) }}</strong></div><div class="col-12"><small class="tf-muted">Reason</small><div>{{ $return->reason }}</div></div></div></section>
<x-table><thead><tr><th>Product</th><th>Quantity</th><th>Unit cost</th><th>Line total</th></tr></thead><tbody>@foreach($return->items as $item)<tr><td>{{ $item->product?->name ?? $item->purchaseItem?->product_name_snapshot ?? '—' }}</td><td>{{ $item->quantity }}</td><td>Rs {{ number_format($item->unit_cost, 2) }}</td><td>Rs {{ number_format($item->line_total, 2) }}</td></tr>@endforeach</tbody></x-table>
@endsection
