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

        <div class="tf-pricing-cycle mb-4" data-pricing-cycle>
            <button type="button" class="active" data-cycle="Monthly">Monthly</button>
            <button type="button" data-cycle="Yearly">Yearly <span class="tf-pricing-save">Save 20%</span></button>
        </div>

        <div class="row g-4 justify-content-center">
            @forelse($plans as $plan)
                @php($monthly = $plan->priceFor('Monthly'))
                @php($yearly = $plan->priceFor('Yearly'))
                <div class="col-md-6 col-xl-4">
                    <article class="tf-card tf-pricing-card h-100 {{ $plan->is_recommended ? 'is-recommended' : '' }}">
                        @if($plan->is_recommended)<div class="tf-pricing-recommended">Recommended</div>@endif
                        <div class="p-4 d-flex flex-column h-100">
                            <h2 class="h4 mb-1">{{ $plan->name }}</h2>
                            <p class="tf-muted small mb-3">{{ $plan->short_description ?: 'A practical package for growing business operations.' }}</p>
                            <div class="tf-pricing-price mb-2">
                                <span data-monthly-price>Rs {{ number_format($monthly) }}</span>
                                <span data-yearly-price class="d-none">Rs {{ number_format($yearly) }}</span>
                                <small data-monthly-label>/ month</small><small data-yearly-label class="d-none">/ year</small>
                            </div>
                            <div class="tf-pricing-trial mb-3"><i class="bi bi-calendar-check"></i>{{ $plan->trial_days }}-day free trial</div>
                            <ul class="list-unstyled tf-pricing-list mb-4">
                                <li><i class="bi bi-check2"></i>{{ number_format($plan->product_limit) }} products</li>
                                <li><i class="bi bi-check2"></i>{{ number_format($plan->staff_limit) }} staff members</li>
                                <li><i class="bi bi-check2"></i>{{ number_format($plan->order_limit) }} orders</li>
                                @foreach(array_slice($plan->features ?? $plan->included_modules ?? [], 0, 4) as $feature)
                                    <li><i class="bi bi-check2"></i>{{ $feature }}</li>
                                @endforeach
                            </ul>
                            @if(!$currentSubscription)
                                <a href="{{ route('register.business', ['plan' => $plan->id, 'billing_cycle' => 'Monthly']) }}" class="btn {{ $plan->is_recommended ? 'btn-tf-primary' : 'btn-outline-primary' }} w-100 mt-auto" data-plan-cta data-plan-id="{{ $plan->id }}">{{ auth()->check() ? 'Choose Plan' : 'Start Free Trial' }}</a>
                            @elseif($currentSubscription->subscription_plan_id === $plan->id)
                                <button class="btn btn-outline-secondary w-100 mt-auto" disabled>{{ $currentSubscription->status === 'Trial' ? 'Current Trial' : 'Current Plan' }}</button>
                            @elseif(auth()->user()?->role === 'business_owner')
                                @php($isUpgrade = $plan->priceFor('Monthly') > $currentSubscription->plan?->priceFor('Monthly'))
                                <a href="{{ route('business.subscription.index') }}" class="btn {{ $isUpgrade ? 'btn-tf-primary' : 'btn-outline-primary' }} w-100 mt-auto">{{ $currentSubscription->status === 'Expired' ? 'Renew Plan' : ($isUpgrade ? 'Upgrade Plan' : 'Downgrade Plan') }}</a>
                            @else
                                <a href="{{ route('business.subscription.index') }}" class="btn btn-outline-primary w-100 mt-auto">View Subscription</a>
                            @endif
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-lg-7"><div class="tf-card p-5 text-center"><i class="bi bi-box-seam fs-2 text-muted"></i><h2 class="h5 mt-3">Plans are being prepared</h2><p class="tf-muted mb-0">Please check back shortly for available TradeFlow plans.</p></div></div>
            @endforelse
        </div>

        @if($plans->count())
            <section class="tf-pricing-compare mt-5">
                <h2 class="h4 mb-3">Compare plans</h2>
                <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Feature</th>@foreach($plans as $plan)<th>{{ $plan->name }}</th>@endforeach</tr></thead><tbody>
                    @foreach(['Products' => 'product_limit', 'Staff' => 'staff_limit', 'Orders' => 'order_limit', 'Included modules' => 'included_modules'] as $label => $field)
                        <tr><th>{{ $label }}</th>@foreach($plans as $plan)<td>@if($field === 'included_modules'){{ count($plan->included_modules ?? []) ? implode(', ', $plan->included_modules) : 'Included modules configured by administrator' }}@else{{ number_format($plan->{$field}) }}@endif</td>@endforeach</tr>
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
    const root = document.querySelector('[data-pricing-cycle]');
    if (!root || root.dataset.ready) return;
    root.dataset.ready = '1';
    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-cycle]');
        if (!button) return;
        const yearly = button.dataset.cycle === 'Yearly';
        root.querySelectorAll('[data-cycle]').forEach((item) => item.classList.toggle('active', item === button));
        document.querySelectorAll('[data-monthly-price], [data-monthly-label]').forEach((item) => item.classList.toggle('d-none', yearly));
        document.querySelectorAll('[data-yearly-price], [data-yearly-label]').forEach((item) => item.classList.toggle('d-none', !yearly));
        document.querySelectorAll('[data-plan-cta]').forEach((link) => {
            const url = new URL(link.href, window.location.origin);
            url.searchParams.set('billing_cycle', yearly ? 'Yearly' : 'Monthly');
            link.href = url.toString();
        });
    });
});
</script>
@endpush
