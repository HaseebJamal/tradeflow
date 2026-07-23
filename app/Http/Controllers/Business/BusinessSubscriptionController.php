<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubscriptionChangeRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\SubscriptionStatusNotification;
use App\Services\CompanyPermissionService;
use App\Services\SubscriptionManagementAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $business = $this->businessFor($request);
        $this->assertSubscriptionManager($request, $business);

        return view('business.subscription.index', [
            'business' => $business,
            'subscription' => $business->subscription,
            'plans' => SubscriptionPlan::publicActive()->orderBy('sort_order')->orderBy('monthly_price')->get(),
            'requests' => SubscriptionChangeRequest::with(['currentPlan', 'requestedPlan'])
                ->where('business_id', $business->id)
                ->latest()
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function storeRequest(Request $request)
    {
        $business = $this->businessFor($request);
        $this->assertSubscriptionManager($request, $business);
        $data = $request->validate([
            'requested_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:Monthly,Yearly'],
            'payment_method' => ['required', 'in:Cash,Bank Transfer,JazzCash Manual,Easypaisa Manual'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $plan = SubscriptionPlan::publicActive()->findOrFail($data['requested_plan_id']);
        $subscription = $business->subscription;
        $type = $this->requestType($subscription, $plan);
        if ($type === 'Current') {
            throw ValidationException::withMessages(['requested_plan_id' => 'This plan is already active for your business.']);
        }
        $this->assertPermission($request, 'subscriptions.'.strtolower($type), $business, $type === 'Subscription' ? 'subscriptions.request' : null);
        if ($type === 'Downgrade') $this->assertDowngradeFits($business, $plan);

        DB::transaction(function () use ($request, $business, $plan, $data, $type) {
            $lockedBusiness = Business::whereKey($business->id)->lockForUpdate()->firstOrFail();
            $lockedSubscription = $lockedBusiness->subscription()->with('plan')->lockForUpdate()->first();
            if (SubscriptionChangeRequest::where('business_id', $lockedBusiness->id)->where('status', 'Pending')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['subscription' => 'A subscription request is already pending.']);
            }
            if ($lockedSubscription
                && $lockedSubscription->subscription_plan_id === $plan->id
                && in_array($lockedSubscription->status, ['Trial', 'Active', 'Expiring'], true)) {
                throw ValidationException::withMessages(['requested_plan_id' => 'This plan is already active for your business.']);
            }

            $change = SubscriptionChangeRequest::create([
                'business_id' => $lockedBusiness->id, 'subscription_id' => $lockedSubscription?->id,
                'current_plan_id' => $lockedSubscription?->subscription_plan_id, 'requested_plan_id' => $plan->id,
                'requested_by' => $request->user()->id, 'type' => $type, 'billing_cycle' => $data['billing_cycle'],
                'expected_amount' => $plan->priceFor($data['billing_cycle']), 'payment_method' => $data['payment_method'],
                'trial_eligible' => ! $lockedSubscription && (int) $plan->trial_days > 0,
                'trial_days' => ! $lockedSubscription ? (int) $plan->trial_days : null,
                'starts_at' => now()->toDateString(),
                'ends_at' => ($data['billing_cycle'] === 'Yearly' ? now()->addYear() : now()->addMonth())->toDateString(),
                'note' => $data['note'] ?? null,
            ]);
            User::where('role', 'super_admin')->where('status', 'active')->get()
                ->each(fn (User $admin) => $admin->notify(new SubscriptionStatusNotification($type === 'Subscription' ? 'New Subscription Request' : $type.' Request', $lockedBusiness->business_name.' requested the '.$plan->name.' plan.', $lockedBusiness->id, $change->id)));
            $lockedBusiness->owner?->notify(new SubscriptionStatusNotification('Subscription Request Submitted', 'Your '.$plan->name.' subscription request was submitted for review.', $lockedBusiness->id));
            return $change;
        });

        return redirect()->route('business.subscription.index')->with('success', $type.' request submitted successfully.');
    }

    private function requestType($current, SubscriptionPlan $requested): string
    {
        if (! $current?->plan) return 'Subscription';
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

    public function cancelRequest(Request $request, SubscriptionChangeRequest $changeRequest)
    {
        $business = $this->businessFor($request);
        $this->assertSubscriptionManager($request, $business);
        $this->assertPermission($request, 'subscriptions.cancel', $business);
        abort_unless($changeRequest->business_id === $business->id, 403);
        abort_unless($changeRequest->status === 'Pending', 422, 'Only pending subscription requests can be cancelled.');

        $changeRequest->update(['status' => 'Cancelled', 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id]);

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
