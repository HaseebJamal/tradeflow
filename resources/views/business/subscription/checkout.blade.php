@extends('layouts.dashboard')

@section('page-title', 'Subscription Checkout')
@section('page-subtitle', 'Review your plan and submit payment for verification')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-9">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><h2 class="h5 mb-0">Checkout</h2><a href="{{ route('business.subscription.index') }}" class="btn btn-outline-secondary">Back to Plans</a></div>
        <form method="POST" action="{{ route('business.subscription.payments.store') }}" enctype="multipart/form-data" class="tf-card p-4" data-subscription-checkout>@csrf
            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
            <div class="row g-3 mb-4">
                <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Business</small><strong>{{ $business->business_name }}</strong><small class="d-block tf-muted mt-1">{{ $quote['subscription']?->status ?? 'No current subscription' }}</small></div></div>
                <div class="col-md-6"><div class="border rounded p-3 h-100"><small class="tf-muted d-block">Selected plan</small><strong>{{ $plan->name }}</strong><small class="d-block tf-muted mt-1">{{ $plan->short_description }}</small></div></div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-4"><label class="form-label">Billing cycle</label><select name="billing_cycle" class="form-select" required data-checkout-cycle><option value="Monthly" @selected($cycle === 'Monthly')>Monthly</option><option value="Yearly" @selected($cycle === 'Yearly')>Yearly</option></select></div>
                <div class="col-md-4"><label class="form-label">Subscription period</label><div class="form-control bg-light">{{ \Illuminate\Support\Carbon::parse($quote['period_starts_at'])->format('d M, Y') }} – {{ \Illuminate\Support\Carbon::parse($quote['period_ends_at'])->format('d M, Y') }}</div></div>
                <div class="col-md-4"><label class="form-label">Total payable</label><div class="form-control bg-light fw-bold">Rs {{ number_format($quote['amount'], 2) }}</div><small class="tf-muted">Calculated from the current plan.</small></div>
            </div>
            <div class="border-top pt-4 row g-3">
                <div class="col-md-6"><label class="form-label">Payment method <span class="text-danger">*</span></label><select name="payment_method" class="form-select" required><option value="">Select payment method</option>@foreach(['Jazz Cash','Easypaisa','Bank Transfer','Cash'] as $method)<option value="{{ $method }}">{{ $method }}</option>@endforeach</select><div class="form-text">All methods, including Cash, require authorized verification.</div></div>
                <div class="col-md-6"><label class="form-label">Transaction / reference ID</label><input name="transaction_reference" maxlength="120" class="form-control" placeholder="Required for digital or bank payments"></div>
                <div class="col-md-6"><label class="form-label">Payment proof</label><input name="payment_proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" class="form-control"><div class="form-text">JPG, PNG, WebP, or PDF. Maximum 5 MB.</div></div>
                <div class="col-md-6"><label class="form-label">Payment note</label><textarea name="note" rows="2" maxlength="1000" class="form-control" placeholder="Optional note for verification"></textarea></div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4"><a href="{{ route('business.subscription.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-tf-primary" type="submit">Submit Payment for Verification</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>document.addEventListener('DOMContentLoaded', function () { document.querySelector('[data-checkout-cycle]')?.addEventListener('change', function () { const url = new URL(window.location.href); url.searchParams.set('billing_cycle', this.value); window.location.assign(url.toString()); }); const form=document.querySelector('[data-subscription-checkout]'); form?.addEventListener('submit', function (event) { if (form.dataset.confirmed || !window.Swal) return; event.preventDefault(); Swal.fire({icon:'question', title:'Submit payment for verification?', text:'Your subscription will remain unchanged until an authorized administrator verifies this payment.', showCancelButton:true, confirmButtonText:'Submit payment'}).then(result => { if (result.isConfirmed) { form.dataset.confirmed='1'; form.submit(); } }); }); });</script>
@endpush
