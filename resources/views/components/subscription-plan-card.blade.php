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
    $usesLandingPresentation = in_array($context, ['landing', 'dashboard'], true);
@endphp

<article
    {{ $attributes->class([
        'tf-card', 'tf-pricing-card', 'pp-pricing-card' => $usesLandingPresentation, 'h-100', 'd-flex', 'flex-column',
        'is-recommended' => $plan->is_recommended,
        'is-archived' => (bool) $plan->archived_at,
    ]) }}
    data-plan-card
    data-plan-name="{{ strtolower($plan->name) }}"
    data-plan-status="{{ $plan->status }}"
    data-plan-visibility="{{ $plan->is_public ? 'Public' : 'Private' }}"
    data-plan-yearly="{{ (int) $plan->yearly_price > 0 ? 'yes' : 'no' }}"
>
    @if($usesLandingPresentation)
        @if($plan->is_recommended)<span class="pp-plan-popular">Most Popular</span>@endif
        <div class="pp-plan-content d-flex flex-column h-100">
            <header class="pp-plan-header">
                <h2>{{ $plan->name }}</h2>
                <p>{{ $plan->short_description ?: 'A flexible plan configured for your business.' }}</p>
            </header>

            <div class="pp-plan-price" aria-live="polite">
                <span class="pp-plan-currency">Rs</span>
                <span data-plan-monthly-price class="pp-plan-amount {{ $cycle === 'Yearly' ? 'd-none' : '' }}">{{ number_format($monthlyPrice) }}</span>
                <span data-plan-yearly-price class="pp-plan-amount {{ $cycle === 'Yearly' ? '' : 'd-none' }}">{{ number_format($yearlyPrice) }}</span>
                <span data-plan-monthly-label class="pp-plan-period {{ $cycle === 'Yearly' ? 'd-none' : '' }}">/ month</span>
                <span data-plan-yearly-label class="pp-plan-period {{ $cycle === 'Yearly' ? '' : 'd-none' }}">/ year</span>
            </div>
            <p class="pp-plan-trial"><i class="bi bi-calendar-check" aria-hidden="true"></i> {{ $plan->trial_days > 0 ? $plan->trial_days.'-day free trial included' : 'Get started today' }}@if($savingPercent > 0)<span>Save {{ $savingPercent }}% yearly</span>@endif</p>

            <div class="pp-plan-action">
                @if($context === 'dashboard' && isset($actions))
                    {{ $actions }}
                @elseif(! auth()->check())
                    <a href="{{ route('register.business', ['plan' => $plan->id, 'billing_cycle' => $cycle, 'source' => 'pricing']) }}" class="btn pp-plan-cta" data-plan-cta>{{ $plan->trial_days > 0 ? 'Start Free Trial' : 'Choose Plan' }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                @elseif(! $isBusinessOwner)
                    <button class="btn pp-plan-cta is-disabled" type="button" disabled>Available to Business Owner</button>
                @elseif(! $currentSubscription)
                    <a href="{{ route('business.subscription.index', ['plan' => $plan->id, 'billing_cycle' => $cycle]) }}" class="btn pp-plan-cta" data-plan-cta>Choose Plan <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                @elseif($isCurrentPlan && $currentSubscription->status === 'Expired')
                    <a href="{{ route('business.subscription.index', ['plan' => $plan->id, 'billing_cycle' => $cycle]) }}" class="btn pp-plan-cta" data-plan-cta>Renew Plan <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                @elseif($isCurrentPlan)
                    <button class="btn pp-plan-cta is-disabled" type="button" disabled>{{ $currentSubscription->status === 'Trial' ? 'Current Trial' : 'Current Plan' }}</button>
                @else
                    <a href="{{ route('business.subscription.index', ['plan' => $plan->id, 'billing_cycle' => $cycle]) }}" class="btn pp-plan-cta" data-plan-cta>{{ $currentSubscription->status === 'Expired' ? 'Renew Plan' : ($isUpgrade ? 'Upgrade Plan' : 'Downgrade Plan') }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                @endif
            </div>

            <div class="pp-plan-divider"></div>
            <div class="pp-plan-features">
                <p>Everything you need to get started:</p>
                <ul>
                    <li><i class="bi bi-check2" aria-hidden="true"></i><span>{{ number_format($plan->product_limit) }} products</span></li>
                    <li><i class="bi bi-check2" aria-hidden="true"></i><span>{{ number_format($plan->staff_limit) }} staff members</span></li>
                    <li><i class="bi bi-check2" aria-hidden="true"></i><span>{{ number_format($plan->order_limit) }} orders</span></li>
                    @foreach($visibleFeatures->take(4) as $feature)<li><i class="bi bi-check2" aria-hidden="true"></i><span>{{ $feature }}</span></li>@endforeach
                    @if($visibleFeatures->count() > 4)<li class="pp-plan-more">+ {{ $visibleFeatures->count() - 4 }} more features</li>@endif
                </ul>
            </div>
            <small class="pp-plan-helper mt-auto">Manage your plan anytime from your workspace.</small>
        </div>
    @else
        {{-- The administration card retains its compact management workflow. --}}
        @if($plan->is_recommended)<div class="tf-pricing-recommended">Recommended</div>@endif
        <div class="p-4 d-flex flex-column flex-grow-1">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2"><div><h2 class="h4 mb-1">{{ $plan->name }}</h2><div class="d-flex flex-wrap gap-1"><span class="tf-badge {{ $plan->status === 'Active' ? 'tf-badge-success' : 'tf-badge-warning' }}">{{ $plan->status }}</span><span class="tf-badge tf-badge-info">{{ $plan->is_public ? 'Public' : 'Private' }}</span>@if($plan->archived_at)<span class="tf-badge tf-badge-danger">Archived</span>@endif</div><div class="tf-muted small mt-2">{{ number_format($plan->active_subscriptions_count ?? $plan->subscriptions_count ?? 0) }} active businesses</div></div></div>
            <p class="tf-muted small mb-3">{{ $plan->short_description ?: 'A flexible plan configured by TradeFlow.' }}</p>
            <div class="tf-pricing-price mb-2"><span>Rs {{ number_format($cycle === 'Yearly' ? $yearlyPrice : $monthlyPrice) }}</span><small>/ {{ strtolower($cycle) }}</small></div>
            <ul class="list-unstyled tf-pricing-list mb-4"><li><i class="bi bi-check2"></i>{{ number_format($plan->product_limit) }} products</li><li><i class="bi bi-check2"></i>{{ number_format($plan->staff_limit) }} staff members</li><li><i class="bi bi-check2"></i>{{ number_format($plan->order_limit) }} orders</li></ul>
            <div class="mt-auto">{{ $actions ?? '' }}</div>
        </div>
    @endif
</article>
