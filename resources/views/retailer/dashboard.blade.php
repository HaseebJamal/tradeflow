@extends('layouts.dashboard')
@section('title', 'Retailer Dashboard | TradeFlow')
@section('page-title', 'Retailer Dashboard')
@section('page-subtitle', 'Buying and credit overview')
@section('content')
<div class="row g-3">
@foreach([
    ['Open Orders',$openOrders ?? 0,'bi-bag-check','bg-blue','In progress'],
    ['Total Orders',$ordersCount ?? 0,'bi-receipt','bg-green','All time'],
    ['Credit Balance','Rs '.number_format(auth()->user()->phone ? \App\Models\Customer::where('phone', auth()->user()->phone)->sum('current_balance') : 0),'bi-wallet2','bg-amber','Across suppliers'],
    ['Marketplace','Live','bi-buildings','bg-navy','Approved suppliers'],
] as [$label,$value,$icon,$color,$note])
<div class="col-md-6 col-xl-3">@include('components.card', compact('label','value','icon','color','note'))</div>
@endforeach
</div>
<div class="row g-4 mt-1">
    <div class="col-lg-8"><x-table><thead><tr><th>Order</th><th>Supplier</th><th>Total</th><th>Status</th></tr></thead><tbody>@forelse($orders ?? [] as $order)<tr><td>{{ $order->order_number }}</td><td>{{ $order->business?->business_name }}</td><td>Rs {{ number_format($order->grand_total ?: $order->total) }}</td><td>{{ $order->status }}</td></tr>@empty<tr><td colspan="4" class="text-center tf-muted py-4">No retailer orders yet.</td></tr>@endforelse</tbody></x-table></div>
    <div class="col-lg-4"><div class="tf-card p-4"><h2 class="h5">Quick Buy</h2><p class="tf-muted">Browse approved supplier products and place manual wholesale orders.</p><a href="{{ route('retailer.products') }}" class="btn btn-tf-primary">Browse Products</a></div></div>
</div>
@endsection
