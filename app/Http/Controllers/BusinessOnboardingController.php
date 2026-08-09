<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterBusinessRequest;
use App\Models\Business;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\PlatformSetting;
use App\Notifications\CompanyRegistrationNotification;
use App\Notifications\SubscriptionStatusNotification;
use App\Services\CompanyPermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class BusinessOnboardingController extends Controller
{
    public function create(Request $request)
    {
        // Registration is deliberately plan-free.  The configured platform
        // trial is assigned server-side when the business is created.
        $request->session()->forget('registration_pricing_selection');

        $trialDays = (int) PlatformSetting::current()->trial_days;
        if ($trialDays < 1 || $trialDays > 365) {
            throw ValidationException::withMessages([
                'trial' => 'Free trial registration is temporarily unavailable. Please contact support.',
            ]);
        }

        return view('onboarding.register-business', compact('trialDays'));
    }

    public function store(RegisterBusinessRequest $request)
    {
        $data = $request->validated();
        $settings = PlatformSetting::current();
        $trialDays = (int) $settings->trial_days;
        if ($trialDays < 1 || $trialDays > 365) {
            throw ValidationException::withMessages([
                'trial' => 'Free trial registration is temporarily unavailable. Please contact support.',
            ]);
        }

        // A plan relation is retained only for legacy data compatibility. It
        // is never selected by a registering business and has no trial limits.
        $plan = SubscriptionPlan::query()
            ->whereKey($settings->default_plan_id)
            ->whereNull('archived_at')
            ->first()
            ?? SubscriptionPlan::query()->where('status', 'Active')->whereNull('archived_at')->orderBy('sort_order')->firstOrFail();
        $trialStart = now(config('app.timezone'));
        $trialEnd = $trialStart->copy()->addDays($trialDays);

        $logoPath = $request->hasFile('logo') ? $request->file('logo')->store('business-logos', 'public') : null;

        try {
        DB::transaction(function () use ($data, $trialDays, $trialStart, $trialEnd, $plan, $logoPath, &$user, &$business, &$subscription) {
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
                'selected_plan_id' => $plan->id,
                'selected_billing_cycle' => 'Custom',
                'plan_selection_source' => 'automatic_trial',
                'selected_plan_price' => 0,
                'trial_eligible' => true,
                'requested_trial_days' => $trialDays,
                'subscription_request_status' => 'Trial Active',
                'plan_selected_at' => now(),
                'business_name' => $data['business_name'],
                // The database retains this legacy required column, but public
                // registration no longer asks a business-type question.
                'business_type' => 'General Business',
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'logo' => $logoPath,
                // New businesses are immediately usable. Verification files
                // remain available to Super Admin as records, not an access gate.
                'status' => 'Approved',
            ]);

            $user->update(['business_id' => $business->id]);
            app(CompanyPermissionService::class)->grantFullAccess($business);
            $business->update([
                'selected_plan_snapshot' => ['access_model' => 'automatic_trial', 'trial_days' => $trialDays],
            ]);
            $subscription = Subscription::create([
                'business_id' => $business->id,
                'subscription_plan_id' => $plan->id,
                'billing_cycle' => 'Custom',
                'amount' => 0,
                'starts_at' => $trialStart->toDateString(),
                'ends_at' => $trialEnd->toDateString(),
                'trial_start_at' => $trialStart->toDateString(),
                'trial_end_at' => $trialEnd->toDateString(),
                'status' => 'Trial',
                'payment_status' => 'Pending',
            ]);

        });
        } catch (Throwable $exception) {
            if ($logoPath) Storage::disk('public')->delete($logoPath);
            throw $exception;
        }

        User::where('role', 'super_admin')->where('status', 'active')->get()
            ->each(fn (User $admin) => $admin->notify(new CompanyRegistrationNotification($business)));
        AuditLog::create([
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'actor_role' => 'business_owner',
            'business_id' => $business->id,
            'module' => 'Subscriptions',
            'action' => 'business registered and trial started automatically',
            'description' => 'Business registered and trial started automatically.',
            'record_type' => 'Subscription',
            'record_id' => $subscription->id,
            'new_values' => ['trial_start' => $trialStart->toDateString(), 'trial_end' => $trialEnd->toDateString()],
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'actor_id' => $user->id,
            'actor_role' => 'business_owner',
            'business_id' => $business->id,
            'module' => 'Settings',
            'action' => 'footer settings created',
            'description' => 'Footer Settings Created',
            'record_type' => 'BusinessDocumentFooter',
            'record_id' => $business->documentFooter?->id,
            'new_values' => ['changed_fields' => ['default_footer']],
        ]);
        $business->owner?->notify(new SubscriptionStatusNotification('Free trial started', 'Your workspace is ready. Your free trial ends on '.$trialEnd->format('d M, Y').'.', $business->id));

        $request->session()->forget(['registration_step', 'registration_draft', 'registration_pricing_selection']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('business.dashboard')->with('success', 'Welcome to Profit Point. Your free trial is active.');
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
