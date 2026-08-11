@extends('layouts.dashboard')
@section('page-title', 'New Sales Return')
@section('page-subtitle', 'Select a completed sale, choose returnable items, and process the return without leaving this page')
@section('content')
@if(session('tradeflow_return_alert'))<div class="alert alert-success">{{ session('tradeflow_return_alert.message') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('business.sales.returns.start') }}" class="tf-card p-4">@csrf
    <div class="row g-3 align-items-end">
        <div class="col-md-8">
            <label class="form-label">Completed Sale</label>
            <select name="order_id" class="form-select" data-placeholder="Select completed sale" required autofocus>
                <option value="">Select completed sale</option>
                @foreach($orders as $availableOrder)
                    @php($remainingItems = $availableOrder->items->filter(fn ($item) => $item->quantity > $item->salesReturnItems->sum('quantity'))->count())
                    <option value="{{ $availableOrder->id }}" @selected(old('order_id', $order?->id) == $availableOrder->id)>SALE - {{ $availableOrder->invoice?->invoice_number ?? $availableOrder->order_number }} - {{ $availableOrder->customer?->name ?? 'Walk-in Customer' }} - {{ $availableOrder->order_date?->timezone(config('app.timezone'))->format('n/j/Y, g:i A') }} - Rs {{ number_format($availableOrder->grand_total, 0) }} - {{ $remainingItems }} returnable {{ \Illuminate\Support\Str::plural('item', $remainingItems) }}</option>
                @endforeach
            </select>
            @if($orders->isEmpty())<small class="text-warning d-block mt-2">No completed sales are available for return.</small>@endif
            @error('order_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 d-flex gap-2"><button class="btn btn-tf-primary" @disabled($orders->isEmpty())>Continue</button><a href="{{ route('business.sales.returns.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </div>
</form>
@if($order)
    <div class="mt-3">
        @include('business.sales-returns._return-form', ['order' => $order])
    </div>
@endif
@endsection
