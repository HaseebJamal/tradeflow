@extends('layouts.public')

@section('title', 'Pricing | TradeFlow')

@section('content')
<section class="tf-section tf-pricing-page" style="padding-top:130px">
    <div class="container">
        <div class="text-center mx-auto mb-4" style="max-width:680px">
            <span class="tf-badge tf-badge-info mb-2">Flexible plans</span>
            <h1 class="fw-bold mb-2">Pricing that grows with your business</h1>
            <p class="tf-muted mb-0">Choose a plan with a {{ $plans->first()?->trial_days ?? 14 }}-day trial. You can upgrade when your business needs more room.</p>
        </div>

        <div class="tf-pricing-cycle mb-4" data-subscription-pricing>
            <button type="button" class="active" data-cycle="Monthly">Monthly</button>
            <button type="button" data-cycle="Yearly">Yearly</button>
        </div>

        <div class="row g-4 justify-content-center">
            @forelse($plans as $plan)
                <div class="col-md-6 col-xl-4">
                    <x-subscription-plan-card :plan="$plan" context="landing" :current-subscription="$currentSubscription" />
                </div>
            @empty
                <div class="col-lg-7"><div class="tf-card p-5 text-center"><i class="bi bi-box-seam fs-2 text-muted"></i><h2 class="h5 mt-3">Plans are being prepared</h2><p class="tf-muted mb-0">Please check back shortly for available {{ $platformSettings->company_name }} plans.</p></div></div>
            @endforelse
        </div>

        @if($plans->count())
            <section class="tf-pricing-compare mt-5">
                <h2 class="h4 mb-3">Compare plans</h2>
                <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Feature</th>@foreach($plans as $plan)<th>{{ $plan->name }}</th>@endforeach</tr></thead><tbody>
                    @foreach(['Products' => 'product_limit', 'Staff' => 'staff_limit', 'Orders' => 'order_limit'] as $label => $field)
                        <tr><th>{{ $label }}</th>@foreach($plans as $plan)<td>{{ number_format($plan->{$field}) }}</td>@endforeach</tr>
                    @endforeach
                </tbody></table></div>
            </section>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-subscription-pricing]');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = '1';
    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-cycle]');
        if (!button) return;
        const yearly = button.dataset.cycle === 'Yearly';
        root.querySelectorAll('[data-cycle]').forEach((item) => item.classList.toggle('active', item === button));
        document.querySelectorAll('[data-plan-monthly-price], [data-plan-monthly-label]').forEach((item) => item.classList.toggle('d-none', yearly));
        document.querySelectorAll('[data-plan-yearly-price], [data-plan-yearly-label]').forEach((item) => item.classList.toggle('d-none', !yearly));
        document.querySelectorAll('[data-plan-cta]').forEach((link) => {
            const url = new URL(link.href, window.location.origin);
            url.searchParams.set('billing_cycle', yearly ? 'Yearly' : 'Monthly');
            link.href = url.toString();
        });
    });
});
</script>
@endpush
