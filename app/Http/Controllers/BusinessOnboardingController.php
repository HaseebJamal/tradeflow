<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterBusinessRequest;
use App\Models\Business;
use App\Models\BusinessDocument;
use App\Models\CompanyApprovalLog;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\CompanyRegistrationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class BusinessOnboardingController extends Controller
{
    public function create()
    {
        return view('onboarding.register-business', [
            'plans' => SubscriptionPlan::publicActive()->orderBy('sort_order')->orderBy('monthly_price')->get(),
        ]);
    }

    public function store(RegisterBusinessRequest $request)
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data, &$user, &$business) {
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
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'business_description' => $data['business_description'] ?? null,
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
            $cycle = $data['billing_cycle'];
            Subscription::create([
                'business_id' => $business->id,
                'subscription_plan_id' => $plan->id,
                'billing_cycle' => $cycle,
                'amount' => $plan->priceFor($cycle),
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

        $request->session()->forget(['registration_step', 'registration_draft']);

        return redirect()->route('public.home')->with('registration_completed', true);
    }
}
