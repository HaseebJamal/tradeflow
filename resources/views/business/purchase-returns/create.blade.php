@extends('layouts.dashboard')
@section('page-title', 'New Purchase Return')
@section('page-subtitle', 'Return received goods to a supplier and automatically reverse stock and payables')
@section('content')
@if($purchase)
    @include('business.purchase-returns._form', ['purchase' => $purchase])
@else
    <form method="POST" action="{{ route('business.purchase-returns.start') }}" class="tf-card p-4">@csrf
        <div class="row g-3 align-items-end">
            <div class="col-md-8"><label class="form-label">Received purchase</label><select name="purchase_id" class="form-select" required autofocus><option value="">Select purchase</option>@foreach($purchases as $availablePurchase)<option value="{{ $availablePurchase->id }}">{{ $availablePurchase->purchase_number }} — {{ $availablePurchase->supplier?->supplier_name }} ({{ $availablePurchase->purchase_date?->format('d M Y') }})</option>@endforeach</select>@error('purchase_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <div class="col-md-4 d-flex gap-2"><button class="btn btn-tf-primary">Continue</button><a href="{{ route('business.purchase-returns.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
        </div>
    </form>
@endif
@endsection
