<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Order;
use App\Models\PlatformPayment;
use App\Models\Product;
use App\Models\SubscriptionChangeRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\SubscriptionStatusNotification;
use App\Services\CompanyPermissionService;
use App\Services\BusinessActivityService;
use App\Services\SubscriptionManagementAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessSubscriptionController extends Controller
{
    public function __construct(private readonly BusinessActivityService $activity)
    {
    }

    public function index(Request $request)
    {
        $business = $this->businessFor($request);
        $this->assertSubscriptionManager($request, $business);

        return view('business.subscription.index', [
            'business' => $business,
            'subscription' => $business->subscription,
            'plans' => SubscriptionPlan::publicActive()->orderBy('sort_order')->orderBy('monthly_price')->get(),
            'requests' => SubscriptionChangeRequest::with(['currentPlan', 'requestedPlan', 'reviewer'])
                ->where('business_id', $business->id)
                ->latest()
                ->paginate(12)
                ->withQueryString(),
            'billingHistory' => PlatformPayment::query()
                ->where('business_id', $business->id)
                ->latest('paid_at')
                ->paginate(12, ['*'], 'billing_page')
                ->withQueryString(),
        ]);
    }

    public function storeRequest(Request $request)
    {
        $business = $this->businessFor($request);
        $this->assertSubscriptionManager($request, $business);
        $data = $request->validate([
            'request_type' => ['nullable', 'in:New Subscription,Upgrade,Downgrade,Billing Cycle Change,Payment Method Change,Renewal,Cancellation,Resume Cancellation'],
            'requested_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'billing_cycle' => ['nullable', 'in:Monthly,Yearly'],
            'payment_method' => ['nullable', 'in:Cash,Bank Transfer,JazzCash Manual,Easypaisa Manual'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $subscription = $business->subscription;
        [$type, $plan] = $this->resolveRequest($data, $subscription);
        $this->assertPermissionForType($request, $type, $business);

        $change = DB::transaction(function () use ($request, $business, $plan, $data, $type) {
            $lockedBusiness = Business::whereKey($business->id)->lockForUpdate()->firstOrFail();
            $lockedSubscription = $lockedBusiness->subscription()->with('plan')->lockForUpdate()->first();
            [$lockedType, $lockedPlan, $lockedCycle, $lockedPaymentMethod, $lockedEffectiveAt] = $this->resolveRequest($data, $lockedSubscription);

            if ($lockedType !== $type || $lockedPlan->id !== $plan->id) {
                throw ValidationException::withMessages([
                    'subscription' => 'The subscription changed while your request was being processed. Please try again.',
                ]);
            }

            if (SubscriptionChangeRequest::where('business_id', $lockedBusiness->id)->where('status', 'Pending')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['subscription' => 'A subscription request is already pending.']);
            }

            if ($lockedType === 'Downgrade') {
                $this->assertDowngradeFits($lockedBusiness, $lockedPlan);
            }

            if (in_array($lockedType, ['New Subscription', 'Upgrade', 'Downgrade'], true)
                && $lockedSubscription
                && $lockedSubscription->subscription_plan_id === $lockedPlan->id
                && in_array($lockedSubscription->status, ['Trial', 'Active', 'Expiring'], true)) {
                throw ValidationException::withMessages(['requested_plan_id' => 'This plan is already active for your business.']);
            }

            $change = SubscriptionChangeRequest::create([
                'business_id' => $lockedBusiness->id, 'subscription_id' => $lockedSubscription?->id,
                'current_plan_id' => $lockedSubscription?->subscription_plan_id, 'requested_plan_id' => $lockedPlan->id,
                'requested_by' => $request->user()->id, 'type' => $lockedType, 'billing_cycle' => $lockedCycle,
                'expected_amount' => $lockedPlan->priceFor($lockedCycle), 'payment_method' => $lockedPaymentMethod,
                'trial_eligible' => $lockedType === 'New Subscription' && ! $lockedSubscription && (int) $lockedPlan->trial_days > 0,
                'trial_days' => $lockedType === 'New Subscription' && ! $lockedSubscription ? (int) $lockedPlan->trial_days : null,
                'starts_at' => now()->toDateString(),
                'ends_at' => ($lockedCycle === 'Yearly' ? now()->addYear() : now()->addMonth())->toDateString(),
                'effective_at' => $lockedEffectiveAt,
                'note' => $data['note'] ?? null,
            ]);
            User::where('role', 'super_admin')->where('status', 'active')->get()
                ->each(fn (User $admin) => $admin->notify(new SubscriptionStatusNotification($lockedType.' Request', $lockedBusiness->business_name.' submitted a '.$lockedType.' request.', $lockedBusiness->id, $change->id)));
            $lockedBusiness->owner?->notify(new SubscriptionStatusNotification('Subscription Request Submitted', 'Your '.$lockedType.' request was submitted for review.', $lockedBusiness->id));
            return $change;
        });

        $this->activity->record($business->id, 'Subscriptions', $type.' Requested', $change->id, null, [
            'current_plan_id' => $change->current_plan_id,
            'requested_plan_id' => $change->requested_plan_id,
            'billing_cycle' => $change->billing_cycle,
            'effective_at' => $change->effective_at?->toDateString(),
        ]);

        return redirect()->route('business.subscription.index')->with('success', $type.' request submitted successfully.');
    }

    private function requestType($current, SubscriptionPlan $requested): string
    {
        if (! $current?->plan) return 'New Subscription';
        if ($current->subscription_plan_id === $requested->id && in_array($current->status, ['Expired', 'Cancelled'], true)) {
            return 'Renew';
        }

        $currentPrice = $current->plan->priceFor('Monthly');
        $requestedPrice = $requested->priceFor('Monthly');
        if ($currentPrice === $requestedPrice) {
            return 'Current';
        }

        return $requestedPrice > $currentPrice ? 'Upgrade' : 'Downgrade';
    }

    private function resolveRequest(array $data, $subscription): array
    {
        $type = $data['request_type'] ?? null;
        $currentPlan = $subscription?->plan;
        $requestedPlanId = $data['requested_plan_id'] ?? $currentPlan?->id;
        $plan = $requestedPlanId ? SubscriptionPlan::publicActive()->findOrFail($requestedPlanId) : null;

        if (! $type) {
            if (! $plan) {
                throw ValidationException::withMessages(['requested_plan_id' => 'Please select a subscription plan.']);
            }
            $type = $this->requestType($subscription, $plan);
        }

        if (! $plan || ! $currentPlan && $type !== 'New Subscription') {
            throw ValidationException::withMessages(['subscription' => 'A current subscription is required for this request.']);
        }

        $cycle = $data['billing_cycle'] ?? $subscription?->billing_cycle ?? 'Monthly';
        $paymentMethod = $data['payment_method'] ?? $subscription?->payment_method;

        if (in_array($type, ['New Subscription', 'Upgrade', 'Downgrade'], true)) {
            $derived = $this->requestType($subscription, $plan);
            if ($derived === 'Current' || $derived !== $type) {
                throw ValidationException::withMessages(['requested_plan_id' => 'The selected plan is not valid for this request.']);
            }
        }

        if ($type === 'Billing Cycle Change') {
            if ($plan->id !== $currentPlan->id || $cycle === $subscription->billing_cycle) {
                throw ValidationException::withMessages(['billing_cycle' => 'Select a different billing cycle for the current plan.']);
            }
        }

        if ($type === 'Payment Method Change') {
            if (! $paymentMethod || $paymentMethod === $subscription->payment_method) {
                throw ValidationException::withMessages(['payment_method' => 'Select a different payment method.']);
            }
        }

        if ($type === 'Renewal' && ! in_array($subscription->status, ['Trial', 'Active', 'Expiring', 'Expired', 'Cancelled'], true)) {
            throw ValidationException::withMessages(['subscription' => 'This subscription cannot be renewed in its current status.']);
        }

        if ($type === 'Cancellation') {
            if (! in_array($subscription->status, ['Trial', 'Active', 'Expiring'], true) || $subscription->cancellation_scheduled_at || ! $subscription->ends_at) {
                throw ValidationException::withMessages(['subscription' => 'Cancellation is not available for this subscription.']);
            }
            if (blank($data['note'] ?? null)) {
                throw ValidationException::withMessages(['note' => 'Please provide a cancellation reason.']);
            }
        }

        if ($type === 'Resume Cancellation' && ! $subscription->cancellation_scheduled_at) {
            throw ValidationException::withMessages(['subscription' => 'There is no scheduled cancellation to resume.']);
        }

        $effectiveAt = match ($type) {
            'Downgrade' => $subscription?->ends_at?->toDateString() ?? now()->toDateString(),
            'Cancellation' => $subscription?->ends_at?->toDateString(),
            'Resume Cancellation' => now()->toDateString(),
            default => now()->toDateString(),
        };

        return [$type, $plan, $cycle, $paymentMethod, $effectiveAt];
    }

    private function assertPermissionForType(Request $request, string $type, Business $business): void
    {
        if ($request->user()?->role === 'business_owner') {
            return;
        }

        if (app(CompanyPermissionService::class)->allowsUser($request->user(), 'subscriptions.manage', $business)) {
            return;
        }

        $permission = match ($type) {
            'New Subscription' => 'subscriptions.request',
            'Renewal' => 'subscriptions.renew',
            'Cancellation' => 'subscriptions.cancel',
            'Billing Cycle Change' => 'subscriptions.change_billing_cycle',
            'Payment Method Change' => 'subscriptions.change_payment_method',
            'Resume Cancellation' => 'subscriptions.resume_cancellation',
            default => 'subscriptions.'.strtolower(str_replace(' ', '_', $type)),
        };

        $this->assertPermission($request, $permission, $business);
    }

    public function cancelRequest(Request $request, SubscriptionChangeRequest $changeRequest)
    {
        $business = $this->businessFor($request);
        $this->assertSubscriptionManager($request, $business);
        $this->assertPermission($request, 'subscriptions.cancel', $business);
        abort_unless($changeRequest->business_id === $business->id, 403);

        $changeRequest = DB::transaction(function () use ($changeRequest, $business, $request) {
            $lockedRequest = SubscriptionChangeRequest::where('business_id', $business->id)
                ->lockForUpdate()
                ->findOrFail($changeRequest->id);
            abort_unless($lockedRequest->status === 'Pending', 422, 'Only pending subscription requests can be cancelled.');
            $lockedRequest->update(['status' => 'Cancelled', 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id]);

            return $lockedRequest->fresh();
        });

        $this->activity->record($business->id, 'Subscriptions', 'Subscription Request Cancelled', $changeRequest->id, ['status' => 'Pending'], ['status' => 'Cancelled']);
        User::where('role', 'super_admin')->where('status', 'active')->get()
            ->each(fn (User $admin) => $admin->notify(new SubscriptionStatusNotification('Subscription Request Cancelled', $business->business_name.' cancelled a '.$changeRequest->type.' request.', $business->id, $changeRequest->id)));

        return back()->with('success', 'Subscription request cancelled.');
    }

    private function businessFor(Request $request): Business
    {
        return Business::with('subscription.plan')->findOrFail($request->user()->business_id);
    }

    private function assertPermission(Request $request, string $permission, Business $business, ?string $fallback = null): void
    {
        $permissions = app(CompanyPermissionService::class);
        if ($permissions->allowsUser($request->user(), $permission, $business)) {
            return;
        }

        abort_unless($fallback && $permissions->allowsUser($request->user(), $fallback, $business), 403);
    }

    private function assertSubscriptionManager(Request $request, Business $business): void
    {
        abort_unless(
            app(SubscriptionManagementAccessService::class)->canManage($request->user(), $business),
            403
        );
    }

    private function assertDowngradeFits(Business $business, SubscriptionPlan $plan): void
    {
        $staff = User::where('business_id', $business->id)->where('role', '!=', 'business_owner')->where('status', '!=', 'archived')->count();
        if (($plan->product_limit && Product::where('business_id', $business->id)->count() > $plan->product_limit)
            || ($plan->staff_limit && $staff > $plan->staff_limit)
            || ($plan->order_limit && Order::where('business_id', $business->id)->count() > $plan->order_limit)) {
            throw ValidationException::withMessages(['requested_plan_id' => 'Your current usage exceeds the selected plan limits. Reduce usage before downgrading.']);
        }
    }
}
