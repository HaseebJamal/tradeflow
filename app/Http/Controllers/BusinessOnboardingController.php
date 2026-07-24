<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterBusinessRequest;
use App\Models\Business;
use App\Models\BusinessDocument;
use App\Models\CompanyApprovalLog;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\CompanyRegistrationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessOnboardingController extends Controller
{
    public function create(Request $request)
    {
        $billingCycle = $request->query('billing_cycle', 'Monthly');
        abort_unless(in_array($billingCycle, ['Monthly', 'Yearly'], true), 404);

        $plans = SubscriptionPlan::publicActive()->orderBy('sort_order')->orderBy('monthly_price')->get();
        $selectedPlanId = $request->integer('plan');
        if ($selectedPlanId) {
            abort_unless($plans->contains('id', $selectedPlanId), 404);
        }

        return view('onboarding.register-business', [
            'plans' => $plans,
            'selectedPlanId' => $selectedPlanId,
            'selectedBillingCycle' => $billingCycle,
        ]);
    }

    public function store(RegisterBusinessRequest $request)
    {
        $data = $request->validated();
        $billingCycle = $data['billing_cycle'];

        DB::transaction(function () use ($request, $data, $billingCycle, &$user, &$business, &$plan) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'business_owner',
                'status' => 'active',
            ]);

            $business = Business::create([
                'owner_id' => $user->id,
                'selected_plan_id' => $data['selected_plan_id'],
                'selected_billing_cycle' => $data['billing_cycle'],
                'selected_plan_price' => 0,
                'trial_eligible' => true,
                'requested_trial_days' => 0,
                'subscription_request_status' => 'Pending Review',
                'plan_selected_at' => now(),
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'business_description' => $data['other_business_type'] ?? null,
                'category' => $data['category'] ?? null,
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
                'city' => $data['city'],
                'registration_number' => $data['registration_number'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'status' => 'Pending',
            ]);

            $user->update(['business_id' => $business->id]);

            $plan = SubscriptionPlan::publicActive()->findOrFail($data['selected_plan_id']);
            $amount = $plan->priceFor($billingCycle);
            $business->update([
                'selected_plan_price' => $amount,
                'trial_eligible' => (int) $plan->trial_days > 0,
                'requested_trial_days' => (int) $plan->trial_days,
                'selected_plan_snapshot' => $this->planSnapshot($plan, $billingCycle, $amount),
            ]);
            Subscription::create([
                'business_id' => $business->id,
                'subscription_plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'amount' => $amount,
                'status' => 'Pending',
                'payment_status' => 'Pending',
            ]);

            CompanyApprovalLog::create([
                'company_id' => $business->id,
                'old_status' => null,
                'new_status' => 'Pending',
                'note' => 'Company registered from public onboarding',
                'changed_by' => null,
                'changed_at' => now(),
            ]);

            foreach (['cnic_image', 'business_document', 'shop_image'] as $field) {
                if ($request->hasFile($field)) {
                    BusinessDocument::create([
                        'business_id' => $business->id,
                        'document_type' => $field,
                        'file_path' => $request->file($field)->store('business-documents', 'public'),
                        'status' => 'Pending Verification',
                    ]);
                }
            }
        });

        User::where('role', 'super_admin')->where('status', 'active')->get()
            ->each(fn (User $admin) => $admin->notify(new CompanyRegistrationNotification($business)));
        AuditLog::create([
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'actor_role' => 'business_owner',
            'business_id' => $business->id,
            'module' => 'Subscriptions',
            'action' => 'registration plan selected',
            'description' => $plan->name.' '.$billingCycle.' plan selected during business registration.',
            'record_type' => 'Subscription',
            'record_id' => $business->subscription?->id,
            'new_values' => ['plan_id' => $plan->id, 'billing_cycle' => $billingCycle, 'amount' => $business->selected_plan_price],
        ]);
        $business->owner?->notify(new \App\Notifications\SubscriptionStatusNotification('Plan Selection Received', 'Your '.$plan->name.' '.$billingCycle.' plan selection was received and is pending review.', $business->id));

        $request->session()->forget(['registration_step', 'registration_draft']);

        return redirect()->route('public.home')->with('registration_completed', true);
    }

    private function planSnapshot(SubscriptionPlan $plan, string $cycle, int $amount): array
    {
        return [
            'plan_name' => $plan->name,
            'billing_cycle' => $cycle,
            'selected_price' => $amount,
            'monthly_price' => $plan->priceFor('Monthly'),
            'yearly_price' => $plan->priceFor('Yearly'),
            'trial_days' => (int) $plan->trial_days,
            'product_limit' => (int) $plan->product_limit,
            'staff_limit' => (int) $plan->staff_limit,
            'order_limit' => (int) $plan->order_limit,
            'plan_status' => $plan->status,
            'included_modules' => $plan->included_modules ?? [],
        ];
    }
}
