@props([
    'plan',
    'context' => 'landing',
    'billingCycle' => 'Monthly',
    'currentSubscription' => null,
])

@php
    $monthlyPrice = $plan->priceFor('Monthly');
    $yearlyPrice = $plan->priceFor('Yearly');
    $cycle = $billingCycle === 'Yearly' ? 'Yearly' : 'Monthly';
    $visibleFeatures = collect($plan->features ?? [])
        ->merge($plan->included_modules ?? [])
        ->map(fn ($feature) => trim((string) $feature))
        ->filter()
        ->unique(fn ($feature) => strtolower($feature))
        ->values();
    $savingPercent = $monthlyPrice > 0 && $yearlyPrice > 0 && $yearlyPrice < ($monthlyPrice * 12)
        ? (int) round((1 - ($yearlyPrice / ($monthlyPrice * 12))) * 100)
        : 0;
    $isCurrentPlan = $currentSubscription?->subscription_plan_id === $plan->id;
    $isBusinessOwner = auth()->user()?->role === 'business_owner';
    $currentPlanPrice = $currentSubscription?->plan?->priceFor('Monthly') ?? 0;
    $isUpgrade = $monthlyPrice > $currentPlanPrice;
@endphp

<article
    {{ $attributes->class([
        'tf-card',
        'tf-pricing-card',
        'h-100',
        'd-flex',
        'flex-column',
        'is-recommended' => $plan->is_recommended,
        'is-archived' => (bool) $plan->archived_at,
    ]) }}
    data-plan-card
    data-plan-name="{{ strtolower($plan->name) }}"
    data-plan-status="{{ $plan->status }}"
    data-plan-visibility="{{ $plan->is_public ? 'Public' : 'Private' }}"
    data-plan-yearly="{{ (int) $plan->yearly_price > 0 ? 'yes' : 'no' }}"
>
    @if($plan->is_recommended)
        <div class="tf-pricing-recommended">Recommended</div>
    @endif

    <div class="p-4 d-flex flex-column flex-grow-1">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
            <div>
                <h2 class="h4 mb-1">{{ $plan->name }}</h2>
                @if($context === 'admin')
                    <div class="d-flex flex-wrap gap-1">
                        <span class="tf-badge {{ $plan->status === 'Active' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $plan->status }}</span>
                        <span class="tf-badge tf-badge-info">{{ $plan->is_public ? 'Public' : 'Private' }}</span>
                        @if($plan->archived_at)<span class="tf-badge tf-badge-danger">Archived</span>@endif
                    </div>
                    <div class="tf-muted small mt-2">{{ number_format($plan->active_subscriptions_count ?? $plan->subscriptions_count ?? 0) }} active businesses</div>
                @endif
            </div>
            @if($savingPercent > 0 && $context !== 'admin')
                <span class="tf-pricing-save">Save {{ $savingPercent }}%</span>
            @endif
        </div>

        <p class="tf-muted small mb-3">{{ $plan->short_description ?: 'A flexible plan configured by TradeFlow.' }}</p>

        <div class="tf-pricing-price mb-2" aria-live="polite">
            <span data-plan-monthly-price class="{{ $cycle === 'Yearly' ? 'd-none' : '' }}">Rs {{ number_format($monthlyPrice) }}</span>
            <span data-plan-yearly-price class="{{ $cycle === 'Yearly' ? '' : 'd-none' }}">Rs {{ number_format($yearlyPrice) }}</span>
            <small data-plan-monthly-label class="{{ $cycle === 'Yearly' ? 'd-none' : '' }}">/ month</small>
            <small data-plan-yearly-label class="{{ $cycle === 'Yearly' ? '' : 'd-none' }}">/ year</small>
        </div>

        <div class="tf-pricing-trial mb-3">
            <i class="bi bi-calendar-check"></i>
            {{ $plan->trial_days > 0 ? $plan->trial_days.'-day free trial' : 'No trial period' }}
        </div>

        <ul class="list-unstyled tf-pricing-list mb-4">
            <li><i class="bi bi-check2"></i>{{ number_format($plan->product_limit) }} products</li>
            <li><i class="bi bi-check2"></i>{{ number_format($plan->staff_limit) }} staff members</li>
            <li><i class="bi bi-check2"></i>{{ number_format($plan->order_limit) }} orders</li>
            @foreach($visibleFeatures->take(4) as $feature)
                <li><i class="bi bi-check2"></i>{{ $feature }}</li>
            @endforeach
            @if($visibleFeatures->count() > 4)
                <li class="tf-muted">+ {{ $visibleFeatures->count() - 4 }} more features</li>
            @endif
        </ul>

        @if($context === 'admin')
            <div class="mt-auto">{{ $actions ?? '' }}</div>
        @elseif($context === 'landing')
            @if(! auth()->check())
                <a href="{{ route('register.business', ['plan' => $plan->id, 'billing_cycle' => $cycle, 'source' => 'pricing']) }}" class="btn {{ $plan->is_recommended ? 'btn-tf-primary' : 'btn-outline-primary' }} w-100 mt-auto" data-plan-cta>
                    {{ $plan->trial_days > 0 ? 'Start Free Trial' : 'Choose Plan' }}
                </a>
            @elseif(! $isBusinessOwner)
                <button class="btn btn-outline-secondary w-100 mt-auto" type="button" disabled>Available to Business Owner</button>
            @elseif(! $currentSubscription)
                <a href="{{ route('business.subscription.index', ['plan' => $plan->id, 'billing_cycle' => $cycle]) }}" class="btn btn-tf-primary w-100 mt-auto" data-plan-cta>Choose Plan</a>
            @elseif($isCurrentPlan && $currentSubscription->status === 'Expired')
                <a href="{{ route('business.subscription.index', ['plan' => $plan->id, 'billing_cycle' => $cycle]) }}" class="btn btn-tf-primary w-100 mt-auto" data-plan-cta>Renew Plan</a>
            @elseif($isCurrentPlan)
                <button class="btn btn-outline-secondary w-100 mt-auto" type="button" disabled>{{ $currentSubscription->status === 'Trial' ? 'Current Trial' : 'Current Plan' }}</button>
            @else
                <a href="{{ route('business.subscription.index', ['plan' => $plan->id, 'billing_cycle' => $cycle]) }}" class="btn {{ $currentSubscription->status === 'Expired' || $isUpgrade ? 'btn-tf-primary' : 'btn-outline-primary' }} w-100 mt-auto" data-plan-cta>
                    {{ $currentSubscription->status === 'Expired' ? 'Renew Plan' : ($isUpgrade ? 'Upgrade Plan' : 'Downgrade Plan') }}
                </a>
            @endif
        @endif
    </div>
</article>
