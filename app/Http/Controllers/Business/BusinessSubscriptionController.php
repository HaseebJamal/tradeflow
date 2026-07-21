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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $business = Business::with('subscription.plan')->findOrFail($request->user()->business_id);
        abort_unless($request->user()->role === 'business_owner', 403);

        return view('business.subscription.index', [
            'business' => $business,
            'subscription' => $business->subscription,
            'plans' => SubscriptionPlan::publicActive()->orderBy('sort_order')->orderBy('monthly_price')->get(),
            'requests' => SubscriptionChangeRequest::with(['currentPlan', 'requestedPlan'])->where('business_id', $business->id)->latest()->paginate(10),
        ]);
    }

    public function storeRequest(Request $request)
    {
        $business = Business::with('subscription.plan')->findOrFail($request->user()->business_id);
        abort_unless($request->user()->role === 'business_owner', 403);
        $data = $request->validate([
            'requested_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:Monthly,Yearly'],
            'payment_method' => ['nullable', 'in:Cash,Bank Transfer,JazzCash Manual,Easypaisa Manual'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $plan = SubscriptionPlan::publicActive()->findOrFail($data['requested_plan_id']);
        $subscription = $business->subscription;
        $type = $this->requestType($subscription?->plan, $plan);
        if ($type === 'Current') {
            throw ValidationException::withMessages(['requested_plan_id' => 'This is already your current plan.']);
        }
        if ($type === 'Downgrade') $this->assertDowngradeFits($business, $plan);

        $change = DB::transaction(function () use ($request, $business, $subscription, $plan, $data, $type) {
            $change = SubscriptionChangeRequest::create([
                'business_id' => $business->id, 'subscription_id' => $subscription?->id,
                'current_plan_id' => $subscription?->subscription_plan_id, 'requested_plan_id' => $plan->id,
                'requested_by' => $request->user()->id, 'type' => $type, 'billing_cycle' => $data['billing_cycle'],
                'expected_amount' => $plan->priceFor($data['billing_cycle']), 'payment_method' => $data['payment_method'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            User::where('role', 'super_admin')->where('status', 'active')->get()
                ->each(fn (User $admin) => $admin->notify(new SubscriptionStatusNotification($type.' Requested', $business->business_name.' requested the '.$plan->name.' plan.', $business->id)));
            return $change;
        });

        return redirect()->route('business.subscription.index')->with('success', $type.' request submitted successfully.');
    }

    private function requestType($current, SubscriptionPlan $requested): string
    {
        if (! $current) return 'Subscription';
        $currentPrice = $current->priceFor('Monthly');
        $requestedPrice = $requested->priceFor('Monthly');
        return $currentPrice === $requestedPrice ? 'Current' : ($requestedPrice > $currentPrice ? 'Upgrade' : 'Downgrade');
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
