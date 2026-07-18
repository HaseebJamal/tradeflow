@extends('layouts.dashboard')
@section('page-title', 'New Sales Return')
@section('page-subtitle', 'Select the original POS sale before choosing items to return')
@section('content')
<form method="POST" action="{{ route('business.sales.returns.start') }}" class="tf-card p-4">@csrf
    <div class="row g-3 align-items-end">
        <div class="col-md-8">
            <label class="form-label">Completed POS Sale</label>
            <select name="order_id" class="form-select" data-placeholder="Select completed POS sale" required autofocus>
                <option value="">Select completed POS sale</option>
                @foreach($orders as $order)
                    @php($remainingItems = $order->items->filter(fn ($item) => $item->quantity > $item->posReturnItems->sum('quantity'))->count())
                    <option value="{{ $order->id }}" @selected(old('order_id') == $order->id)>{{ $order->invoice?->invoice_number ?? $order->order_number }} — {{ $order->customer?->name ?? 'Walk-in Customer' }} — {{ $order->order_date?->timezone(config('app.timezone'))->format('d M Y h:i A') }} — Rs {{ number_format($order->grand_total, 2) }} — {{ $remainingItems }} returnable {{ \Illuminate\Support\Str::plural('item', $remainingItems) }}</option>
                @endforeach
            </select>
            @if($orders->isEmpty())<small class="text-warning d-block mt-2">No completed sales are available for return.</small>@endif
            @error('order_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 d-flex gap-2"><button class="btn btn-tf-primary" @disabled($orders->isEmpty())>Continue</button><a href="{{ route('business.sales.returns.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </div>
</form>
@endsection
