@extends('layouts.dashboard')
@section('page-title', 'New Sales Return')
@section('page-subtitle', 'Select the original POS sale before choosing items to return')
@section('content')
<form method="POST" action="{{ route('business.sales.returns.start') }}" class="tf-card p-4">@csrf
    <div class="row g-3 align-items-end"><div class="col-md-8"><label class="form-label">Completed POS sale</label><select name="order_id" class="form-select" required autofocus><option value="">Select sale</option>@foreach($orders as $order)<option value="{{ $order->id }}">{{ $order->order_number }} — {{ $order->customer?->name ?? 'Walk-in Customer' }} (Rs {{ number_format($order->grand_total, 2) }})</option>@endforeach</select>@error('order_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div><div class="col-md-4 d-flex gap-2"><button class="btn btn-tf-primary">Continue</button><a href="{{ route('business.sales.returns.index') }}" class="btn btn-outline-secondary">Cancel</a></div></div>
</form>
@endsection
