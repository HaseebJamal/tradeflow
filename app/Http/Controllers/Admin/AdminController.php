<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessReport;
use App\Models\BusinessUserAssignment;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\NewsletterSubscriber;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformPayment;
use App\Models\RenewalInvoice;
use App\Services\PlatformSettingsService;
use App\Services\PhoneNumberService;
use App\Services\SubscriptionLifecycleService;
use App\Services\SubscriptionPaymentService;
use App\Services\RenewalInvoiceService;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\SubscriptionAccessExtension;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionChangeRequest;
use App\Models\Supplier;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\AuditIpResolver;
use App\Notifications\SubscriptionStatusNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('super-admin.dashboard', $this->platformDashboardData());
    }

    private function platformDashboardData(): array
    {
        // Keep the platform business-status cards on one authoritative aggregate
        // instead of issuing a separate query for every status.
        $businessSummary = Business::query()
            ->selectRaw("COUNT(*) as total_businesses,
                SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) as pending_businesses,
                SUM(CASE WHEN LOWER(status) = 'approved' THEN 1 ELSE 0 END) as approved_businesses,
                SUM(CASE WHEN LOWER(status) = 'rejected' THEN 1 ELSE 0 END) as rejected_businesses,
                SUM(CASE WHEN LOWER(status) = 'suspended' THEN 1 ELSE 0 END) as suspended_businesses")
            ->first();

        $monthlyRevenue = Schema::hasColumn('subscriptions', 'amount')
            ? Subscription::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount')
            : Subscription::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)
                ->join('subscription_plans', 'subscriptions.subscription_plan_id', '=', 'subscription_plans.id')->sum('subscription_plans.price');

        $growthStart = now()->subMonths(5)->startOfMonth();
        $registrationsByMonth = Business::query()
            ->where('created_at', '>=', $growthStart)
            ->get(['created_at'])
            ->countBy(fn (Business $business) => $business->created_at?->format('Y-m'));
        $registrationTrend = collect(range(5, 0))->map(function (int $offset) use ($registrationsByMonth) {
            $month = now()->subMonths($offset);

            return [
                'label' => $month->format('M'),
                'count' => (int) ($registrationsByMonth->get($month->format('Y-m')) ?? 0),
            ];
        });

        return [
            'totalBusinesses' => (int) ($businessSummary->total_businesses ?? 0),
            'pendingApprovals' => (int) ($businessSummary->pending_businesses ?? 0),
            'activeBusinesses' => (int) ($businessSummary->approved_businesses ?? 0),
            'rejectedBusinesses' => (int) ($businessSummary->rejected_businesses ?? 0),
            'suspendedBusinesses' => (int) ($businessSummary->suspended_businesses ?? 0),
            'totalUsers' => User::count(),
            'activeSubscriptions' => Subscription::where('status', 'Active')->count(),
            'expiredSubscriptions' => Subscription::where('status', 'Expired')->count(),
            'monthlyRevenue' => $monthlyRevenue,
            'ticketsCount' => SupportTicket::where('status', 'Open')->count(),
            'securityAlerts' => ActivityLog::where('module', 'Security')->whereDate('occurred_at', '>=', today()->subDays(7))->count(),
            'registrationTrend' => $registrationTrend,
        ];
    }

    public function platformAdmins(Request $request)
    {
        $query = User::withCount(['children', 'businessAssignments'])
            ->with('creator')
            ->where('role', 'platform_admin');

        $this->applyAdminFilters($query, $request);

        return view('super-admin.administration.platform-admins', [
            'admins' => $query->latest()->paginate(12)->withQueryString(),
            'permissions' => $this->platformPermissions(),
        ]);
    }

    public function platformSubAdmins(Request $request)
    {
        $query = User::with(['parent', 'creator'])->withCount('businessAssignments')->where('role', 'platform_sub_admin');
        $this->applyAdminFilters($query, $request);

        return view('super-admin.administration.platform-sub-admins', [
            'subAdmins' => $query->latest()->paginate(12)->withQueryString(),
            'platformAdmins' => User::where('role', 'platform_admin')->where('status', 'active')->orderBy('name')->get(),
            'permissions' => $this->platformPermissions(),
        ]);
    }

    public function storePlatformUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'role' => ['required', Rule::in(['platform_admin', 'platform_sub_admin'])],
            'parent_user_id' => ['nullable', 'exists:users,id'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'status' => ['required', 'in:active,inactive,suspended'],
            'permissions' => ['nullable', 'array'],
        ]);

        if ($data['role'] === 'platform_admin') {
            $data['parent_user_id'] = auth()->id();
        } else {
            $parent = User::where('role', 'platform_admin')->findOrFail($data['parent_user_id']);
            $data['parent_user_id'] = $parent->id;
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'status' => $data['status'],
            'parent_user_id' => $data['parent_user_id'],
            'created_by' => auth()->id(),
            'password' => Hash::make($data['password']),
            'permissions' => $data['permissions'] ?? [],
        ]);

        $this->audit('Created '.$data['role'].' '.$user->email, $request, 'Administration', $user->id, null, $user->only(['name', 'email', 'role', 'status']));
        $this->activity($request, 'Administration', 'create', 'Created '.$data['role'].' '.$user->email, $user);

        return back()->with('success', 'Platform user created.');
    }

    public function updatePlatformUser(Request $request, User $user)
    {
        abort_unless(in_array($user->role, ['platform_admin', 'platform_sub_admin'], true), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'parent_user_id' => ['nullable', 'exists:users,id'],
            'permissions' => ['nullable', 'array'],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        if ($user->role === 'platform_sub_admin' && !empty($data['parent_user_id'])) {
            User::where('role', 'platform_admin')->findOrFail($data['parent_user_id']);
        } else {
            unset($data['parent_user_id']);
        }

        $old = $user->only(['name', 'phone', 'status', 'parent_user_id', 'permissions']);
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data + ['permissions' => $data['permissions'] ?? []]);

        $this->audit('Updated platform user '.$user->email, $request, 'Administration', $user->id, $old, $user->fresh()->only(['name', 'phone', 'status', 'parent_user_id', 'permissions']));

        return back()->with('success', 'Platform user updated.');
    }

    public function businessAssignments(Request $request)
    {
        return view('super-admin.administration.business-assignments', [
            'assignments' => BusinessUserAssignment::with(['business.owner', 'user', 'assigner'])->where('status', 'Active')->latest()->paginate(12),
            'businesses' => Business::orderBy('business_name')->get(),
            'admins' => User::whereIn('role', ['platform_admin', 'platform_sub_admin', 'business_owner'])->orderBy('name')->get(),
        ]);
    }

    public function storeBusinessAssignment(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'exists:businesses,id'],
            'user_id' => ['required', 'exists:users,id'],
            'assignment_role' => ['required', Rule::in(['portfolio_admin', 'portfolio_sub_admin', 'business_owner', 'business_manager', 'support_manager', 'read_only_auditor'])],
        ]);

        $user = User::findOrFail($data['user_id']);
        if ($user->role === 'platform_sub_admin') {
            $parentBusinessIds = BusinessUserAssignment::where('user_id', $user->parent_user_id)->where('status', 'Active')->pluck('business_id');
            if ($parentBusinessIds->isNotEmpty() && !$parentBusinessIds->contains((int) $data['business_id'])) {
                return back()->withErrors(['business_id' => 'This business is outside the parent admin portfolio.']);
            }
        }

        BusinessUserAssignment::updateOrCreate(
            ['business_id' => $data['business_id'], 'user_id' => $data['user_id'], 'assignment_role' => $data['assignment_role']],
            ['assigned_by' => auth()->id(), 'assigned_at' => now(), 'revoked_at' => null, 'status' => 'Active']
        );

        $this->audit('Assigned business #'.$data['business_id'].' to user #'.$data['user_id'], $request, 'Business Assignments');
        return back()->with('success', 'Business assignment saved.');
    }

    public function revokeBusinessAssignment(Request $request, BusinessUserAssignment $assignment)
    {
        $assignment->update(['status' => 'Revoked', 'revoked_at' => now()]);
        $this->audit('Revoked business assignment #'.$assignment->id, $request, 'Business Assignments');
        return back()->with('success', 'Assignment revoked.');
    }

    public function adminPermissions()
    {
        return view('super-admin.administration.admin-permissions', [
            'users' => User::whereIn('role', ['platform_admin', 'platform_sub_admin'])->orderBy('name')->get(),
            'permissions' => $this->platformPermissions(),
        ]);
    }

    public function adminActivity(Request $request)
    {
        return view('super-admin.administration.admin-activity', [
            'activities' => ActivityLog::with(['actor', 'business'])->whereIn('actor_role', ['super_admin', 'platform_admin', 'platform_sub_admin'])->latest('occurred_at')->paginate(12)->withQueryString(),
        ]);
    }

    public function liveActivity(Request $request)
    {
        $query = ActivityLog::with(['actor', 'business', 'admin', 'subAdmin'])
            ->when($request->role, fn ($q, $value) => $q->where('actor_role', $value))
            ->when($request->module, fn ($q, $value) => $q->where('module', $value))
            ->when($request->action, fn ($q, $value) => $q->where('action', $value))
            ->when($request->business_id, fn ($q, $value) => $q->where('business_id', $value))
            ->when($request->search, fn ($q, $value) => $q->where('description', 'like', "%{$value}%"))
            ->when($request->date_from, fn ($q, $value) => $q->whereDate('occurred_at', '>=', $value))
            ->when($request->date_to, fn ($q, $value) => $q->whereDate('occurred_at', '<=', $value));

        return view('super-admin.live-activity', [
            'activities' => $query->latest('occurred_at')->paginate(12)->withQueryString(),
            'businesses' => Business::orderBy('business_name')->get(),
        ]);
    }

    public function heartbeat(Request $request)
    {
        $request->user()->forceFill(['last_seen_at' => now(), 'last_activity_at' => now()])->save();
        return response()->json(['ok' => true, 'seen_at' => now()->toIso8601String()]);
    }

    public function businesses()
    {
        return view('super-admin.businesses', [
            'businesses' => Business::with(['owner', 'documents', 'subscription.plan', 'assignments.user'])
                ->withCount(['users', 'customers', 'orders'])
                ->latest()
                ->paginate(12),
        ]);
    }

    public function businessShow(Business $business)
    {
        return view('super-admin.business-show', ['business' => $business->load(['owner', 'documents', 'subscription.plan'])]);
    }

    public function updateStatus(Request $request, Business $business)
    {
        return app(CompanyController::class)->updateStatus($request, $business);
    }

    public function updateBusinessStatus(Request $request, Business $business)
    {
        return $this->updateStatus($request, $business);
    }

    public function users(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'suspended', 'inactive'])],
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
            'last_sign_in_from' => ['nullable', 'date'],
            'last_sign_in_to' => ['nullable', 'date', 'after_or_equal:last_sign_in_from'],
        ]);
        $query = User::with('business');
        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('business', fn ($business) => $business->where('business_name', 'like', "%{$search}%")));
        }

        $query
            ->when($request->filled('role'), fn ($builder) => $builder->where('role', $request->input('role')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->input('status')))
            ->when($request->filled('business_id'), fn ($builder) => $builder->where('business_id', $request->integer('business_id')));

        return view('super-admin.users', [
            'users' => $query
                ->when($request->filled('last_sign_in_from'), fn ($builder) => $builder->where('last_login_at', '>=', Carbon::parse($request->input('last_sign_in_from'), config('app.timezone'))->startOfDay()))
                ->when($request->filled('last_sign_in_to'), fn ($builder) => $builder->where('last_login_at', '<=', Carbon::parse($request->input('last_sign_in_to'), config('app.timezone'))->endOfDay()))
                ->latest()
                ->paginate(10)
                ->withQueryString(),
            'businesses' => Business::orderBy('business_name')->get(['id', 'business_name']),
            'roles' => User::query()->select('role')->distinct()->orderBy('role')->pluck('role'),
            'counts' => [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'suspended' => User::where('status', 'suspended')->count(),
                'business_owners' => User::where('role', 'business_owner')->count(),
            ],
        ]);
    }

    public function updateUserStatus(Request $request, User $user)
    {
        if ($user->business_id) {
            $this->audit('Blocked company account status access', $request, 'Users', $user->id, null, ['operation' => 'user_status_update']);
            abort(403, 'Manage company users through their company status controls.');
        }

        $data = $request->validate(['status' => ['required', 'in:active,suspended,inactive']]);
        if ($user->role === 'super_admin' && ($user->id === auth()->id() || User::where('role', 'super_admin')->where('status', 'active')->count() <= 1)) {
            return back()->withErrors(['status' => 'The primary Super Admin cannot be suspended or deactivated from normal admin screens.']);
        }
        $user->update($data);
        $this->audit('User '.$data['status'].': '.$user->email, $request);
        return back()->with('success', 'User status updated.');
    }

    public function resetUserPassword(Request $request, User $user)
    {
        if ($user->business_id) {
            $this->audit('Blocked company credential reset attempt', $request, 'Users', $user->id, null, ['operation' => 'password_reset']);
            abort(403, 'Super Admins cannot reset company user passwords.');
        }

        if ($user->id === auth()->id()) {
            return back()->withErrors(['password' => 'Use your profile security settings to change your own password.']);
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);
        $this->audit('Password reset for user: '.$user->email, $request, 'Users', $user->id);
        $this->activity($request, 'Users', 'password_reset', 'Password reset for '.$user->email, $user);

        return back()->with('success', 'Password reset for '.$user->name.'.');
    }

    public function subscriptions(Request $request)
    {
        $this->expireDueSubscriptions($request);
        app(\App\Services\BusinessAccessInitializationService::class)->recoverTodaysMissingApprovedBusinesses();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'access_status' => ['nullable', Rule::in(['trial_active', 'trial_expiring', 'trial_expired', 'paid_scheduled', 'paid_active', 'paid_expiring', 'restricted'])],
            'trial_status' => ['nullable', Rule::in(['active', 'expiring', 'expired'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
            'manage' => ['nullable', 'boolean'],
            'payment_id' => ['nullable', 'integer', 'exists:platform_payments,id'],
        ]);
        $filters = array_replace([
            'search' => null,
            'access_status' => null,
            'trial_status' => null,
            'date_from' => null,
            'date_to' => null,
            'business_id' => null,
            'manage' => false,
            'payment_id' => null,
        ], $filters);
        // A payment deep link must never be hidden by a prior search or
        // status filter. Access remains controlled here, not on Payments.
        if ($filters['manage'] && $filters['business_id']) {
            $filters['search'] = null;
            $filters['access_status'] = null;
            $filters['trial_status'] = null;
            $filters['date_from'] = null;
            $filters['date_to'] = null;
        }

        $today = now(config('app.timezone'))->startOfDay();
        $trialExpiringDate = $today->copy()->addDays(SubscriptionLifecycleService::TRIAL_EXPIRING_SOON_DAYS);
        $paidExpiringDate = $today->copy()->addDays(SubscriptionLifecycleService::PAID_EXPIRING_SOON_DAYS);
        $businesses = Business::query()->with(['owner:id,name,email', 'subscription'])
            ->when($filters['business_id'] ?? null, fn ($query, $businessId) => $query->whereKey($businessId))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('business_name', 'like', "%{$search}%")
                        ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereHas('subscription', fn ($subscription) => $subscription->whereDate('trial_end_at', '>=', $date)))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereHas('subscription', fn ($subscription) => $subscription->whereDate('trial_end_at', '<=', $date)))
            ->when($filters['trial_status'] ?? null, function ($query, $status) use ($today, $trialExpiringDate) {
                $query->whereHas('subscription', function ($subscription) use ($status, $today, $trialExpiringDate) {
                    $subscription->where('payment_status', '!=', 'Received');
                    match ($status) {
                        'active' => $subscription->where('status', 'Trial')->whereDate('trial_end_at', '>', $trialExpiringDate),
                        'expiring' => $subscription->where('status', 'Trial')->whereBetween('trial_end_at', [$today, $trialExpiringDate]),
                        'expired' => $subscription->where('status', 'Expired'),
                    };
                });
            })
            ->when($filters['access_status'] ?? null, function ($query, $status) use ($today, $trialExpiringDate, $paidExpiringDate) {
                match ($status) {
                    'trial_active' => $query->whereHas('subscription', fn ($subscription) => $subscription->where('status', 'Trial')->whereDate('trial_end_at', '>', $trialExpiringDate)),
                    'trial_expiring' => $query->whereHas('subscription', fn ($subscription) => $subscription->where('status', 'Trial')->whereBetween('trial_end_at', [$today, $trialExpiringDate])),
                    'trial_expired' => $query->whereHas('subscription', fn ($subscription) => $subscription->where('status', 'Expired')->where('payment_status', '!=', 'Received')),
                    'paid_scheduled' => $query->whereHas('subscription', fn ($subscription) => $subscription->where('payment_status', 'Received')->whereNotNull('starts_at')->whereDate('starts_at', '>', $today)),
                    'paid_active' => $query->whereHas('subscription', fn ($subscription) => $subscription->where('payment_status', 'Received')->where('status', 'Active')->whereDate('ends_at', '>', $paidExpiringDate)),
                    'paid_expiring' => $query->whereHas('subscription', fn ($subscription) => $subscription->where('payment_status', 'Received')->whereIn('status', ['Active', 'Expiring'])->whereBetween('ends_at', [$today, $paidExpiringDate])),
                    'restricted' => $query->where(function ($inner) use ($today) {
                        $inner->doesntHave('subscription')->orWhereHas('subscription', function ($subscription) use ($today) {
                            $subscription->whereIn('status', ['Expired', 'Suspended', 'Cancelled'])
                                ->orWhere(function ($pending) use ($today) {
                                    $pending->where('status', 'Pending')
                                        ->where(function ($notFuturePaid) use ($today) {
                                            $notFuturePaid->whereNull('payment_status')
                                                ->orWhere('payment_status', '!=', 'Received')
                                                ->orWhereNull('starts_at')
                                                ->orWhereDate('starts_at', '<=', $today);
                                        });
                                });
                        });
                    }),
                };
            })
            ->orderBy('business_name')
            ->paginate(10)
            ->withQueryString();

        $lifecycle = app(SubscriptionLifecycleService::class);
        $accessHistory = AuditLog::query()
            ->with('actor:id,name')
            ->whereIn('business_id', $businesses->getCollection()->pluck('id'))
            ->where('module', 'Trial & Access')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->groupBy('business_id');
        $accessStates = $businesses->getCollection()->mapWithKeys(function (Business $business) use ($lifecycle, $accessHistory) {
            $presentation = $this->accessPresentation($business, $lifecycle->forBusiness($business));
            $presentation['history'] = $accessHistory->get($business->id, collect());

            return [$business->id => $presentation];
        })->all();

        $summary = Business::with('subscription')->get()->map(function (Business $business) use ($lifecycle) {
            return $this->accessPresentation($business, $lifecycle->forBusiness($business));
        });

        return view('super-admin.subscriptions', [
            'settings' => app(PlatformSettingsService::class)->current(),
            'businesses' => $businesses,
            'accessStates' => $accessStates,
            'filters' => $filters,
            'stats' => [
                'trial' => $summary->where('kind', 'trial_active')->count(),
                'expiring' => $summary->where('kind', 'trial_expiring')->count(),
                'expired' => $summary->where('kind', 'trial_expired')->count(),
                'paid' => $summary->whereIn('kind', ['paid_scheduled', 'paid_active', 'paid_expiring'])->count(),
                'restricted' => $summary->whereIn('kind', ['trial_expired', 'restricted'])->count(),
            ],
        ]);
    }

    public function storePlan(Request $request)
    {
        $data = $this->planData($request);
        $data['price'] = $data['monthly_price'];
        $plan = DB::transaction(function () use ($data) {
            if ($data['is_recommended']) {
                SubscriptionPlan::where('is_recommended', true)->update(['is_recommended' => false]);
            }

            return SubscriptionPlan::create($data);
        });
        $this->audit('Subscription plan created: '.$plan->name, $request, 'Subscriptions', $plan->id, null, $plan->only(['name', 'price', 'product_limit', 'staff_limit', 'order_limit', 'status']));

        return back()->with('success', 'Subscription plan created.');
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $data = $this->planData($request, $plan);
        $data['price'] = $data['monthly_price'];
        $old = $plan->only(array_keys($data));
        DB::transaction(function () use ($plan, $data) {
            if ($data['is_recommended']) {
                SubscriptionPlan::where('id', '<>', $plan->id)->where('is_recommended', true)->update(['is_recommended' => false]);
            }

            $plan->update($data);
        });
        $this->audit('Subscription plan updated: '.$plan->name, $request, 'Subscriptions', $plan->id, $old, $plan->fresh()->only(array_keys($data)));

        return back()->with('success', 'Subscription plan updated.');
    }

    public function destroyPlan(Request $request, SubscriptionPlan $plan)
    {
        $hasReferences = $plan->subscriptions()->exists()
            || (Schema::hasTable('subscription_change_requests') && SubscriptionChangeRequest::query()
                ->where('current_plan_id', $plan->id)
                ->orWhere('requested_plan_id', $plan->id)
                ->exists())
            || (Schema::hasColumn('businesses', 'selected_plan_id') && Business::where('selected_plan_id', $plan->id)->exists())
            || (Schema::hasTable('platform_settings') && Schema::hasColumn('platform_settings', 'default_plan_id')
                && DB::table('platform_settings')->where('default_plan_id', $plan->id)->exists());

        if ($hasReferences) {
            return back()->withErrors(['plan' => 'This plan cannot be deleted because it is currently assigned or referenced.']);
        }

        $old = $plan->only(['name', 'price', 'monthly_price', 'yearly_price', 'trial_days', 'product_limit', 'staff_limit', 'order_limit', 'status']);
        $plan->delete();
        $this->audit('Subscription plan deleted: '.$old['name'], $request, 'Subscriptions', $plan->id, $old);

        return back()->with('success', 'Subscription plan deleted.');
    }

    public function setPlanStatus(Request $request, SubscriptionPlan $plan)
    {
        abort_if($plan->archived_at, 422, 'Restore the plan before changing its status.');
        $data = $request->validate(['status' => ['required', 'in:Active,Inactive']]);
        $old = $plan->status;
        $plan->update([
            'status' => $data['status'],
            'is_recommended' => $data['status'] === 'Active' ? $plan->is_recommended : false,
        ]);
        $this->audit('Subscription plan '.strtolower($data['status']), $request, 'Subscriptions', $plan->id, ['status' => $old], ['status' => $data['status']]);
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Plan '.($data['status'] === 'Active' ? 'activated' : 'deactivated').' successfully.',
                'status' => $data['status'],
            ]);
        }
        return back()->with('success', 'Plan '.strtolower($data['status']).' successfully.');
    }

    public function archivePlan(Request $request, SubscriptionPlan $plan)
    {
        if ($plan->archived_at) return back()->with('success', 'Plan is already archived.');
        $plan->update(['archived_at' => now(), 'status' => 'Inactive', 'is_recommended' => false]);
        $this->audit('Subscription plan archived', $request, 'Subscriptions', $plan->id);
        return back()->with('success', 'Plan archived successfully.');
    }

    public function restorePlan(Request $request, SubscriptionPlan $plan)
    {
        abort_unless($plan->archived_at, 404);
        $plan->update(['archived_at' => null, 'status' => 'Inactive']);
        $this->audit('Subscription plan restored', $request, 'Subscriptions', $plan->id);
        return back()->with('success', 'Plan restored successfully. Activate it when ready.');
    }

    public function togglePlanVisibility(Request $request, SubscriptionPlan $plan)
    {
        abort_if($plan->archived_at, 422, 'Archived plans cannot be made public.');
        $plan->update(['is_public' => ! $plan->is_public]);
        $this->audit('Subscription plan visibility updated', $request, 'Subscriptions', $plan->id, null, ['is_public' => $plan->is_public]);
        return back()->with('success', $plan->is_public ? 'Plan is now public.' : 'Plan is now private.');
    }

    public function togglePlanRecommendation(Request $request, SubscriptionPlan $plan)
    {
        abort_if($plan->archived_at, 422, 'Archived plans cannot be recommended.');
        abort_unless($plan->status === 'Active', 422, 'Only active plans can be recommended.');
        DB::transaction(function () use ($plan) {
            if ($plan->is_recommended) {
                $plan->update(['is_recommended' => false]);
                return;
            }

            SubscriptionPlan::where('id', '<>', $plan->id)->where('is_recommended', true)->update(['is_recommended' => false]);
            $plan->update(['is_recommended' => true]);
        });
        $this->audit('Subscription plan recommendation updated', $request, 'Subscriptions', $plan->id, null, ['is_recommended' => $plan->is_recommended]);
        return back()->with('success', $plan->is_recommended ? 'Plan marked as recommended.' : 'Recommended marker removed.');
    }

    public function activateSubscription(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'exists:businesses,id'],
            'subscription_context_business_id' => ['nullable', 'integer'],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'billing_cycle' => ['nullable', 'in:Monthly,Yearly,Custom'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'in:Cash,Bank Transfer,Jazz Cash,Easypaisa'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_status' => ['nullable', 'in:Pending,Received,Failed'],
            'trial_start_at' => ['nullable', 'date'],
            'trial_end_at' => ['nullable', 'date', 'after_or_equal:trial_start_at'],
            'end_trial_now' => ['nullable', 'boolean'],
            'auto_renew' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'effective_at' => ['nullable', 'date'],
            'status' => ['required', 'in:Pending,Trial,Active,Expiring,Expired,Suspended,Cancelled'],
        ]);

        $lockedBusinessId = $request->session()->get('admin.subscription.locked_business_id');
        if ($lockedBusinessId !== null && (
            (int) $data['business_id'] !== (int) $lockedBusinessId
            || (int) ($data['subscription_context_business_id'] ?? 0) !== (int) $lockedBusinessId
        )) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'business_id' => 'The company selected from the actions menu cannot be changed.',
            ]);
        }

        $data['billing_cycle'] = $data['billing_cycle'] ?? 'Custom';
        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
        $data['amount'] = $data['amount'] ?? $plan->priceFor($data['billing_cycle'] === 'Custom' ? 'Monthly' : $data['billing_cycle']);
        $data['starts_at'] = $data['starts_at'] ?? now()->toDateString();
        $startDate = Carbon::parse($data['starts_at']);
        $data['ends_at'] = $data['ends_at'] ?? ($data['billing_cycle'] === 'Yearly'
            ? $startDate->copy()->addYear()->toDateString()
            : $startDate->copy()->addMonth()->toDateString());
        $data['payment_status'] = $data['payment_status'] ?? ($data['status'] === 'Active' ? 'Received' : 'Pending');
        $data['status'] = $this->resolvedSubscriptionStatus($data['status'], $data['ends_at']);

        $existing = Subscription::where('business_id', $data['business_id'])->first();
        if ($existing && in_array($existing->status, ['Trial', 'Active', 'Expiring'], true)) {
            throw ValidationException::withMessages(['business_id' => 'This business already has an active subscription. Use Manage, Renew, Upgrade, or Downgrade instead.']);
        }
        $subscription = Subscription::updateOrCreate(['business_id' => $data['business_id']], $data);
        $this->recordManualSubscriptionPayment($subscription, $request, null);
        app(SubscriptionLifecycleService::class)->synchronize($subscription->fresh());
        $this->audit('Subscription created or assigned for business #'.$data['business_id'], $request, 'Subscriptions', $subscription->id, null, $subscription->only(['subscription_plan_id', 'amount', 'payment_method', 'starts_at', 'ends_at', 'status']));
        $subscription->business?->owner?->notify(new SubscriptionStatusNotification('Subscription Assigned', 'Your '.$plan->name.' subscription was assigned successfully.', $subscription->business_id));

        return back()->with('success', 'Subscription updated.');
    }

    public function updateSubscription(Request $request, Subscription $subscription)
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'billing_cycle' => ['nullable', 'in:Monthly,Yearly,Custom'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'in:Cash,Bank Transfer,Jazz Cash,Easypaisa'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_status' => ['nullable', 'in:Pending,Received,Failed'],
            'trial_start_at' => ['nullable', 'date'],
            'trial_end_at' => ['nullable', 'date', 'after_or_equal:trial_start_at'],
            'end_trial_now' => ['nullable', 'boolean'],
            'auto_renew' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:Pending,Trial,Active,Expiring,Expired,Suspended,Cancelled'],
        ]);

        $data['billing_cycle'] = $data['billing_cycle'] ?? $subscription->billing_cycle ?? 'Custom';
        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
        $data['amount'] = $data['amount'] ?? $subscription->amount ?? $plan->priceFor($data['billing_cycle'] === 'Custom' ? 'Monthly' : $data['billing_cycle']);
        $data['starts_at'] = $data['starts_at'] ?? $subscription->starts_at?->toDateString() ?? now()->toDateString();
        $startDate = Carbon::parse($data['starts_at']);
        $data['ends_at'] = $data['ends_at'] ?? ($subscription->ends_at?->toDateString() ?? ($data['billing_cycle'] === 'Yearly'
            ? $startDate->copy()->addYear()->toDateString()
            : $startDate->copy()->addMonth()->toDateString()));
        if ($request->boolean('end_trial_now')) {
            abort_unless(in_array($subscription->status, ['Trial', 'Expired'], true), 422, 'Only a trial can be ended now.');
            $data['status'] = 'Expired';
            // Keep an immediate manual end auditable as today's local date;
            // the Expired status is the explicit restriction marker.
            $data['trial_end_at'] = now(config('app.timezone'))->toDateString();
            $data['ends_at'] = $data['trial_end_at'];
        } elseif ($data['status'] === 'Trial' && filled($data['trial_end_at'] ?? null)) {
            $data['trial_start_at'] = $data['trial_start_at'] ?? $data['starts_at'];
            $data['ends_at'] = $data['trial_end_at'];
        }
        $data['status'] = $this->resolvedSubscriptionStatus($data['status'], $data['ends_at']);
        $this->assertSubscriptionTransition($subscription->status, $data['status']);
        $old = $subscription->only(['subscription_plan_id', 'amount', 'payment_method', 'payment_status', 'starts_at', 'ends_at', 'status']);
        $subscription->update($data);
        $this->recordManualSubscriptionPayment($subscription->fresh(), $request, $old['payment_status'] ?? null);
        app(SubscriptionLifecycleService::class)->synchronize($subscription->fresh());
        $this->audit('Subscription updated for business #'.$subscription->business_id, $request, 'Subscriptions', $subscription->id, $old, $subscription->fresh()->only(array_keys($old)));

        return back()->with('success', 'Subscription updated.');
    }

    public function cancelSubscription(Request $request, Subscription $subscription)
    {
        if ($subscription->status === 'Cancelled') {
            return back()->with('success', 'This subscription is already cancelled.');
        }

        $old = $subscription->only(['status', 'ends_at']);
        $subscription->update(['status' => 'Cancelled', 'cancelled_at' => now(), 'ends_at' => $subscription->ends_at ?? now()->toDateString()]);
        $this->audit('Subscription cancelled for business #'.$subscription->business_id, $request, 'Subscriptions', $subscription->id, $old, $subscription->fresh()->only(array_keys($old)));
        $subscription->business?->owner?->notify(new SubscriptionStatusNotification('Subscription Cancelled', 'Your subscription has been cancelled.', $subscription->business_id));

        return back()->with('success', 'Subscription cancelled. The historical record was retained.');
    }

    public function destroySubscription(Request $request, Subscription $subscription)
    {
        if (!in_array($subscription->status, ['Expired', 'Cancelled'], true)) {
            return back()->withErrors(['subscription' => 'Cancel or expire the subscription before deleting it.']);
        }

        $old = $subscription->only(['business_id', 'subscription_plan_id', 'amount', 'payment_method', 'starts_at', 'ends_at', 'status']);
        $subscription->delete();
        $this->audit('Subscription deleted for business #'.$old['business_id'], $request, 'Subscriptions', $subscription->id, $old);

        return back()->with('success', 'Subscription record deleted.');
    }

    public function transitionSubscription(Request $request, Subscription $subscription)
    {
        $data = $request->validate(['status' => ['required', 'in:Active,Suspended,Cancelled,Expired']]);
        $this->assertSubscriptionTransition($subscription->status, $data['status']);
        $old = $subscription->status;
        $subscription->update(['status' => $data['status'], 'renewed_at' => $data['status'] === 'Active' ? now() : $subscription->renewed_at]);
        $this->audit('Subscription '.strtolower($data['status']), $request, 'Subscriptions', $subscription->id, ['status' => $old], ['status' => $data['status']]);
        $subscription->business?->owner?->notify(new SubscriptionStatusNotification('Subscription '.ucfirst($data['status']), 'Your subscription status is now '.$data['status'].'.', $subscription->business_id));
        return back()->with('success', 'Subscription '.strtolower($data['status']).' successfully.');
    }

    public function extendTrial(Request $request, Subscription $subscription)
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365']]);
        if (! in_array($subscription->status, ['Trial', 'Expired'], true)) {
            throw ValidationException::withMessages(['subscription' => 'This action is not valid for the current subscription status.']);
        }
        $now = now(config('app.timezone'));
        $currentEnd = $subscription->trial_end_at ?? $now;
        $end = $currentEnd->lt($now->copy()->startOfDay())
            ? $now->copy()->startOfDay()->addDays($data['days'])
            : $currentEnd->copy()->addDays($data['days']);
        $subscription->update(['status' => 'Trial', 'trial_start_at' => $subscription->trial_start_at ?? now(), 'trial_end_at' => $end, 'ends_at' => $end]);
        $this->audit('Trial extended', $request, 'Subscriptions', $subscription->id, null, ['trial_end_at' => $end->toDateString()]);
        $subscription->business?->owner?->notify(new SubscriptionStatusNotification('Trial Extended', 'Your '.app(PlatformSettingsService::class)->name().' trial was extended until '.$end->format('d M, Y').'.', $subscription->business_id));
        return back()->with('success', 'Trial extended successfully.');
    }

    public function updateDefaultTrialDays(Request $request): RedirectResponse
    {
        $data = $request->validate(['trial_days' => ['required', 'integer', 'min:1', 'max:365']]);
        $settingsService = app(PlatformSettingsService::class);
        $settings = $settingsService->current();
        $previous = (int) $settings->trial_days;
        $settings->update(['trial_days' => $data['trial_days']]);
        $settingsService->forget();
        $this->audit('Default trial days updated', $request, 'Trial & Access', $settings->id, ['trial_days' => $previous], ['trial_days' => $data['trial_days']]);

        return back()->with('success', 'Default trial duration updated. Existing trials keep their stored end date.');
    }

    public function adjustTrial(Request $request, Subscription $subscription): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            // Keep the legacy set_end action accepted for historic/audited
            // clients, but the Trial & Access UI intentionally exposes only
            // extend, reduce, and end-now controls.
            'action' => ['required', Rule::in(['extend', 'reduce', 'set_end', 'end_now'])],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'trial_end_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (in_array($data['action'], ['extend', 'reduce'], true) && empty($data['days'])) {
            throw ValidationException::withMessages(['days' => 'Enter the number of days to adjust.']);
        }
        if ($data['action'] === 'set_end' && empty($data['trial_end_at'])) {
            throw ValidationException::withMessages(['trial_end_at' => 'Select the new trial end date.']);
        }

        $change = DB::transaction(function () use ($request, $subscription, $data): array {
            $locked = Subscription::query()->with('business.owner')->lockForUpdate()->findOrFail($subscription->id);
            abort_if($locked->payment_status === 'Received' && in_array($locked->status, ['Active', 'Expiring'], true), 422, 'Paid access dates are managed from Payments & Billing.');

            $now = now(config('app.timezone'));
            $today = $now->copy()->startOfDay();
            $currentEnd = ($locked->trial_end_at ?? $locked->ends_at ?? $today)->copy()->startOfDay();
            $adjustmentBase = $currentEnd->lt($today) ? $today : $currentEnd;
            $newEnd = match ($data['action']) {
                'extend' => $adjustmentBase->copy()->addDays((int) $data['days']),
                'reduce' => $currentEnd->copy()->subDays((int) $data['days']),
                'set_end' => Carbon::parse($data['trial_end_at'], config('app.timezone'))->startOfDay(),
                'end_now' => $today->copy(),
            };
            if ($data['action'] === 'reduce' && $newEnd->lte($today)) {
                $newEnd = $today->copy();
            }
            $old = [
                'status' => $locked->status,
                'trial_start_at' => $locked->trial_start_at?->toDateString(),
                'trial_end_at' => $locked->trial_end_at?->toDateString(),
            ];
            // A reduction that consumes every currently remaining calendar
            // day ends the entitlement now. Store today's date (not a fake
            // yesterday) and let the explicit Expired status preserve that
            // immediate restriction. Partial reductions retain normal
            // end-of-day validity for their new stored date.
            $expiresImmediately = $data['action'] === 'end_now'
                || ($data['action'] === 'reduce' && $newEnd->lte($today));
            $hasExpired = $expiresImmediately || $now->gte($newEnd->copy()->endOfDay());
            $locked->update([
                'status' => $hasExpired ? 'Expired' : 'Trial',
                'trial_start_at' => $locked->trial_start_at ?? $today,
                'trial_end_at' => $newEnd,
                'ends_at' => $newEnd,
                'payment_status' => 'Pending',
                'note' => $data['note'] ?? $locked->note,
            ]);
            $updated = app(SubscriptionLifecycleService::class)->synchronize($locked->fresh()->load('business.owner'));
            $actionLabel = match ($data['action']) {
                'extend' => 'Trial extended by '.(int) $data['days'].' days',
                'reduce' => 'Trial reduced by '.(int) $data['days'].' days',
                'set_end' => 'Trial end date changed',
                'end_now' => 'Trial ended manually',
            };
            $daysChanged = match ($data['action']) {
                'extend' => (int) $data['days'],
                'reduce' => -((int) $data['days']),
                default => $currentEnd->diffInDays($newEnd, false),
            };
            $new = [
                'status' => $updated->status,
                'previous_trial_end' => $currentEnd->toDateString(),
                'trial_end_at' => $updated->trial_end_at?->toDateString(),
                'days_changed' => $daysChanged,
                'note' => $data['note'] ?? null,
            ];
            $this->auditBusinessAccess($request, $updated->business, $actionLabel, $updated, $old, $new);

            return compact('updated', 'actionLabel', 'newEnd', 'expiresImmediately');
        });

        $updated = $change['updated'];
        $actionLabel = $change['actionLabel'];
        $newEnd = $change['newEnd'];
        $expiresImmediately = $change['expiresImmediately'];
        $updated->business?->owner?->notify(new SubscriptionStatusNotification(
            $expiresImmediately ? 'Your free trial has ended' : $actionLabel,
            $expiresImmediately
                ? 'Your business data is safe. Contact '.app(PlatformSettingsService::class)->name().' to continue using your workspace.'
                : 'Your trial now ends on '.$newEnd->format('n/j/Y').'.',
            $updated->business_id,
        ));

        $message = $actionLabel.'.'.($expiresImmediately ? ' Workspace access is now restricted.' : '');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => $updated->status,
                'trial_end_at' => $updated->trial_end_at?->toDateString(),
                'restricted' => $expiresImmediately,
            ]);
        }

        return back()->with('success', $message);
    }

    public function adjustPaidAccess(Request $request, Subscription $subscription): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in([
                'extra_extend', 'extra_reduce',
                'paid_duration_extend', 'paid_duration_reduce',
                // Legacy values remain compatible with previously rendered
                // extra-access forms.
                'extend', 'reduce', 'set_end', 'end_now',
            ])],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'ends_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_unless($subscription->payment_status === 'Received', 422, 'Only a paid access period can be changed here.');
        $action = match ($data['action']) {
            'extend' => 'extra_extend',
            'reduce' => 'extra_reduce',
            default => $data['action'],
        };
        if (in_array($action, ['extra_extend', 'extra_reduce', 'paid_duration_extend', 'paid_duration_reduce'], true) && empty($data['days'])) {
            throw ValidationException::withMessages(['days' => 'Enter the number of days to adjust.']);
        }
        if ($action === 'set_end') {
            throw ValidationException::withMessages(['action' => 'Use the paid-duration controls to correct the paid access period.']);
        }

        $today = now(config('app.timezone'))->startOfDay();
        [$subscription, $old, $new] = DB::transaction(function () use ($subscription, $request, $data, $action, $today): array {
            $locked = Subscription::with('business.owner')->lockForUpdate()->findOrFail($subscription->id);
            abort_unless($locked->payment_status === 'Received' && $locked->starts_at && $locked->ends_at, 422, 'Only a paid access period can be changed here.');

            $snapshot = static function (Subscription $record): array {
                return [
                    'status' => $record->status,
                    'paid_access_start' => $record->starts_at?->toDateString(),
                    'original_paid_access_end' => $record->ends_at?->toDateString(),
                    'paid_duration_days' => $record->starts_at && $record->ends_at
                        ? $record->starts_at->diffInDays($record->ends_at)
                        : 0,
                    'extra_access_days' => $record->extraAccessDays(),
                    'effective_access_end' => $record->effectivePaidAccessEnd()?->toDateString(),
                ];
            };
            $old = $snapshot($locked);

            if ($action === 'paid_duration_extend') {
                // This corrects the subscription access period only. The
                // associated payment/invoice snapshots remain immutable.
                $locked->update([
                    'ends_at' => $locked->ends_at->copy()->addDays((int) $data['days']),
                    'note' => $data['note'] ?? $locked->note,
                ]);
            } elseif ($action === 'paid_duration_reduce') {
                $paidDuration = $locked->starts_at->diffInDays($locked->ends_at);
                if ((int) $data['days'] >= $paidDuration) {
                    throw ValidationException::withMessages([
                        'days' => "Paid duration must retain at least one day. Only ".max(0, $paidDuration - 1).' day(s) can be reduced.',
                    ]);
                }

                $newPaidEnd = $locked->ends_at->copy()->subDays((int) $data['days']);
                $extraDays = $locked->extraAccessDays();
                $update = [
                    'ends_at' => $newPaidEnd,
                    'note' => $data['note'] ?? $locked->note,
                ];
                // A paid correction that reaches today expires immediately
                // only when no separately tracked complimentary access remains.
                // Otherwise the effective end continues to honour that access.
                if ($newPaidEnd->lte($today) && $extraDays === 0) {
                    $update['access_ended_at'] = $today;
                    $update['status'] = 'Expired';
                }
                $locked->update($update);
            } elseif ($action === 'extra_extend') {
                SubscriptionAccessExtension::create([
                    'subscription_id' => $locked->id,
                    'business_id' => $locked->business_id,
                    'paid_access_start_at' => $locked->starts_at,
                    'paid_access_end_at' => $locked->ends_at,
                    'days' => (int) $data['days'],
                    'kind' => 'manual_extra',
                    'note' => $data['note'] ?? null,
                    'granted_by' => $request->user()?->id,
                    'granted_at' => now(),
                ]);
            } elseif ($action === 'extra_reduce') {
                $extraDays = $locked->extraAccessDays();
                if ((int) $data['days'] > $extraDays) {
                    throw ValidationException::withMessages(['days' => "Only {$extraDays} complimentary access day(s) can be reduced."]);
                }
                SubscriptionAccessExtension::create([
                    'subscription_id' => $locked->id,
                    'business_id' => $locked->business_id,
                    'paid_access_start_at' => $locked->starts_at,
                    'paid_access_end_at' => $locked->ends_at,
                    'days' => -((int) $data['days']),
                    'kind' => 'manual_extra_reduction',
                    'note' => $data['note'] ?? null,
                    'granted_by' => $request->user()?->id,
                    'granted_at' => now(),
                ]);
            } elseif ($action === 'end_now') {
                // Administrative early-end override: the paid period and all
                // complimentary history remain unchanged for audit purposes.
                $locked->update(['access_ended_at' => $today, 'status' => 'Expired', 'note' => $data['note'] ?? $locked->note]);
            }

            $locked->refresh();
            return [$locked, $old, $snapshot($locked)];
        });
        $subscription = app(SubscriptionLifecycleService::class)->synchronize($subscription);
        $renewals = app(RenewalInvoiceService::class);
        if (in_array($action, ['extra_extend', 'extra_reduce', 'paid_duration_extend', 'paid_duration_reduce'], true)) {
            $renewals->reconcileEffectiveAccessEnd($subscription);
        }
        $renewals->generateDue();
        $actionLabel = match ($action) {
            'paid_duration_extend' => 'Paid duration extended by '.(int) $data['days'].' days',
            'paid_duration_reduce' => 'Paid duration reduced by '.(int) $data['days'].' days',
            'extra_extend' => 'Granted '.(int) $data['days'].' complimentary access days',
            'extra_reduce' => 'Reduced complimentary access by '.(int) $data['days'].' days',
            'end_now' => 'Paid access ended manually',
        };
        $this->auditBusinessAccess($request, $subscription->business, $actionLabel, $subscription, $old, $new);
        $originalEnd = $subscription->ends_at?->format('d M, Y') ?? 'the original paid end';
        $effectiveEnd = $subscription->effectivePaidAccessEnd()?->format('d M, Y') ?? 'the effective access end';
        $subscription->business?->owner?->notify(new SubscriptionStatusNotification(
            $action === 'end_now' ? 'Your access period has ended' : $actionLabel,
            $action === 'end_now'
                ? 'Your business data is safe. Contact '.app(PlatformSettingsService::class)->name().' to continue using your workspace.'
                : (in_array($action, ['paid_duration_extend', 'paid_duration_reduce'], true)
                    ? "Your paid access end is {$originalEnd}. Your effective access end is {$effectiveEnd}."
                    : "Your original paid access end remains {$originalEnd}. Your effective access end is {$effectiveEnd}."),
            $subscription->business_id,
        ));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $actionLabel.'.',
                'paid_access_end' => $subscription->ends_at?->toDateString(),
                'effective_access_end' => $subscription->effectivePaidAccessEnd()?->toDateString(),
                'paid_duration_days' => $subscription->starts_at && $subscription->ends_at
                    ? $subscription->starts_at->diffInDays($subscription->ends_at)
                    : 0,
                'extra_access_days' => $subscription->extraAccessDays(),
            ]);
        }

        return back()->with('success', $actionLabel.'.');
    }

    /**
     * Start a fresh valid paid-access period for an expired or administratively
     * restricted business. This is deliberately an access entitlement only:
     * payments stay exclusively in Payments & Billing and no payment record is
     * manufactured by this recovery action.
     */
    public function reactivatePaidAccess(Request $request, Subscription $subscription): RedirectResponse|JsonResponse
    {
        $today = now(config('app.timezone'))->startOfDay();
        $data = $request->validate([
            'starts_at' => ['required', 'date', 'after_or_equal:'.$today->toDateString()],
            'paid_duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'extra_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        [$updated, $old, $new] = DB::transaction(function () use ($request, $subscription, $data, $today): array {
            $locked = Subscription::query()
                ->with('business.owner')
                ->lockForUpdate()
                ->findOrFail($subscription->id);
            $lifecycle = app(SubscriptionLifecycleService::class);
            $state = $lifecycle->state($locked);
            $canReactivate = $locked->payment_status === 'Received'
                && ! $state['can_access_business']
                && ($state['is_expired'] || in_array($locked->status, ['Expired', 'Suspended'], true));
            abort_unless($canReactivate, 422, 'Only an expired or restricted paid-access business can be reactivated.');

            $start = Carbon::parse($data['starts_at'], config('app.timezone'))->startOfDay();
            abort_if($start->lt($today), 422, 'New access cannot start in the past.');
            $paidEnd = $start->copy()->addDays((int) $data['paid_duration_days']);
            $old = [
                'status' => $locked->status,
                'paid_access_start' => $locked->starts_at?->toDateString(),
                'paid_access_end' => $locked->ends_at?->toDateString(),
                'paid_duration_days' => $locked->starts_at && $locked->ends_at
                    ? $locked->starts_at->diffInDays($locked->ends_at)
                    : 0,
                'extra_access_days' => $locked->extraAccessDays(),
                'effective_access_end' => $locked->effectivePaidAccessEnd()?->toDateString(),
            ];

            // The access record represents the current entitlement. Old cycle
            // values are retained in the immutable audit entry below; no
            // PlatformPayment is inserted or altered by manual reactivation.
            $locked->update([
                'starts_at' => $start,
                'ends_at' => $paidEnd,
                'access_ended_at' => null,
                'status' => 'Active',
                'note' => $data['note'] ?? $locked->note,
                'renewed_at' => now(),
                'cancellation_scheduled_at' => null,
                'cancellation_reason' => null,
            ]);

            $extraDays = (int) ($data['extra_days'] ?? 0);
            if ($extraDays > 0) {
                SubscriptionAccessExtension::create([
                    'subscription_id' => $locked->id,
                    'business_id' => $locked->business_id,
                    'paid_access_start_at' => $start,
                    'paid_access_end_at' => $paidEnd,
                    'days' => $extraDays,
                    'kind' => 'manual_reactivation_extra',
                    'note' => $data['note'] ?? 'Manual reactivation complimentary access.',
                    'granted_by' => $request->user()?->id,
                    'granted_at' => now(),
                ]);
            }

            $updated = $lifecycle->synchronize($locked->fresh()->load('business.owner'));
            $new = [
                'status' => $updated->status,
                'reactivation_type' => 'Manual Reactivation / Complimentary Access',
                'paid_access_start' => $updated->starts_at?->toDateString(),
                'paid_access_end' => $updated->ends_at?->toDateString(),
                'paid_duration_days' => $updated->starts_at && $updated->ends_at
                    ? $updated->starts_at->diffInDays($updated->ends_at)
                    : 0,
                'extra_access_days' => $updated->extraAccessDays(),
                'effective_access_end' => $updated->effectivePaidAccessEnd()?->toDateString(),
                'reactivated_by' => $request->user()?->id,
                'reactivated_at' => now()->toIso8601String(),
                'note' => $data['note'] ?? null,
            ];

            return [$updated, $old, $new];
        });

        app(RenewalInvoiceService::class)->generateDue();
        $this->auditBusinessAccess($request, $updated->business, 'Business access reactivated', $updated, $old, $new);
        $effectiveEnd = $updated->effectivePaidAccessEnd()?->format('n/j/Y') ?? 'the configured access end date';
        $updated->business?->owner?->notify(new SubscriptionStatusNotification(
            'Access reactivated',
            'Your '.app(PlatformSettingsService::class)->name().' access has been reactivated until '.$effectiveEnd.'.',
            $updated->business_id,
        ));

        $message = 'Business access reactivated successfully.';
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => $updated->status,
                'paid_access_start' => $updated->starts_at?->toDateString(),
                'paid_access_end' => $updated->ends_at?->toDateString(),
                'effective_access_end' => $updated->effectivePaidAccessEnd()?->toDateString(),
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Restore an access-restricted workspace. A company with a prior received
     * paid entitlement is restored to that paid plan; otherwise a trial is
     * restored. This never creates a new payment transaction.
     */
    public function restoreRestrictedAccess(Request $request, Business $business): RedirectResponse|JsonResponse
    {
        $today = now(config('app.timezone'))->startOfDay();
        $data = $request->validate([
            'starts_at' => ['required', 'date', 'after_or_equal:'.$today->toDateString()],
            'access_duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        [$updated, $old, $new] = DB::transaction(function () use ($request, $business, $data, $today): array {
            $lockedBusiness = Business::query()
                ->with('owner')
                ->lockForUpdate()
                ->findOrFail($business->id);
            $locked = Subscription::query()
                ->where('business_id', $lockedBusiness->id)
                ->lockForUpdate()
                ->first();
            $priorPaidPayment = PlatformPayment::query()
                ->where('business_id', $lockedBusiness->id)
                ->where('status', 'Received')
                ->whereNotNull('subscription_plan_id')
                ->whereNotNull('period_starts_at')
                ->whereNotNull('period_ends_at')
                ->lockForUpdate()
                ->orderByDesc('period_ends_at')
                ->orderByDesc('id')
                ->first();
            $lifecycle = app(SubscriptionLifecycleService::class);
            $state = $lifecycle->state($locked);

            abort_if($state['can_access_business'], 422, 'This business already has active access.');
            $restorePaidAccess = $locked?->payment_status === 'Received' || (bool) $priorPaidPayment;
            abort_if(! $restorePaidAccess && (int) $data['access_duration_days'] > 365, 422, 'Trial access cannot exceed 365 days.');

            $start = Carbon::parse($data['starts_at'], config('app.timezone'))->startOfDay();
            abort_if($start->lt($today), 422, 'Restored access cannot start in the past.');
            $end = $start->copy()->addDays((int) $data['access_duration_days']);
            $planId = $locked?->subscription_plan_id
                ?? $priorPaidPayment?->subscription_plan_id
                ?? $lockedBusiness->selected_plan_id
                ?? app(PlatformSettingsService::class)->current()->default_plan_id
                ?? SubscriptionPlan::query()->orderBy('id')->value('id');
            abort_unless($planId && SubscriptionPlan::query()->whereKey($planId)->exists(), 422, 'Choose a subscription plan before restoring access.');

            $old = [
                'status' => $locked?->status,
                'subscription_plan_id' => $locked?->subscription_plan_id,
                'payment_status' => $locked?->payment_status,
                'starts_at' => $locked?->starts_at?->toDateString(),
                'ends_at' => $locked?->ends_at?->toDateString(),
                'trial_start_at' => $locked?->trial_start_at?->toDateString(),
                'trial_end_at' => $locked?->trial_end_at?->toDateString(),
                'access_record_existed' => (bool) $locked,
            ];
            $values = [
                'subscription_plan_id' => $planId,
                'billing_cycle' => $locked?->billing_cycle ?? $priorPaidPayment?->billing_cycle ?? 'Custom',
                'amount' => $locked?->amount ?? $priorPaidPayment?->amount ?? 0,
                'payment_method' => $locked?->payment_method ?? $priorPaidPayment?->method,
                'payment_status' => $restorePaidAccess ? 'Received' : 'Pending',
                'payment_reference' => $locked?->payment_reference ?? $priorPaidPayment?->transaction_reference ?? $priorPaidPayment?->reference_number,
                'starts_at' => $start,
                'ends_at' => $end,
                'trial_start_at' => $restorePaidAccess ? $locked?->trial_start_at : $start,
                'trial_end_at' => $restorePaidAccess ? $locked?->trial_end_at : $end,
                'access_ended_at' => null,
                'status' => 'Trial',
                'note' => $data['note'] ?? $locked?->note,
                'renewed_at' => now(),
                'cancelled_at' => null,
                'cancellation_scheduled_at' => null,
                'cancellation_reason' => null,
            ];

            $updated = $locked
                ? tap($locked, fn (Subscription $subscription) => $subscription->update($values))
                : Subscription::create(['business_id' => $lockedBusiness->id] + $values);
            $updated = $lifecycle->synchronize($updated->fresh()->load('business.owner'));

            $new = [
                'status' => $updated->status,
                'restoration_type' => $restorePaidAccess ? 'Manual Paid Access Restore' : 'Manual Trial Access Restore',
                'subscription_plan_id' => $updated->subscription_plan_id,
                'payment_status' => $updated->payment_status,
                'access_start' => $updated->starts_at?->toDateString(),
                'access_end' => $updated->ends_at?->toDateString(),
                'access_duration_days' => (int) $data['access_duration_days'],
                'restored_by' => $request->user()?->id,
                'restored_at' => now()->toIso8601String(),
                'note' => $data['note'] ?? null,
            ];

            return [$updated, $old, $new, $restorePaidAccess];
        });

        if ($new['restoration_type'] === 'Manual Paid Access Restore') {
            app(RenewalInvoiceService::class)->generateDue();
        }
        $this->auditBusinessAccess($request, $updated->business, 'Business access restored', $updated, $old, $new);
        $end = ($updated->payment_status === 'Received'
            ? $updated->effectivePaidAccessEnd()
            : $updated->trial_end_at)?->format('n/j/Y') ?? 'the configured access end date';
        $updated->business?->owner?->notify(new SubscriptionStatusNotification(
            'Access restored',
            'Your '.app(PlatformSettingsService::class)->name().' '.($updated->payment_status === 'Received' ? 'paid' : 'trial').' access has been restored until '.$end.'.',
            $updated->business_id,
        ));

        $message = 'Business access restored successfully.';
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => $updated->status,
                'access_type' => $updated->payment_status === 'Received' ? 'paid' : 'trial',
                'access_start' => $updated->starts_at?->toDateString(),
                'access_end' => $updated->ends_at?->toDateString(),
            ]);
        }

        return back()->with('success', $message);
    }

    /** @param array<string, mixed> $state */
    private function accessPresentation(Business $business, array $state): array
    {
        $subscription = $state['subscription'];
        $renewalSecured = (bool) ($state['has_secured_upcoming_paid_renewal'] ?? false);
        $upcomingPaidCycle = $renewalSecured && $subscription
            ? app(SubscriptionLifecycleService::class)->upcomingPaidCycle($subscription)
            : null;
        $priorPaidPayment = ! $state['can_access_business'] && $subscription?->payment_status !== 'Received'
            ? PlatformPayment::query()
                ->where('business_id', $business->id)
                ->where('status', 'Received')
                ->whereNotNull('subscription_plan_id')
                ->whereNotNull('period_starts_at')
                ->whereNotNull('period_ends_at')
                ->orderByDesc('period_ends_at')
                ->orderByDesc('id')
                ->first()
            : null;
        $restorePaidAccess = (bool) $priorPaidPayment;
        $kind = 'restricted';
        $label = 'Access Restricted';
        if ($state['is_trial']) {
            $kind = $state['is_expired'] ? 'trial_expired' : ($state['is_expiring_soon'] ? 'trial_expiring' : 'trial_active');
            $label = match ($kind) {
                'trial_active' => 'Trial Active',
                'trial_expiring' => 'Trial Expiring',
                default => 'Trial Expired',
            };
        } elseif ($subscription && $subscription->payment_status === 'Received' && $state['is_scheduled']) {
            $kind = 'paid_scheduled';
            $label = 'Paid Scheduled';
        } elseif ($subscription && $subscription->payment_status === 'Received' && ! $state['is_expired'] && $state['is_active_period']) {
            $kind = $state['is_expiring_soon'] ? 'paid_expiring' : 'paid_active';
            $label = $kind === 'paid_expiring' ? 'Paid Expiring' : 'Paid Active';
        } elseif ($subscription && $subscription->payment_status === 'Received') {
            $label = 'Paid Expired / Restricted';
        }

        $days = $state['days_remaining'];
        $paidDays = $state['paid_days_remaining'];
        $paidRemainingLabel = $paidDays === null
            ? '—'
            : $paidDays.' day'.($paidDays === 1 ? '' : 's');
        $extraAccessDays = (int) ($state['extra_access_days'] ?? 0);
        $daysLabel = $days === null ? '—' : ($state['is_expired'] ? 'Expired' : ($days === 0 ? 'Ends today' : $days.' day'.($days === 1 ? '' : 's')));

        return [
            'business' => $business,
            'subscription' => $subscription,
            'kind' => $kind,
            'label' => $label,
            'trial_start' => $state['trial_start'],
            'trial_end' => $state['trial_end'],
            'paid_access_start' => $state['paid_access_start'],
            'original_paid_access_end' => $state['paid_access_end'],
            'paid_duration_days' => $state['paid_duration_days'],
            'paid_remaining_label' => $paidRemainingLabel,
            'renewal_secured' => $renewalSecured,
            'upcoming_paid_cycle' => $upcomingPaidCycle,
            'extra_access_days' => $extraAccessDays,
            'extended_days_label' => $extraAccessDays > 0 ? '+'.$extraAccessDays.' day'.($extraAccessDays === 1 ? '' : 's') : '—',
            'effective_access_end' => $state['effective_access_end'],
            'days_label' => $daysLabel,
            // Tables and operational access always use the effective end;
            // financial paid-period values above remain separate.
            'start_date' => $state['start_date'],
            'end_date' => $state['end_date'],
            'remaining_label' => $daysLabel,
            'can_manage_trial' => (bool) $subscription && $subscription->payment_status !== 'Received',
            'can_manage_paid' => (bool) $subscription && $subscription->payment_status === 'Received',
            'can_reactivate_paid' => (bool) $subscription
                && $subscription->payment_status === 'Received'
                && ! $state['can_access_business']
                && ($state['is_expired'] || in_array($subscription->status, ['Expired', 'Suspended'], true)),
            'can_restore_restricted' => ! $state['can_access_business']
                && $subscription?->payment_status !== 'Received',
            'restore_paid_access' => $restorePaidAccess,
            'restore_plan_name' => $restorePaidAccess ? $priorPaidPayment?->plan?->name : null,
            'reactivation_duration_days' => ($state['paid_duration_days'] ?? 0) > 0
                ? (int) $state['paid_duration_days']
                : max(1, (int) (app(PlatformSettingsService::class)->current()->default_paid_access_days ?: 30)),
            'restore_duration_days' => max(1, (int) (app(PlatformSettingsService::class)->current()->trial_days ?: 14)),
            'restore_paid_duration_days' => $priorPaidPayment?->period_starts_at && $priorPaidPayment?->period_ends_at
                ? max(1, $priorPaidPayment->period_starts_at->diffInDays($priorPaidPayment->period_ends_at))
                : max(1, (int) (app(PlatformSettingsService::class)->current()->default_paid_access_days ?: 30)),
            // Date management remains available for a historical/expired
            // trial so an administrator can restart it, but an already
            // expired trial has nothing left to end. Keep destructive end
            // actions limited to the currently active entitlement type.
            'can_end_trial' => in_array($kind, ['trial_active', 'trial_expiring'], true),
            'can_end_paid' => in_array($kind, ['paid_active', 'paid_expiring'], true),
        ];
    }

    /** @param array<string, mixed> $old @param array<string, mixed> $new */
    private function auditBusinessAccess(Request $request, ?Business $business, string $action, Subscription $subscription, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'actor_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role,
            'business_id' => $business?->id,
            'module' => 'Trial & Access',
            'action' => $action,
            'description' => $action,
            'record_type' => Subscription::class,
            'record_id' => $subscription->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => app(AuditIpResolver::class)->capture($request),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    private function canDeletePlatformPayment(PlatformPayment $payment): bool
    {
        if ($payment->status !== 'Received' || ! $payment->subscription || ! $payment->business) {
            return true;
        }

        $subscription = $payment->subscription;
        $state = app(SubscriptionLifecycleService::class)->forBusiness($payment->business);
        // Be deliberately conservative: while this business has active paid
        // access, none of its received payment history can be removed from
        // this screen. It prevents an ambiguous or legacy record from being
        // mistaken for a safe historical payment.
        $isCurrentPeriod = $subscription->payment_status === 'Received'
            && $state['is_active_period'];

        return ! $isCurrentPeriod;
    }

    private function recordManualSubscriptionPayment(Subscription $subscription, Request $request, ?string $previousPaymentStatus): void
    {
        if ($subscription->payment_status !== 'Received' || $previousPaymentStatus === 'Received') {
            return;
        }

        $reference = $subscription->payment_reference ?: 'MANUAL-'.$subscription->id.'-'.now()->format('YmdHis');
        PlatformPayment::create([
            'business_id' => $subscription->business_id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $subscription->subscription_plan_id,
            'billing_cycle' => $subscription->billing_cycle,
            'amount' => $subscription->amount,
            'method' => $subscription->payment_method ?: 'Manual',
            'reference_number' => $reference,
            'transaction_reference' => $subscription->payment_reference,
            'status' => 'Received',
            'paid_at' => now(),
            'submitted_at' => now(),
            'verified_at' => now(),
            'verified_by' => $request->user()?->id,
            'period_starts_at' => $subscription->starts_at,
            'period_ends_at' => $subscription->ends_at,
            'notes' => $subscription->note,
            'recorded_by' => $request->user()?->id,
        ]);
        $subscription->business?->owner?->notify(new SubscriptionStatusNotification(
            'Payment recorded',
            'Your payment has been recorded and your workspace access has been updated.',
            $subscription->business_id,
        ));
    }

    public function subscriptionChangeRequestReview(SubscriptionChangeRequest $changeRequest)
    {
        return view('super-admin.subscriptions.review-request', [
            'changeRequest' => $changeRequest->load(['business.owner', 'subscription.plan', 'currentPlan', 'requestedPlan', 'requester']),
            'plans' => SubscriptionPlan::query()->where('status', 'Active')->whereNull('archived_at')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function updateSubscriptionChangeRequestReview(Request $request, SubscriptionChangeRequest $changeRequest)
    {
        abort_unless(in_array($changeRequest->status, ['Pending', 'Changes Requested'], true), 422, 'This subscription request can no longer be updated.');

        $data = $request->validate([
            'requested_plan_id' => ['required', 'integer'],
            'billing_cycle' => ['required', 'in:Monthly,Yearly'],
            'trial_eligible' => ['nullable', 'boolean'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $plan = SubscriptionPlan::query()->where('status', 'Active')->whereNull('archived_at')->findOrFail($data['requested_plan_id']);
        $trialEligible = $request->boolean('trial_eligible');
        $trialDays = $trialEligible ? (int) ($data['trial_days'] ?? $plan->trial_days) : 0;
        $startsAt = $data['starts_at'] ?? now()->toDateString();
        $endsAt = $data['ends_at'] ?? ($data['billing_cycle'] === 'Yearly' ? Carbon::parse($startsAt)->addYear()->toDateString() : Carbon::parse($startsAt)->addMonth()->toDateString());

        $old = $changeRequest->only(['requested_plan_id', 'billing_cycle', 'expected_amount', 'trial_eligible', 'trial_days', 'starts_at', 'ends_at', 'effective_at']);
        $changeRequest->update([
            'requested_plan_id' => $plan->id,
            'billing_cycle' => $data['billing_cycle'],
            'expected_amount' => $plan->priceFor($data['billing_cycle']),
            'trial_eligible' => $trialEligible,
            'trial_days' => $trialDays,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'effective_at' => $data['effective_at'] ?? $changeRequest->effective_at,
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        $this->audit('Subscription request review updated', $request, 'Subscriptions', $changeRequest->id, $old, $changeRequest->fresh()->only(array_keys($old)));
        $changeRequest->business?->owner?->notify(new SubscriptionStatusNotification('Subscription Request Updated', app(PlatformSettingsService::class)->name().' updated the review details for your subscription request.', $changeRequest->business_id));

        return back()->with('success', 'Subscription request review details updated.');
    }

    public function reviewSubscriptionChangeRequest(Request $request, SubscriptionChangeRequest $changeRequest)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:Approved,Activate,Rejected,Changes Requested'],
            'admin_note' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('decision') === 'Rejected')],
        ]);
        abort_unless(in_array($changeRequest->status, ['Pending', 'Changes Requested'], true), 422, 'This subscription request has already been reviewed.');

        DB::transaction(function () use ($request, $changeRequest, $data) {
            $changeRequest = SubscriptionChangeRequest::whereKey($changeRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($changeRequest->status, ['Pending', 'Changes Requested'], true), 422, 'This subscription request has already been reviewed.');
            $requestStatus = $data['decision'] === 'Activate' ? 'Active' : $data['decision'];
            $changeRequest->update([
                'status' => $requestStatus,
                'admin_note' => $data['admin_note'] ?? null,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
            ]);
            if (in_array($data['decision'], ['Approved', 'Activate'], true)) {
                $plan = SubscriptionPlan::query()->where('status', 'Active')->whereNull('archived_at')->findOrFail($changeRequest->requested_plan_id);
                $subscription = Subscription::where('business_id', $changeRequest->business_id)->lockForUpdate()->first() ?: new Subscription(['business_id' => $changeRequest->business_id]);
                $type = $changeRequest->type === 'Subscription' ? 'New Subscription' : $changeRequest->type;

                if ($type === 'Cancellation') {
                    abort_unless($subscription->exists && in_array($subscription->status, ['Trial', 'Active', 'Expiring'], true), 422, 'This subscription can no longer be scheduled for cancellation.');
                    $subscription->update([
                        'cancellation_scheduled_at' => $changeRequest->effective_at ?? $subscription->ends_at,
                        'cancellation_reason' => $changeRequest->note,
                    ]);
                    $title = 'Subscription Cancellation Scheduled';
                    $message = 'Your subscription will remain active until '.($subscription->cancellation_scheduled_at?->format('d M, Y') ?? 'its expiry date').'.';
                } elseif ($type === 'Resume Cancellation') {
                    abort_unless($subscription->exists && $subscription->cancellation_scheduled_at, 422, 'There is no scheduled cancellation to resume.');
                    $subscription->update(['cancellation_scheduled_at' => null, 'cancellation_reason' => null]);
                    $title = 'Subscription Cancellation Resumed';
                    $message = 'Your scheduled subscription cancellation has been removed.';
                } else {
                $start = $changeRequest->effective_at ?? $changeRequest->starts_at ?? now();
                $end = $changeRequest->ends_at ?? ($changeRequest->billing_cycle === 'Yearly' ? Carbon::parse($start)->addYear() : Carbon::parse($start)->addMonth());
                $trial = $type === 'New Subscription' && $data['decision'] === 'Approved' && $changeRequest->trial_eligible && (int) $changeRequest->trial_days > 0;
                if (! $trial && $data['decision'] === 'Activate' && $subscription->exists && $subscription->payment_status !== 'Received') {
                    app(\App\Services\SubscriptionAccessHistoryService::class)->recordTrialConvertedToPaid(
                        $subscription,
                        null,
                        $request->user(),
                        'Subscription request #'.$changeRequest->id,
                    );
                }
                $subscription->fill([
                    'subscription_plan_id' => $plan->id,
                    'billing_cycle' => $changeRequest->billing_cycle,
                    'amount' => $plan->priceFor($changeRequest->billing_cycle),
                    'payment_method' => $changeRequest->payment_method,
                    'starts_at' => Carbon::parse($start)->toDateString(),
                    'ends_at' => $trial ? Carbon::parse($start)->addDays((int) $changeRequest->trial_days)->toDateString() : Carbon::parse($end)->toDateString(),
                    'trial_start_at' => $trial ? Carbon::parse($start)->toDateString() : $subscription->trial_start_at,
                    'trial_end_at' => $trial ? Carbon::parse($start)->addDays((int) $changeRequest->trial_days)->toDateString() : $subscription->trial_end_at,
                    // An Approved request is the business decision that applies
                    // this plan. Keeping the request as Approved while leaving
                    // the actual subscription Pending creates two conflicting
                    // sources of truth for the business workspace.
                    'status' => $trial ? 'Trial' : 'Active',
                    'payment_status' => $data['decision'] === 'Activate' ? 'Received' : 'Pending',
                    'renewed_at' => now(),
                ])->save();
                $title = $trial ? 'Trial Activated' : 'Subscription Activated';
                $message = $trial
                    ? 'Your '.$plan->name.' trial is now active.'
                    : 'Your '.$plan->name.' subscription is now active.'.($data['decision'] === 'Approved' ? ' Payment confirmation remains pending.' : '');
                }
                $changeRequest->business?->owner?->notify(new SubscriptionStatusNotification($title, $message, $changeRequest->business_id));
            } elseif ($data['decision'] === 'Changes Requested') {
                $message = app(PlatformSettingsService::class)->name().' requested changes to your '.$changeRequest->type.' request.';
                if (filled($data['admin_note'] ?? null)) {
                    $message .= ' Note: '.$data['admin_note'];
                }
                $changeRequest->business?->owner?->notify(new SubscriptionStatusNotification('Changes Requested', $message, $changeRequest->business_id));
            } else {
                $changeRequest->business?->owner?->notify(new SubscriptionStatusNotification('Subscription Request Rejected', 'Your '.$changeRequest->type.' request was not approved.', $changeRequest->business_id));
            }
        });

        $this->audit('Subscription '.$changeRequest->type.' '.strtolower($data['decision']), $request, 'Subscriptions', $changeRequest->id, null, ['decision' => $data['decision'], 'effective_at' => $changeRequest->effective_at?->toDateString()]);
        return back()->with('success', in_array($data['decision'], ['Approved', 'Activate'], true) ? 'Subscription request processed successfully.' : ($data['decision'] === 'Changes Requested' ? 'Changes requested from the business owner.' : 'Subscription request rejected.'));
    }

    private function expireDueSubscriptions(Request $request): void
    {
        // The scheduled lifecycle command owns notifications. Screen visits
        // only synchronize access state and must not generate alerts.
        app(SubscriptionLifecycleService::class)->synchronizeAll(false);
    }

    private function resolvedSubscriptionStatus(string $requestedStatus, ?string $endsAt): string
    {
        return $requestedStatus === 'Active' && $endsAt && now()->startOfDay()->gt(\Carbon\Carbon::parse($endsAt)->startOfDay())
            ? 'Expired'
            : $requestedStatus;
    }

    private function assertSubscriptionTransition(string $oldStatus, string $newStatus): void
    {
        if ($oldStatus === $newStatus) {
            return;
        }

        $allowed = [
            'Pending' => ['Trial', 'Active'],
            'Trial' => ['Active', 'Expired'],
            'Active' => ['Expiring', 'Expired', 'Suspended', 'Cancelled'],
            'Expiring' => ['Active', 'Expired', 'Suspended', 'Cancelled'],
            'Expired' => ['Active', 'Trial'],
            'Suspended' => ['Active'],
            'Cancelled' => ['Active'],
        ];

        if (! in_array($newStatus, $allowed[$oldStatus] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'This subscription status transition is not allowed.']);
        }
    }

    private function planData(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('subscription_plans', 'name')->ignore($plan?->id)],
            'price' => ['nullable', 'integer', 'min:0'],
            'monthly_price' => ['nullable', 'integer', 'min:0'],
            'yearly_price' => ['nullable', 'integer', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'product_limit' => ['required', 'integer', 'min:0'],
            'staff_limit' => ['required', 'integer', 'min:0'],
            'order_limit' => ['required', 'integer', 'min:0'],
            'is_public' => ['nullable', 'boolean'],
            'is_recommended' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        $data['monthly_price'] = $data['monthly_price'] ?? $data['price'] ?? $plan?->monthly_price ?? $plan?->price ?? 0;
        $data['yearly_price'] = $data['yearly_price'] ?? $plan?->yearly_price ?? ($data['monthly_price'] * 12);
        if ((int) $data['yearly_price'] <= 0 && (int) $data['monthly_price'] > 0) {
            $data['yearly_price'] = (int) $data['monthly_price'] * 12;
        }
        $data['trial_days'] = $data['trial_days'] ?? $plan?->trial_days ?? 14;
        unset($data['price']);
        $data['is_public'] = $request->has('is_public') ? $request->boolean('is_public') : ($plan?->is_public ?? true);
        $data['is_recommended'] = $request->has('is_recommended') ? $request->boolean('is_recommended') : ($plan?->is_recommended ?? false);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $slug = \Illuminate\Support\Str::slug($data['name']);
        $data['slug'] = $plan && $plan->slug === $slug ? $slug : $this->uniquePlanSlug($slug, $plan?->id);

        return $data;
    }

    private function lineList(?string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    private function uniquePlanSlug(string $base, ?int $ignoreId = null): string
    {
        $base = $base ?: 'plan';
        $slug = $base;
        $suffix = 2;
        while (SubscriptionPlan::query()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function supportTickets(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', Rule::in(['Low', 'Medium', 'High', 'Urgent'])],
            'status' => ['nullable', Rule::in(['Open', 'Assigned', 'In Progress', 'Waiting for User', 'Escalated', 'Resolved', 'Closed', 'Reopened', 'Pending'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'clear' => ['nullable', 'boolean'],
        ]);

        // Date filters are intentionally optional. A support desk should not
        // silently hide open conversations merely because they were created
        // before today.
        $filters += ['date_from' => null, 'date_to' => null];

        $tickets = SupportTicket::with(['business', 'user', 'assignedAdmin', 'assignedSubAdmin', 'messages.sender'])
            ->when($filters['search'] ?? null, function ($query, $value) {
                $query->where(function ($inner) use ($value) {
                    $inner->where('ticket_number', 'like', "%{$value}%")
                        ->orWhere('subject', 'like', "%{$value}%")
                        ->orWhere('contact_name', 'like', "%{$value}%")
                        ->orWhere('contact_email', 'like', "%{$value}%")
                        ->orWhere('contact_phone', 'like', "%{$value}%")
                        ->orWhereHas('business', fn ($business) => $business->where('business_name', 'like', "%{$value}%"));
                });
            })
            ->when($filters['type'] ?? null, fn ($query, $value) => $query->where('type', $value))
            ->when($filters['priority'] ?? null, fn ($query, $value) => $query->where('priority', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->where('created_at', '>=', Carbon::parse($value, config('app.timezone'))->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->where('created_at', '<=', Carbon::parse($value, config('app.timezone'))->endOfDay()))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('super-admin.support-tickets', [
            'tickets' => $tickets,
            'filters' => $filters,
            'ticketTypes' => SupportTicket::query()->whereNotNull('type')->distinct()->orderBy('type')->pluck('type'),
            'supportHandlers' => User::query()
                ->whereIn('role', ['super_admin', 'platform_admin', 'platform_sub_admin'])
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'role']),
        ]);
    }

    public function updateTicket(Request $request, SupportTicket $ticket)
    {
        abort_if(in_array($ticket->status, ['Resolved', 'Closed'], true), 422, 'Finalized tickets must be reopened before they can be managed.');

        $data = $request->validate([
            'action' => ['nullable', Rule::in(['save', 'reply'])],
            'message' => ['nullable', 'string', 'max:2000'],
            'admin_reply' => ['nullable'],
            'status' => ['required', 'in:Open,Assigned,In Progress,Waiting for User,Escalated,Resolved,Closed,Reopened,Pending'],
            'priority' => ['nullable', 'in:Low,Medium,High,Urgent'],
            'assigned_admin_id' => ['nullable', 'integer', 'exists:users,id'],
            'resolution' => ['nullable', 'string'],
            'internal_note' => ['nullable', 'boolean'],
        ]);

        if (($data['action'] ?? 'save') === 'reply' && blank($data['message'] ?? null)) {
            throw ValidationException::withMessages(['message' => 'Enter a reply or internal note before sending.']);
        }

        $assignedHandler = null;
        if (filled($data['assigned_admin_id'] ?? null)) {
            $assignedHandler = User::query()
                ->whereIn('role', ['super_admin', 'platform_admin', 'platform_sub_admin'])
                ->where('status', 'active')
                ->find($data['assigned_admin_id']);

            if (! $assignedHandler) {
                throw ValidationException::withMessages(['assigned_admin_id' => 'Choose an active platform support handler.']);
            }
        }

        if (!$ticket->ticket_number) {
            $ticket->ticket_number = 'TF-TKT-'.now()->format('Ymd').'-'.str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT);
        }

        $old = $ticket->only(['status', 'priority', 'assigned_admin_id', 'assigned_sub_admin_id']);
        $ticket->fill([
            'admin_reply' => $data['admin_reply'] ?? $ticket->admin_reply,
            'status' => $data['status'],
            'priority' => $data['priority'] ?? $ticket->priority,
            'assigned_admin_id' => $assignedHandler?->id,
            'assigned_sub_admin_id' => null,
            'resolution' => $data['resolution'] ?? $ticket->resolution,
        ]);
        if (in_array($ticket->status, ['Resolved'], true) && !$ticket->resolved_at) $ticket->resolved_at = now();
        if (in_array($ticket->status, ['Closed'], true) && !$ticket->closed_at) $ticket->closed_at = now();
        if (!empty($data['message']) && !$ticket->first_response_at) $ticket->first_response_at = now();
        $ticket->save();

        if (!empty($data['message'])) {
            $ticket->messages()->create([
                'sender_id' => auth()->id(),
                'message' => $data['message'],
                'internal_note' => $request->boolean('internal_note'),
            ]);
        }

        $this->audit('Updated ticket '.$ticket->ticket_number, $request, 'Complaints & Support', $ticket->id, $old, $ticket->fresh()->only(['status', 'priority', 'assigned_admin_id', 'assigned_sub_admin_id']));
        return back()->with('success', 'Ticket updated.');
    }

    public function newsletterSubscribers(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
        ]);

        $subscribers = NewsletterSubscriber::query()
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->where('email', 'like', "%{$value}%"))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->orderByDesc('subscribed_at')
            ->paginate(10)
            ->withQueryString();

        $summary = $this->newsletterSubscriberSummary();

        return view('super-admin.newsletter-subscribers', compact('subscribers', 'filters', 'summary'));
    }

    public function updateNewsletterSubscriber(Request $request, NewsletterSubscriber $subscriber): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $subscriber->update($data);

        $message = $subscriber->status === 'Active'
            ? 'Subscriber activated.'
            : 'Subscriber deactivated.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'subscriber' => [
                    'id' => $subscriber->id,
                    'status' => $subscriber->status,
                    'subscribed_at' => $subscriber->subscribed_at?->toIso8601String(),
                    'updated_at' => $subscriber->updated_at?->toIso8601String(),
                ],
                'summary' => $this->newsletterSubscriberSummary(),
            ]);
        }

        return back()->with('success', $message);
    }

    public function destroyNewsletterSubscriber(Request $request, NewsletterSubscriber $subscriber): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $data = $request->validate(['confirmation' => ['required', 'string']]);

        if (! hash_equals($subscriber->email, $data['confirmation'])) {
            throw ValidationException::withMessages(['confirmation' => 'Type the exact subscriber email to confirm permanent deletion.']);
        }

        $email = $subscriber->email;
        $id = $subscriber->id;
        $subscriber->delete();
        $this->audit('Newsletter subscriber permanently deleted', $request, 'Newsletter Subscribers', $id, null, ['subscriber_id' => $id, 'email' => $email]);

        return redirect()->route('admin.newsletter-subscribers.index')->with('success', 'Newsletter subscriber permanently deleted.');
    }

    /**
     * Keep the audience overview tied to the same persisted subscriber data as
     * the table. Inactive records remain part of the audience history.
     */
    private function newsletterSubscriberSummary(): array
    {
        $summary = NewsletterSubscriber::query()
            ->selectRaw(
                'COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as inactive, SUM(CASE WHEN COALESCE(subscribed_at, created_at) >= ? THEN 1 ELSE 0 END) as new_this_month',
                ['Active', 'Inactive', now()->startOfMonth()]
            )
            ->first();

        return [
            'total' => (int) ($summary->total ?? 0),
            'active' => (int) ($summary->active ?? 0),
            'inactive' => (int) ($summary->inactive ?? 0),
            'new_this_month' => (int) ($summary->new_this_month ?? 0),
        ];
    }

    public function payments(Request $request)
    {
        $filters = $request->validate([
            'payment_id' => ['nullable', 'integer', 'exists:platform_payments,id'],
            'business_id' => ['nullable', 'exists:businesses,id'],
            'status' => ['nullable', Rule::in(['Received', 'Pending', 'Rejected', 'Failed', 'Refunded'])],
            'method' => ['nullable', Rule::in(['Cash', 'Bank Transfer', 'Jazz Cash', 'Easypaisa', 'Cheque', 'Other'])],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'clear' => ['nullable', 'boolean'],
            'tab' => ['nullable', Rule::in(['payments', 'renewals'])],
            'renewal_invoice_id' => ['nullable', 'integer', 'exists:renewal_invoices,id'],
        ]);

        $filters += ['date_from' => null, 'date_to' => null];

        $payments = PlatformPayment::with(['business.owner', 'subscription.plan', 'plan', 'recordedBy', 'verifiedBy'])
            ->when($filters['payment_id'] ?? null, fn ($query, $value) => $query->whereKey($value))
            ->when($filters['business_id'] ?? null, fn ($query, $value) => $query->where('business_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['method'] ?? null, fn ($query, $value) => $query->where('method', $value))
            ->when($filters['search'] ?? null, function ($query, $value) {
                $query->where(function ($inner) use ($value) {
                    $inner->where('reference_number', 'like', "%{$value}%")
                        ->orWhereHas('business.owner', fn ($owner) => $owner->where('name', 'like', "%{$value}%"));
                });
            })
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->where('paid_at', '>=', Carbon::parse($value)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->where('paid_at', '<=', Carbon::parse($value)->endOfDay()))
            ->latest('paid_at')
            ->paginate(10)
            ->withQueryString();
        $payments->getCollection()->each(fn (PlatformPayment $payment) => $payment->setAttribute('can_delete', $this->canDeletePlatformPayment($payment)));

        $renewalInvoices = RenewalInvoice::with(['business.owner', 'subscription'])
            ->latest()
            ->paginate(10, ['*'], 'renewal_page')
            ->withQueryString();
        $renewals = app(RenewalInvoiceService::class);
        $renewalInvoices->getCollection()->each(function (RenewalInvoice $invoice) use ($renewals): void {
            $invoice->setAttribute('can_manage', $renewals->canManage($invoice));
        });
        $recordRenewal = isset($filters['renewal_invoice_id'])
            ? RenewalInvoice::with(['business.owner', 'subscription'])->findOrFail($filters['renewal_invoice_id'])
            : null;

        $settings = app(PlatformSettingsService::class)->current();
        $paymentNow = now(config('app.timezone'));
        $defaultPaidAccessDays = max(1, (int) ($settings->default_paid_access_days ?: 30));
        $businesses = Business::with('subscription.plan')->orderBy('business_name')->get();
        $lifecycle = app(SubscriptionLifecycleService::class);
        $paymentDateDefaults = $businesses->mapWithKeys(function (Business $business) use ($lifecycle, $paymentNow, $defaultPaidAccessDays): array {
            $subscription = $business->subscription;
            $state = $lifecycle->state($subscription);
            $hasActivePaidAccess = $subscription
                && $subscription->payment_status === 'Received'
                && ! $state['is_trial']
                && $state['is_active_period']
                && $subscription->ends_at;

            // Renewals start on the next calendar day after an active paid
            // period ends, so recording a renewal never removes paid days
            // the business already has. Expired/restricted businesses begin
            // from the current application date.
            $startsAt = $hasActivePaidAccess
                ? Carbon::parse($state['effective_access_end']->toDateString(), config('app.timezone'))->addDay()->startOfDay()
                : $paymentNow->copy()->startOfDay();

            return [$business->id => [
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $startsAt->copy()->addDays($defaultPaidAccessDays)->toDateString(),
            ]];
        })->all();

        return view('super-admin.payments', [
            'payments' => $payments,
            'renewalInvoices' => $renewalInvoices,
            'recordRenewal' => $recordRenewal,
            'tab' => $filters['tab'] ?? 'payments',
            'businesses' => $businesses,
            'paymentNow' => $paymentNow,
            'defaultPaidAccessDays' => $defaultPaidAccessDays,
            'paymentDateDefaults' => $paymentDateDefaults,
            'filters' => $filters,
        ]);
    }

    public function storePlatformPayment(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'exists:businesses,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(['Cash', 'Bank Transfer', 'Jazz Cash', 'Easypaisa', 'Cheque', 'Other'])],
            'status' => ['required', Rule::in(['Received', 'Pending', 'Rejected', 'Refunded'])],
            'paid_at' => ['required', 'date'],
            'transaction_reference' => ['nullable', 'string', 'max:120'],
            'period_starts_at' => ['nullable', 'date'],
            'period_ends_at' => ['nullable', 'date', 'after:period_starts_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'renewal_invoice_id' => ['nullable', 'integer', 'exists:renewal_invoices,id'],
        ]);

        if ($data['status'] === 'Received' && (empty($data['period_starts_at']) || empty($data['period_ends_at']))) {
            throw ValidationException::withMessages(['period_starts_at' => 'Paid access start and end dates are required for a received payment.']);
        }

        if ($data['status'] === 'Received' && ! empty($data['period_ends_at'])) {
            $accessEnd = Carbon::parse($data['period_ends_at'], config('app.timezone'))->endOfDay();
            if ($accessEnd->lt(now(config('app.timezone')))) {
                throw ValidationException::withMessages(['period_ends_at' => 'The paid access end date cannot be in the past.']);
            }
        }

        $isEarlyRenewal = false;
        $payment = DB::transaction(function () use ($data, $request, &$business, &$isEarlyRenewal) {
            $business = Business::with('subscription')->lockForUpdate()->findOrFail($data['business_id']);
            $renewalInvoice = isset($data['renewal_invoice_id'])
                ? RenewalInvoice::lockForUpdate()->findOrFail($data['renewal_invoice_id'])
                : null;
            if ($renewalInvoice && ($renewalInvoice->business_id !== $business->id || ! app(RenewalInvoiceService::class)->canRecordPayment($renewalInvoice))) {
                throw ValidationException::withMessages(['renewal_invoice_id' => 'This renewal invoice cannot be recorded against the selected business.']);
            }
            $subscription = $business->subscription;
            if (! $subscription) {
                $legacyPlanId = app(PlatformSettingsService::class)->current()->default_plan_id
                    ?? SubscriptionPlan::query()->orderBy('id')->value('id');
                abort_unless($legacyPlanId, 422, 'A legacy access record is required before paid access can be activated.');
                $subscription = Subscription::create(['business_id' => $business->id, 'subscription_plan_id' => $legacyPlanId, 'billing_cycle' => 'Custom', 'amount' => 0, 'starts_at' => now()->toDateString(), 'ends_at' => now()->subDay()->toDateString(), 'status' => 'Expired', 'payment_status' => 'Pending']);
            }

            $lifecycle = app(SubscriptionLifecycleService::class);
            $isEarlyRenewal = $data['status'] === 'Received'
                && $lifecycle->hasActivePaidCycle($subscription);

            // A received payment during a valid paid period is a paid next
            // cycle, not a replacement for the current entitlement.  Anchor
            // it after the real effective end even if an old form submitted a
            // stale date.  The entered duration remains unchanged.
            if ($isEarlyRenewal) {
                if ($lifecycle->upcomingPaidCycle($subscription)) {
                    throw ValidationException::withMessages(['period_starts_at' => 'An upcoming paid renewal already exists for this business.']);
                }

                $requestedStart = Carbon::parse($data['period_starts_at'], config('app.timezone'))->startOfDay();
                $requestedEnd = Carbon::parse($data['period_ends_at'], config('app.timezone'))->startOfDay();
                $durationDays = max(1, $requestedStart->diffInDays($requestedEnd));
                $nextStart = Carbon::parse($subscription->effectivePaidAccessEnd()->toDateString(), config('app.timezone'))->addDay()->startOfDay();
                $data['period_starts_at'] = $nextStart->toDateString();
                $data['period_ends_at'] = $nextStart->copy()->addDays($durationDays)->toDateString();
            }

            $paymentData = collect($data)->except('renewal_invoice_id')->all();
            $payment = PlatformPayment::create($paymentData + ['subscription_id' => $subscription->id, 'subscription_plan_id' => $subscription->subscription_plan_id, 'billing_cycle' => 'Custom', 'submitted_at' => now(), 'recorded_by' => $request->user()->id]);
            $payment->update(['reference_number' => 'PP-'.now()->format('Ymd').'-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)]);

            if ($data['status'] === 'Received' && ! $isEarlyRenewal) {
                if ($subscription->payment_status !== 'Received') {
                    app(\App\Services\SubscriptionAccessHistoryService::class)
                        ->recordTrialConvertedToPaid($subscription, $payment, $request->user());
                }
                $old = $subscription->only(['status', 'amount', 'starts_at', 'ends_at', 'trial_start_at', 'trial_end_at', 'payment_status']);
                $subscription->update(['billing_cycle' => 'Custom', 'amount' => $data['amount'], 'payment_method' => $data['method'], 'payment_status' => 'Received', 'payment_reference' => $data['transaction_reference'] ?? $payment->reference_number, 'starts_at' => $data['period_starts_at'], 'ends_at' => $data['period_ends_at'], 'access_ended_at' => null, 'note' => $data['notes'] ?? null, 'status' => 'Active', 'renewed_at' => now()]);
                $payment->update(['verified_at' => now(), 'verified_by' => $request->user()->id]);
                if ($renewalInvoice) {
                    app(RenewalInvoiceService::class)->markPaid($renewalInvoice, $payment);
                }
                $this->auditBusinessAccess($request, $business, 'Paid access activated', $subscription->fresh(), $old, $subscription->fresh()->only(['status', 'amount', 'starts_at', 'ends_at', 'trial_start_at', 'trial_end_at', 'payment_status']));
                $business->owner?->notify(new SubscriptionStatusNotification('Paid access activated', 'Your workspace access is active until '.Carbon::parse($data['period_ends_at'])->format('d M, Y').'.', $business->id, $payment->id));
            } elseif ($data['status'] === 'Received') {
                // Keep the currently active paid cycle unchanged.  This
                // payment is already Received/Paid and will be promoted by
                // the lifecycle service on its scheduled start date.
                $payment->update(['verified_at' => now(), 'verified_by' => $request->user()->id]);
                if ($renewalInvoice) {
                    app(RenewalInvoiceService::class)->markPaid($renewalInvoice, $payment);
                }
                if ($lifecycle->hasSecuredUpcomingPaidRenewal($subscription)) {
                    $lifecycle->resolveSecuredPaidRenewalNotifications($subscription);
                }
                $currentEnd = $subscription->effectivePaidAccessEnd()?->format('d M, Y');
                $nextStart = Carbon::parse($data['period_starts_at'], config('app.timezone'))->format('d M, Y');
                $business->owner?->notify(new SubscriptionStatusNotification(
                    'Renewal payment received',
                    "Your current access remains active until {$currentEnd}. Your renewed access will continue automatically from {$nextStart}.",
                    $business->id,
                    $payment->id,
                ));
            } elseif ($renewalInvoice) {
                // Keep one source of truth for a renewal payment awaiting
                // verification. This also lets attention counters treat the
                // invoice and its pending payment as one work item.
                app(RenewalInvoiceService::class)->markPendingPayment($renewalInvoice, $payment);
            }

            return $payment;
        });

        $this->audit('Recorded custom payment from '.$business->business_name, $request, 'Platform Payments', $payment->id, null, $payment->only(['amount', 'method', 'status', 'paid_at', 'period_starts_at', 'period_ends_at']));

        if ($data['status'] === 'Received') {
            // A manually recorded short/custom period can already be inside
            // the reminder window. Generate its one renewal invoice now;
            // the scheduled command remains the recovery backstop.
            app(RenewalInvoiceService::class)->generateDue();
        }

        $success = $data['status'] === 'Received'
            ? ($isEarlyRenewal
                ? 'Renewal payment recorded. Current paid access remains active and the next cycle is scheduled automatically.'
                : 'Custom payment recorded and paid access activated for '.$business->business_name.'.')
            : 'Custom payment recorded for '.$business->business_name.'.';

        return back()->with('success', $success);
    }

    public function approvePlatformPayment(Request $request, PlatformPayment $payment)
    {
        $approved = app(SubscriptionPaymentService::class)->approve($payment, $request->user());
        $this->audit('Verified subscription payment '.$approved->reference_number, $request, 'Platform Payments', $approved->id, ['status' => 'Pending'], ['status' => 'Received', 'subscription_id' => $approved->subscription_id]);

        return back()->with('success', 'Payment verified and subscription activated successfully.');
    }

    public function rejectPlatformPayment(Request $request, PlatformPayment $payment)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);
        $rejected = app(SubscriptionPaymentService::class)->reject($payment, $request->user(), $data['rejection_reason']);
        $this->audit('Rejected subscription payment '.$rejected->reference_number, $request, 'Platform Payments', $rejected->id, ['status' => 'Pending'], ['status' => 'Rejected']);

        return back()->with('success', 'Payment rejected. The business can submit another payment.');
    }

    public function destroyPlatformPayment(Request $request, PlatformPayment $payment): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $payment = DB::transaction(function () use ($payment, $request) {
            $payment = PlatformPayment::with(['business', 'subscription'])->lockForUpdate()->findOrFail($payment->id);
            if (! $this->canDeletePlatformPayment($payment)) {
                throw ValidationException::withMessages(['payment' => 'This payment is the current active paid-access record. End or replace that access period before deleting the payment record.']);
            }

            $details = $payment->only(['id', 'business_id', 'reference_number', 'amount', 'method', 'status', 'period_starts_at', 'period_ends_at']);
            $businessName = $payment->business?->business_name ?? 'Unknown business';
            $payment->delete();
            $this->audit('Payment record deleted: '.$details['reference_number'].' for '.$businessName, $request, 'Platform Payments', $details['id'], $details, ['deleted' => true]);

            return $payment;
        });

        return back()->with('success', 'Payment record '.$payment->reference_number.' was deleted. The business and its access record were not changed.');
    }

    public function platformPaymentProof(PlatformPayment $payment)
    {
        abort_unless($payment->payment_proof && Storage::disk('local')->exists($payment->payment_proof), 404);
        return Storage::disk('local')->download($payment->payment_proof, 'subscription-payment-'.$payment->reference_number.'.'.pathinfo($payment->payment_proof, PATHINFO_EXTENSION));
    }

    public function platformPaymentReceipt(PlatformPayment $payment)
    {
        abort_unless($payment->status === 'Received', 404);
        $payment->load(['business', 'plan', 'subscription.plan', 'recordedBy', 'verifiedBy']);

        return Pdf::loadView('super-admin.payments.receipt-pdf', [
            'business' => $payment->business,
            'payment' => $payment,
            'platformName' => app(PlatformSettingsService::class)->name(),
        ])->setPaper('a4')
            // stream() sends an inline PDF response. The dropdown link opens
            // it in a separate tab, rather than forcing a file download.
            ->stream('payment-receipt-'.($payment->reference_number ?: $payment->id).'.pdf');
    }

    public function renewalInvoicePdf(Request $request, RenewalInvoice $invoice)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $invoice->load(['business.owner', 'subscription', 'payment']);
        $lifecycle = app(SubscriptionLifecycleService::class);
        $upcomingPaidCycle = $invoice->subscription
            ? $lifecycle->upcomingPaidCycle($invoice->subscription)
            : null;
        $renewalSecured = $invoice->status === RenewalInvoice::STATUS_PAID
            && $invoice->payment?->status === 'Received'
            && $upcomingPaidCycle?->id === $invoice->payment?->id
            && $invoice->subscription
            && $lifecycle->hasSecuredUpcomingPaidRenewal($invoice->subscription);
        $platformSettings = app(PlatformSettingsService::class)->current();
        $platformLogoDataUri = null;
        $platformLogoPath = preg_replace('#^(?:public/|storage/)#', '', ltrim((string) $platformSettings->logo, '/'));

        // Dompdf renders local image data reliably without depending on a
        // public URL, while unsupported/missing logo files retain the compact
        // branded mark in the invoice view.
        if (filled($platformLogoPath)) {
            $publicDisk = Storage::disk('public');
            if ($publicDisk->exists($platformLogoPath)) {
                $mime = $publicDisk->mimeType($platformLogoPath);
                $logoContents = $publicDisk->get($platformLogoPath);
                if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true) && is_string($logoContents)) {
                    $platformLogoDataUri = 'data:'.$mime.';base64,'.base64_encode($logoContents);
                }
            }
        }

        return Pdf::loadView('super-admin.renewal-invoices.pdf', [
            'invoice' => $invoice,
            'business' => $invoice->business,
            'owner' => $invoice->business?->owner,
            'platformName' => $platformSettings->company_name ?: app(PlatformSettingsService::class)->name(),
            'platformSettings' => $platformSettings,
            'platformLogoDataUri' => $platformLogoDataUri,
            'renewalSecured' => $renewalSecured,
            'upcomingPaidCycle' => $renewalSecured ? $upcomingPaidCycle : null,
        ])->setPaper('a4')->stream('renewal-invoice-'.$invoice->invoice_number.'.pdf');
    }

    public function sendRenewalInvoiceEmail(Request $request, RenewalInvoice $invoice): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $invoice->load(['business.owner', 'subscription']);
        abort_if(! app(RenewalInvoiceService::class)->canManage($invoice), 422, 'This renewal invoice is closed.');

        $email = $invoice->business?->owner?->email;
        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL), 422, 'No business email is available.');

        $platformName = app(PlatformSettingsService::class)->name();
        $owner = $invoice->business?->owner?->name ?: 'there';
        $subject = $platformName.' Renewal Invoice - '.$invoice->invoice_number;
        $body = "Hello {$owner},\n\nYour {$platformName} access for {$invoice->business?->business_name} is due to expire on {$invoice->access_ends_at->format('n/j/Y')}.\n\nRenewal Invoice: {$invoice->invoice_number}\nCurrent Access End: {$invoice->access_ends_at->format('n/j/Y')}\nProposed Amount: Rs ".number_format((float) $invoice->amount, 2)."\nDue Date: {$invoice->due_date->format('n/j/Y')}\n\nPlease contact {$platformName} to renew your access.\n\nRegards,\n{$platformName}";

        // A mailto link only opens a draft; delivery remains a manual action
        // in the administrator's email application.
        $invoice = app(RenewalInvoiceService::class)->markDraftOpened($invoice, 'email', $request->user()->id);
        $this->audit('Opened renewal invoice email draft for '.$invoice->invoice_number, $request, 'Renewal Invoices', $invoice->id);

        // This is intentionally Gmail Web rather than a mailto link. A
        // mailto redirect can land on a blank browser tab when no desktop
        // email application is registered. http_build_query encodes line
        // breaks, ampersands, currency punctuation, and names safely.
        $redirect = 'https://mail.google.com/mail/?'.http_build_query([
            'view' => 'cm',
            'fs' => '1',
            'to' => $email,
            'su' => $subject,
            'body' => $body,
        ], '', '&', PHP_QUERY_RFC3986);

        return $request->expectsJson()
            ? response()->json(['redirect' => $redirect, 'status' => $invoice->status])
            : redirect()->away($redirect);
    }

    public function openRenewalInvoiceWhatsApp(Request $request, RenewalInvoice $invoice): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $invoice->load('business.owner');
        abort_if(! app(RenewalInvoiceService::class)->canManage($invoice), 422, 'This renewal invoice is closed.');

        $phone = $invoice->business?->phone ?: $invoice->business?->owner?->phone;
        $digits = app(PhoneNumberService::class)->whatsappDigits($phone);
        abort_unless($digits, 422, 'No WhatsApp/phone number is available.');

        $platformName = app(PlatformSettingsService::class)->name();
        $owner = $invoice->business?->owner?->name ?: 'there';
        $message = "Hi {$owner},\n\nYour {$platformName} access for {$invoice->business?->business_name} ends on {$invoice->access_ends_at->format('d M Y')}.\n\nRenewal Invoice: {$invoice->invoice_number}\nProposed Amount: Rs ".number_format((float) $invoice->amount, 2)."\nDue Date: {$invoice->due_date->format('d M Y')}\n\nPlease contact {$platformName} to renew your access.";
        $invoice = app(RenewalInvoiceService::class)->markDraftOpened($invoice, 'whatsapp', $request->user()->id);
        $this->audit('Opened WhatsApp renewal draft for '.$invoice->invoice_number, $request, 'Renewal Invoices', $invoice->id);

        // This records only the click-to-chat action, never a claimed delivery.
        $redirect = 'https://wa.me/'.$digits.'?text='.rawurlencode($message);

        return $request->expectsJson()
            ? response()->json(['redirect' => $redirect, 'status' => $invoice->status])
            : redirect()->away($redirect);
    }

    public function updateRenewalInvoiceAmount(Request $request, RenewalInvoice $invoice): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_if(! app(RenewalInvoiceService::class)->canManage($invoice), 422, 'A closed renewal invoice cannot be changed.');
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1', 'max:999999999.99']]);
        $old = $invoice->amount;
        $invoice->update(['amount' => $data['amount']]);
        $this->audit('Updated negotiated amount for '.$invoice->invoice_number, $request, 'Renewal Invoices', $invoice->id, ['amount' => $old], ['amount' => $invoice->amount]);

        return back()->with('success', 'Renewal invoice amount updated.');
    }

    public function cancelRenewalInvoice(Request $request, RenewalInvoice $invoice): RedirectResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_if(! app(RenewalInvoiceService::class)->canManage($invoice), 422, 'This renewal invoice cannot be cancelled.');
        app(RenewalInvoiceService::class)->cancel($invoice);
        $this->audit('Cancelled renewal invoice '.$invoice->invoice_number, $request, 'Renewal Invoices', $invoice->id);

        return back()->with('success', 'Renewal invoice cancelled.');
    }

    public function notifications()
    {
        return view('super-admin.notifications', ['announcements' => Announcement::latest()->paginate(12), 'businesses' => Business::all()]);
    }

    public function storeAnnouncement(Request $request)
    {
        Announcement::create($request->validate(['title' => ['required'], 'message' => ['required'], 'target_type' => ['required', 'in:All Businesses,Specific Business,Specific Role'], 'business_id' => ['nullable', 'exists:businesses,id'], 'role' => ['nullable']]));
        return back()->with('success', 'Announcement saved.');
    }

    public function auditLogs(Request $request)
    {
        [$filters, $query] = $this->filteredAuditLogQuery($request);
        $metadata = Cache::remember('tradeflow:admin:audit-log-filter-metadata', now()->addMinutes(5), function (): array {
            return [
                'modules' => AuditLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module')->all(),
                'actions' => AuditLog::query()->whereNotNull('action')->distinct()->orderBy('action')->pluck('action')->all(),
                'roles' => AuditLog::query()->whereNotNull('role')->distinct()->orderBy('role')->pluck('role')->all(),
            ];
        });

        return view('super-admin.audit-logs', [
            'logs' => $query?->orderByDesc('created_at')->paginate(10)->withQueryString(),
            'modules' => $metadata['modules'],
            'users' => User::orderBy('name')->get(['id', 'name']),
            'businesses' => Business::orderBy('business_name')->get(['id', 'business_name']),
            'actions' => $metadata['actions'],
            'roles' => $metadata['roles'],
            'filters' => $filters,
            'hasAuditLogPeriod' => $query !== null,
        ]);
    }

    public function liveAuditLogs(Request $request)
    {
        $validated = $request->validate(['after_id' => ['nullable', 'integer', 'min:0']]);
        $afterId = max(0, (int) ($validated['after_id'] ?? 0));
        [, $query] = $this->filteredAuditLogQuery($request);
        if ($query === null) {
            return response()->json(['logs' => [], 'last_id' => $afterId]);
        }
        $logs = $query->where('id', '>', $afterId)->latest('id')->take(50)->get()
            ->sortBy('id')->values()->map(fn (AuditLog $log) => $this->auditLogPayload($log));

        return response()->json(['logs' => $logs, 'last_id' => $logs->last()['id'] ?? $afterId]);
    }

    public function exportAuditLogsCsv(Request $request): StreamedResponse
    {
        [, $query] = $this->filteredAuditLogQuery($request);
        $this->ensureAuditLogExportPeriod($query);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date & Time', 'Company', 'User', 'Role', 'Module', 'Action', 'IP Address']);
            $query->with('business:id,business_name')->chunkById(250, function ($logs) use ($out): void {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $this->auditLogDate($log), $log->business?->business_name ?: 'Platform', $log->user_name ?: $log->user?->name ?: 'System',
                        $log->role ?: $log->actor_role, $log->module, $log->action, AuditIpResolver::display($log->ip_address),
                    ]);
                }
            });
            fclose($out);
        }, 'tradeflow-platform-audit-logs-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportAuditLogsPdf(Request $request)
    {
        [, $query] = $this->filteredAuditLogQuery($request);
        $this->ensureAuditLogExportPeriod($query);
        // Dompdf keeps the complete table layout in memory. A large audit trail
        // can otherwise exceed PHP's memory limit before a response is sent.
        // CSV remains available for complete, unbounded exports.
        $pdfRowLimit = 200;
        $logs = $query->with('business:id,business_name')->orderByDesc('created_at')->limit($pdfRowLimit)->get();

        return Pdf::loadView('super-admin.audit-logs-pdf', compact('logs', 'pdfRowLimit'))->setPaper('a4', 'landscape')
            ->stream('tradeflow-platform-audit-logs-'.now()->format('Ymd-His').'.pdf');
    }

    public function settings()
    {
        $settings = app(PlatformSettingsService::class)->current();
        $storedPhone = trim((string) $settings->support_phone);
        $phoneDigits = preg_replace('/\D+/', '', $storedPhone) ?: '';
        $candidatePhone = $phoneDigits !== '' ? '+'.$phoneDigits : '';
        $supportPhone = $candidatePhone !== '' && app(PhoneNumberService::class)->isValidE164($candidatePhone)
            ? $candidatePhone
            : $storedPhone;

        return view('super-admin.settings', compact('settings', 'supportPhone'));
    }

    public function updateSettings(Request $request)
    {
        $settingsService = app(PlatformSettingsService::class);
        $settings = $settingsService->current();
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'support_email' => ['nullable', 'email'],
            'support_phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
            'default_paid_access_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'renewal_invoice_reminder_days' => ['required', 'integer', 'min:1', 'max:30'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'logo.mimes' => 'Please upload a JPG, JPEG, PNG, or WebP image.',
            'logo.max' => 'Platform logo must not exceed 2MB.',
        ]);

        // The phone component submits one normalized E.164 value. When that
        // value represents the existing support number, retain the stored
        // canonical value exactly as-is instead of reformatting/re-saving it
        // while an unrelated setting (name, email, or logo) is updated.
        $submittedPhoneDigits = preg_replace('/\D+/', '', (string) ($data['support_phone'] ?? '')) ?: '';
        $storedPhoneDigits = preg_replace('/\D+/', '', (string) ($settings->support_phone ?? '')) ?: '';
        if ($submittedPhoneDigits !== '' && $submittedPhoneDigits === $storedPhoneDigits) {
            $data['support_phone'] = $settings->support_phone;
        }

        $oldLogo = $settings->logo;
        $newLogo = null;

        try {
            if ($request->hasFile('logo')) {
                $newLogo = $request->file('logo')->store('platform', 'public');
                $data['logo'] = $newLogo;
            }

            DB::transaction(function () use ($settings, $data): void {
                $settings->update($data);
            });
        } catch (\Throwable $exception) {
            if ($newLogo) {
                Storage::disk('public')->delete($newLogo);
            }

            throw $exception;
        }

        if ($newLogo && $oldLogo && $oldLogo !== $newLogo) {
            $oldLogoPath = preg_replace('#^(?:public/|storage/)#', '', ltrim($oldLogo, '/'));
            Storage::disk('public')->delete($oldLogoPath);
        }

        $settingsService->forget();
        $this->audit('Settings updated', $request);
        return back()->with('success', 'Settings updated.');
    }

    /**
     * Save the optional public landing-page demo. The public page never renders
     * this video unless an administrator has supplied a valid source and enabled it.
     */
    public function updateDemoVideoSettings(Request $request): RedirectResponse
    {
        if ($request->filled('demo_language')) {
            return $this->updateBilingualDemoVideo($request);
        }
        $videoFile = $request->file('demo_video_file');
        if ($videoFile) {
            $extension = strtolower($videoFile->getClientOriginalExtension());
            if (! $videoFile->isValid()
                || ! in_array($extension, ['mp4', 'webm', 'ogv'], true)
                || ! in_array($videoFile->getMimeType(), ['video/mp4', 'video/webm', 'video/ogg', 'application/ogg'], true)) {
                throw ValidationException::withMessages([
                    'demo_video_file' => 'Upload an MP4, WebM, or OGV video file.',
                ]);
            }
        }

        $data = $request->validate([
            'demo_title' => ['nullable', 'string', 'max:120'],
            'demo_subtitle' => ['nullable', 'string', 'max:500'],
            'demo_video_type' => ['required', Rule::in(['external', 'upload'])],
            'demo_video_url' => ['nullable', 'string', 'max:2048'],
            // Size and extension are checked above, before any content probing.
            'demo_video_file' => ['nullable', 'file'],
            'demo_poster_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_demo_poster' => ['nullable', 'boolean'],
            'demo_is_active' => ['nullable', 'boolean'],
        ], [
            'demo_poster_file.mimes' => 'Poster image must be a JPG, JPEG, PNG, or WebP image.',
        ]);

        $settingsService = app(PlatformSettingsService::class);
        $settings = $settingsService->current();
        $oldVideo = $settings->demo_video_url;
        $oldVideoType = $settings->demo_video_type;
        $oldPoster = $settings->demo_poster;
        $newVideo = null;
        $newPoster = null;
        $removePoster = $request->boolean('remove_demo_poster');

        try {
            if ($data['demo_video_type'] === 'external') {
                $videoUrl = $this->validatedDemoVideoUrl($data['demo_video_url'] ?? null);
            } elseif ($request->hasFile('demo_video_file')) {
                $newVideo = $request->file('demo_video_file')->store('platform/demo-videos', 'public');
                $videoUrl = $newVideo;
            } elseif ($oldVideoType === 'upload' && filled($oldVideo)) {
                $videoUrl = $oldVideo;
            } else {
                throw ValidationException::withMessages(['demo_video_file' => 'Upload a demo video before using the uploaded video option.']);
            }

            if ($request->hasFile('demo_poster_file')) {
                $newPoster = $request->file('demo_poster_file')->store('platform/demo-posters', 'public');
            }

            if ($request->boolean('demo_is_active') && ! filled($videoUrl)) {
                throw ValidationException::withMessages(['demo_video_url' => 'Provide a valid demo video before enabling it.']);
            }

            DB::transaction(function () use ($settings, $data, $videoUrl, $newPoster, $removePoster, $request): void {
                $settings->update([
                    'demo_title' => filled($data['demo_title'] ?? null) ? trim($data['demo_title']) : 'See Profit Point in action',
                    'demo_subtitle' => filled($data['demo_subtitle'] ?? null) ? trim($data['demo_subtitle']) : null,
                    'demo_video_type' => $data['demo_video_type'],
                    'demo_video_url' => $videoUrl,
                    'demo_poster' => $newPoster ?: ($removePoster ? null : $settings->demo_poster),
                    'demo_is_active' => $request->boolean('demo_is_active'),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($newVideo) {
                Storage::disk('public')->delete($newVideo);
            }
            if ($newPoster) {
                Storage::disk('public')->delete($newPoster);
            }

            throw $exception;
        }

        if ($newVideo && $oldVideoType === 'upload' && $oldVideo && $oldVideo !== $newVideo) {
            Storage::disk('public')->delete($this->platformSettingStoragePath($oldVideo));
        }
        if (($newPoster || $removePoster) && $oldPoster && $oldPoster !== $newPoster) {
            Storage::disk('public')->delete($this->platformSettingStoragePath($oldPoster));
        }

        $settingsService->forget();
        $this->audit('Public demo video settings updated', $request, 'Settings', $settings->id);

        return back()->with('success', 'Demo video settings updated.');
    }

    public function updateWhatsAppContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_number' => ['nullable', 'string', 'max:25'],
            'whatsapp_message' => ['nullable', 'string', 'max:500'],
            'whatsapp_tooltip' => ['nullable', 'string', 'max:100'],
            'whatsapp_is_active' => ['nullable', 'boolean'],
        ]);

        $digits = null;
        if (filled($data['whatsapp_number'] ?? null)) {
            $digits = app(PhoneNumberService::class)->whatsappDigits($data['whatsapp_number']);
            if (! $digits) {
                throw ValidationException::withMessages(['whatsapp_number' => 'Enter a valid phone number with a country code.']);
            }
        }
        if ($request->boolean('whatsapp_is_active') && ! $digits) {
            throw ValidationException::withMessages(['whatsapp_number' => 'Enter a valid WhatsApp number before enabling it.']);
        }

        $settingsService = app(PlatformSettingsService::class);
        $settings = $settingsService->current();
        $settings->update([
            'whatsapp_number' => $digits,
            'whatsapp_message' => filled($data['whatsapp_message'] ?? null) ? trim($data['whatsapp_message']) : null,
            'whatsapp_tooltip' => filled($data['whatsapp_tooltip'] ?? null) ? trim($data['whatsapp_tooltip']) : null,
            'whatsapp_is_active' => $request->boolean('whatsapp_is_active'),
        ]);
        $settingsService->forget();
        $this->audit('Public WhatsApp contact updated', $request, 'Settings', $settings->id);

        return back()->with('success', 'WhatsApp contact settings updated.');
    }

    /** Update only the public WhatsApp visibility state, without overwriting its saved configuration. */
    public function toggleWhatsAppActive(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['is_active' => ['required', 'boolean']]);

        $settingsService = app(PlatformSettingsService::class);
        $settings = $settingsService->current();
        $isActive = $request->boolean('is_active');
        $number = filled($settings->whatsapp_number) ? '+'.ltrim((string) $settings->whatsapp_number, '+') : null;

        if ($isActive && ! app(PhoneNumberService::class)->isValidE164($number)) {
            throw ValidationException::withMessages([
                'whatsapp_number' => 'Please configure a valid WhatsApp number first.',
            ]);
        }

        $settings->update(['whatsapp_is_active' => $isActive]);
        $settingsService->forget();
        $this->audit('Floating WhatsApp '.($isActive ? 'enabled' : 'disabled'), $request, 'Settings', $settings->id);

        if ($request->expectsJson()) {
            return response()->json([
                'active' => $isActive,
                'message' => $isActive ? 'Floating WhatsApp enabled.' : 'Floating WhatsApp disabled.',
            ]);
        }

        return back()->with('success', $isActive ? 'Floating WhatsApp enabled.' : 'Floating WhatsApp disabled.');
    }

    /** Update one localized landing-demo visibility state without changing its content. */
    public function toggleDemoVideoActive(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'demo_language' => ['required', Rule::in(['en', 'ur'])],
            'is_active' => ['required', 'boolean'],
        ]);

        $prefix = 'demo_'.$data['demo_language'].'_';
        $settingsService = app(PlatformSettingsService::class);
        $settings = $settingsService->current();
        $isActive = $request->boolean('is_active');

        if ($isActive && ! $this->hasUsableDemoVideo(
            $settings->getAttribute($prefix.'video_type'),
            $settings->getAttribute($prefix.'video_url'),
        )) {
            throw ValidationException::withMessages([
                $prefix.'video_url' => 'Demo video is not configured yet.',
            ]);
        }

        $settings->update([$prefix.'is_active' => $isActive]);
        $settingsService->forget();
        $this->audit(strtoupper($data['demo_language']).' landing demo '.($isActive ? 'enabled' : 'disabled'), $request, 'Settings', $settings->id);

        if ($request->expectsJson()) {
            return response()->json([
                'active' => $isActive,
                'message' => $isActive ? 'Demo video enabled.' : 'Demo video disabled.',
            ]);
        }

        return back()->with('success', $isActive ? 'Demo video enabled.' : 'Demo video disabled.');
    }

    public function removeDemoVideo(Request $request): RedirectResponse
    {
        if ($request->filled('demo_language')) {
            return $this->removeBilingualDemoVideo($request);
        }
        $settingsService = app(PlatformSettingsService::class);
        $settings = $settingsService->current();
        $video = $settings->demo_video_url;
        $videoType = $settings->demo_video_type;
        $poster = $settings->demo_poster;

        $settings->update([
            'demo_title' => null, 'demo_subtitle' => null, 'demo_video_type' => null,
            'demo_video_url' => null, 'demo_poster' => null, 'demo_is_active' => false,
        ]);

        if ($videoType === 'upload' && $video) {
            Storage::disk('public')->delete($this->platformSettingStoragePath($video));
        }
        if ($poster) {
            Storage::disk('public')->delete($this->platformSettingStoragePath($poster));
        }

        $settingsService->forget();
        $this->audit('Public demo video removed', $request, 'Settings', $settings->id);

        return back()->with('success', 'Demo video removed from the landing page.');
    }

    private function updateBilingualDemoVideo(Request $request): RedirectResponse
    {
        $locale = $request->string('demo_language')->value();
        abort_unless(in_array($locale, ['en', 'ur'], true), 422);
        $prefix = 'demo_'.$locale.'_';
        $videoField = $prefix.'video_file';
        $posterField = $prefix.'poster_file';
        $videoFile = $request->file($videoField);
        if ($videoFile?->getError() === UPLOAD_ERR_INI_SIZE) {
            throw ValidationException::withMessages([
                $videoField => 'The upload exceeded the server configuration limit. Increase the server upload settings and try again.',
            ]);
        }
        $data = $request->validate([
            'demo_language' => ['required', Rule::in(['en', 'ur'])],
            $prefix.'title' => ['nullable', 'string', 'max:120'],
            $prefix.'subtitle' => ['nullable', 'string', 'max:500'],
            $prefix.'video_type' => ['required', Rule::in(['external', 'upload'])],
            $prefix.'video_url' => ['nullable', 'string', 'max:2048'],
            // Demo video uploads are constrained by the web server/PHP, not by
            // an application-level file-size limit. Type validation remains
            // below so the public landing page only receives supported videos.
            $videoField => ['nullable', 'file'],
            $posterField => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            $prefix.'remove_poster' => ['nullable', 'boolean'],
            $prefix.'is_active' => ['nullable', 'boolean'],
        ]);
        if ($videoFile && (! $videoFile->isValid()
            || ! in_array(strtolower($videoFile->getClientOriginalExtension()), ['mp4', 'webm', 'ogv'], true)
            || ! in_array($videoFile->getMimeType(), ['video/mp4', 'video/webm', 'video/ogg', 'application/ogg'], true))) {
            throw ValidationException::withMessages([$videoField => 'Upload a valid MP4, WebM, or OGV video file.']);
        }

        $settingsService = app(PlatformSettingsService::class);
        $settings = $settingsService->current();
        $oldVideo = $settings->getAttribute($prefix.'video_url');
        $oldVideoType = $settings->getAttribute($prefix.'video_type');
        $oldPoster = $settings->getAttribute($prefix.'poster');
        $newVideo = null;
        $newPoster = null;
        $removePoster = $request->boolean($prefix.'remove_poster');
        try {
            if ($data[$prefix.'video_type'] === 'external') {
                $videoUrl = $this->validatedDemoVideoUrl($data[$prefix.'video_url'] ?? null);
            } elseif ($videoFile) {
                $newVideo = $videoFile->store('platform/demo-videos/'.$locale, 'public');
                $videoUrl = $newVideo;
            } elseif ($oldVideoType === 'upload' && filled($oldVideo)) {
                $videoUrl = $oldVideo;
            } else {
                throw ValidationException::withMessages([$videoField => 'Upload a demo video before using the uploaded video option.']);
            }
            if ($request->hasFile($posterField)) {
                $newPoster = $request->file($posterField)->store('platform/demo-posters/'.$locale, 'public');
            }
            if ($request->boolean($prefix.'is_active') && ! $this->hasUsableDemoVideo($data[$prefix.'video_type'], $videoUrl)) {
                throw ValidationException::withMessages([$prefix.'video_url' => 'Provide a valid demo video before enabling it.']);
            }
            $settings->update([
                $prefix.'title' => filled($data[$prefix.'title'] ?? null) ? trim($data[$prefix.'title']) : ($locale === 'en' ? 'See Profit Point in action' : null),
                $prefix.'subtitle' => filled($data[$prefix.'subtitle'] ?? null) ? trim($data[$prefix.'subtitle']) : null,
                $prefix.'video_type' => $data[$prefix.'video_type'],
                $prefix.'video_url' => $videoUrl,
                $prefix.'poster' => $newPoster ?: ($removePoster ? null : $oldPoster),
                $prefix.'is_active' => $request->boolean($prefix.'is_active'),
            ]);
        } catch (\Throwable $exception) {
            if ($newVideo) Storage::disk('public')->delete($newVideo);
            if ($newPoster) Storage::disk('public')->delete($newPoster);
            throw $exception;
        }
        if ($newVideo && $oldVideoType === 'upload' && $oldVideo && $oldVideo !== $newVideo) Storage::disk('public')->delete($this->platformSettingStoragePath($oldVideo));
        if (($newPoster || $removePoster) && $oldPoster && $oldPoster !== $newPoster) Storage::disk('public')->delete($this->platformSettingStoragePath($oldPoster));
        $settingsService->forget();
        $this->audit(strtoupper($locale).' landing demo updated', $request, 'Settings', $settings->id);
        return back()->with('success', strtoupper($locale).' demo settings updated.');
    }

    private function removeBilingualDemoVideo(Request $request): RedirectResponse
    {
        $locale = $request->string('demo_language')->value();
        abort_unless(in_array($locale, ['en', 'ur'], true), 422);
        $prefix = 'demo_'.$locale.'_';
        $settingsService = app(PlatformSettingsService::class);
        $settings = $settingsService->current();
        $video = $settings->getAttribute($prefix.'video_url');
        $type = $settings->getAttribute($prefix.'video_type');
        $poster = $settings->getAttribute($prefix.'poster');
        $settings->update([$prefix.'title' => null, $prefix.'subtitle' => null, $prefix.'video_type' => null, $prefix.'video_url' => null, $prefix.'poster' => null, $prefix.'is_active' => false]);
        if ($type === 'upload' && $video) Storage::disk('public')->delete($this->platformSettingStoragePath($video));
        if ($poster) Storage::disk('public')->delete($this->platformSettingStoragePath($poster));
        $settingsService->forget();
        $this->audit(strtoupper($locale).' landing demo removed', $request, 'Settings', $settings->id);
        return back()->with('success', strtoupper($locale).' demo removed from the landing page.');
    }

    public function removeWhatsAppContact(Request $request): RedirectResponse
    {
        $settingsService = app(PlatformSettingsService::class);
        $settings = $settingsService->current();
        $settings->update([
            'whatsapp_number' => null, 'whatsapp_message' => null,
            'whatsapp_tooltip' => null, 'whatsapp_is_active' => false,
        ]);

        $settingsService->forget();
        $this->audit('Public WhatsApp contact removed', $request, 'Settings', $settings->id);

        return back()->with('success', 'WhatsApp contact removed from the landing page.');
    }

    /**
     * Use the same persisted-source rules as the landing page before allowing
     * a localized demo to become public.
     */
    private function hasUsableDemoVideo(?string $type, ?string $value): bool
    {
        $value = trim((string) $value);
        if ($type === 'upload') {
            $path = preg_replace('#^(?:public/|storage/)#', '', ltrim($value, '/'));

            return filled($path) && Storage::disk('public')->exists($path);
        }

        if ($type !== 'external') {
            return false;
        }

        $parts = parse_url($value) ?: [];
        $extension = strtolower(pathinfo((string) ($parts['path'] ?? ''), PATHINFO_EXTENSION));

        return filter_var($value, FILTER_VALIDATE_URL)
            && ($parts['scheme'] ?? null) === 'https'
            && filled($parts['host'] ?? null)
            && in_array($extension, ['mp4', 'webm', 'ogv'], true);
    }

    private function validatedDemoVideoUrl(?string $url): string
    {
        $url = trim((string) $url);
        $parts = parse_url($url) ?: [];
        $extension = strtolower(pathinfo((string) ($parts['path'] ?? ''), PATHINFO_EXTENSION));

        if (! filter_var($url, FILTER_VALIDATE_URL)
            || ($parts['scheme'] ?? null) !== 'https'
            || empty($parts['host'])
            || ! in_array($extension, ['mp4', 'webm', 'ogv'], true)) {
            throw ValidationException::withMessages([
                'demo_video_url' => 'Use a direct HTTPS video URL ending in .mp4, .webm, or .ogv.',
            ]);
        }

        return $url;
    }

    private function platformSettingStoragePath(string $path): string
    {
        return preg_replace('#^(?:public/|storage/)#', '', ltrim($path, '/'));
    }

    public function restoreDefaultLogo(Request $request)
    {
        $settingsService = app(PlatformSettingsService::class);
        $settings = $settingsService->current();
        $oldLogo = $settings->logo;
        $defaultLogo = $settingsService->defaultBranding()['logo'];

        try {
            DB::transaction(function () use ($settings, $defaultLogo): void {
                $settings->update(['logo' => $defaultLogo]);
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Unable to restore the default platform logo. Please try again.');
        }

        if ($oldLogo && $oldLogo !== $defaultLogo) {
            $oldLogoPath = preg_replace('#^(?:public/|storage/)#', '', ltrim($oldLogo, '/'));
            Storage::disk('public')->delete($oldLogoPath);
        }

        $settingsService->forget();
        $this->audit('Default platform logo restored', $request, 'Settings', $settings->id, ['logo' => 'custom'], ['logo' => 'default']);

        return back()->with('success', 'Default platform logo restored successfully.');
    }

    public function resetSettingsDefaults(Request $request)
    {
        $settingsService = app(PlatformSettingsService::class);
        $settings = $settingsService->current();
        $defaults = $settingsService->defaultBranding();
        $oldValues = $settings->only(array_keys($defaults));

        try {
            DB::transaction(function () use ($settings, $defaults): void {
                $settings->update($defaults);
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Unable to reset platform settings. Please try again.');
        }

        if ($oldValues['logo'] && $oldValues['logo'] !== $defaults['logo']) {
            $oldLogoPath = preg_replace('#^(?:public/|storage/)#', '', ltrim($oldValues['logo'], '/'));
            Storage::disk('public')->delete($oldLogoPath);
        }
        if (($oldValues['demo_video_type'] ?? null) === 'upload' && ! empty($oldValues['demo_video_url'])) {
            Storage::disk('public')->delete($this->platformSettingStoragePath($oldValues['demo_video_url']));
        }
        if (! empty($oldValues['demo_poster'])) {
            Storage::disk('public')->delete($this->platformSettingStoragePath($oldValues['demo_poster']));
        }
        foreach (['en', 'ur'] as $locale) {
            $prefix = 'demo_'.$locale.'_';
            if (($oldValues[$prefix.'video_type'] ?? null) === 'upload' && ! empty($oldValues[$prefix.'video_url'])) {
                Storage::disk('public')->delete($this->platformSettingStoragePath($oldValues[$prefix.'video_url']));
            }
            if (! empty($oldValues[$prefix.'poster'])) {
                Storage::disk('public')->delete($this->platformSettingStoragePath($oldValues[$prefix.'poster']));
            }
        }

        $settingsService->forget();
        $this->audit('Platform settings reset to defaults', $request, 'Settings', $settings->id, ['fields' => array_keys($oldValues)], ['fields' => array_keys($defaults)]);

        return back()->with('success', 'Platform settings reset to default.');
    }

    public function businessReports(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', Rule::in(['sales_desc', 'sales_asc', 'expenses_desc', 'expenses_asc', 'profit_desc', 'profit_asc'])],
        ]);
        $this->useCurrentBusinessReportDates($request);
        $sales = Order::whereNotIn('status', ['Cancelled', 'Void', 'Returned'])
            ->when($request->filled('business_id'), fn ($builder) => $builder->where('business_id', $request->integer('business_id')));
        $this->applyBusinessReportPeriod($sales, 'order_date', $request);
        $expenses = Expense::query()
            ->when($request->filled('business_id'), fn ($builder) => $builder->where('business_id', $request->integer('business_id')));
        $this->applyBusinessReportPeriod($expenses, 'expense_date', $request);
        $companySummaries = $this->companyPerformanceQuery($request)
            ->paginate(10, ['*'], 'company_page')
            ->withQueryString();
        $purchases = Purchase::query()
            ->when($request->filled('business_id'), fn ($builder) => $builder->where('business_id', $request->integer('business_id')));
        $this->applyBusinessReportPeriod($purchases, 'purchase_date', $request);

        return view('super-admin.business-reports.index', [
            'businesses' => Business::orderBy('business_name')->get(),
            'totalBusinesses' => Business::count(),
            'activeBusinesses' => Business::whereIn('status', ['Approved', 'approved'])->count(),
            'platformSales' => (clone $sales)->sum('grand_total'),
            'monthlyTransactions' => Payment::whereMonth('payment_date', now()->month)->sum('amount'),
            'pendingReports' => BusinessReport::where('status', 'Pending Review')->count(),
            'flaggedBusinesses' => Business::whereIn('status', ['Suspended', 'Rejected'])->count(),
            'highCreditBusinesses' => Business::whereHas('customers', fn($q) => $q->where('current_balance', '>', 100000))->count(),
            'inactiveBusinesses' => Business::whereNotIn('status', ['Approved', 'approved'])->count(),
            'platformSummary' => [
                'sales' => (clone $sales)->sum('grand_total'), 'sales_count' => (clone $sales)->count(),
                'expenses' => (clone $expenses)->sum('amount'), 'purchases' => $purchases->sum('grand_total'),
                'receivables' => (clone $sales)->sum('balance'), 'payables' => Purchase::when($request->filled('business_id'), fn ($builder) => $builder->where('business_id', $request->integer('business_id')))->sum('balance'),
            ],
            'companySummaries' => $companySummaries,
        ]);
    }

    public function businessReportHistory(Request $request)
    {
        $request->validate([
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
            'report_type' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['Pending Review', 'Verified', 'Rejected'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $reports = BusinessReport::query()
            ->with('business')
            ->when($request->filled('business_id'), fn ($query) => $query->where('business_id', $request->integer('business_id')))
            ->when($request->filled('report_type'), fn ($query) => $query->where('report_type', $request->string('report_type')->value()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()));
        $this->applyBusinessReportPeriod($reports, 'created_at', $request);

        return view('super-admin.business-reports.history', [
            'reports' => $reports->latest()->paginate(10)->withQueryString(),
        ]);
    }

    public function businessReportShow(Business $business)
    {
        return view('super-admin.business-reports.show', ['business' => $business->load(['owner','subscription.plan']), 'reports' => $business->reports()->latest()->get()]);
    }

    public function approveReport(Request $request, BusinessReport $report)
    {
        $report->update(['status' => 'Verified', 'approved_by' => auth()->id(), 'approved_at' => now(), 'admin_note' => $request->admin_note]);
        $this->audit('Report approved: #'.$report->id, $request);
        return back()->with('success', 'Report approved.');
    }

    public function rejectReport(Request $request, BusinessReport $report)
    {
        $report->update(['status' => 'Rejected', 'approved_by' => auth()->id(), 'approved_at' => now(), 'admin_note' => $request->admin_note]);
        $this->audit('Report rejected: #'.$report->id, $request);
        return back()->with('success', 'Report rejected.');
    }

    public function reportPdf(BusinessReport $report)
    {
        $pdf = Pdf::loadView('super-admin.business-reports.pdf', [
            'report' => $report->load('business.owner'),
            'generatedAt' => now()->timezone(config('app.timezone')),
        ]);

        return request()->boolean('download')
            ? $pdf->download('business-report-'.$report->id.'.pdf')
            : $pdf->stream('business-report-'.$report->id.'.pdf');
    }

    public function businessReportsExcel(Request $request)
    {
        $this->useCurrentBusinessReportDates($request);
        $summaries = $this->companyPerformanceQuery($request)->get();
        $generatedAt = now()->timezone(config('app.timezone'));
        $filters = $this->businessReportFilterLabels($request);

        $filename = 'tradeflow-business-report-'.$generatedAt->format('Ymd-His').'.xlsx';
        $path = $this->createBusinessReportsWorkbook($summaries, $generatedAt, $filters);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function businessReportsPdf(Request $request)
    {
        $this->useCurrentBusinessReportDates($request);
        $generatedAt = now()->timezone(config('app.timezone'));
        $pdf = Pdf::loadView('super-admin.business-reports.summary-pdf', [
            'companySummaries' => $this->companyPerformanceQuery($request)->get(),
            'filters' => $this->businessReportFilterLabels($request),
            'generatedAt' => $generatedAt,
        ])->setPaper('a4', 'landscape');

        return $request->boolean('download')
            ? $pdf->download('tradeflow-business-report-'.$generatedAt->format('Ymd-His').'.pdf')
            : $pdf->stream('tradeflow-business-report-'.$generatedAt->format('Ymd-His').'.pdf');
    }

    public function editBusinessReport(BusinessReport $report)
    {
        return view('super-admin.business-reports.edit', ['report' => $report->load('business')]);
    }

    public function updateBusinessReport(Request $request, BusinessReport $report): RedirectResponse
    {
        $data = $request->validate([
            'report_type' => ['required', 'string', 'max:100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'status' => ['required', Rule::in(['Pending Review', 'Verified', 'Rejected'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $report->update($data);
        $this->audit('Business report metadata updated: #'.$report->id, $request);

        return redirect()->route('admin.business-reports.history')->with('success', 'Report metadata updated. Financial values remain read-only.');
    }

    private function companyPerformanceQuery(Request $request)
    {
        $period = fn ($builder, string $column) => $this->applyBusinessReportPeriod($builder, $column, $request);
        $sort = $request->input('sort', 'sales_desc');

        return Business::query()
            ->when($request->filled('business_id'), fn ($builder) => $builder->whereKey($request->integer('business_id')))
            ->when($request->filled('search'), fn ($builder) => $builder->where('business_name', 'like', '%'.$request->input('search').'%'))
            ->withSum(['orders as sales_total' => fn ($builder) => $period($builder->whereNotIn('status', ['Cancelled', 'Void', 'Returned']), 'order_date')], 'grand_total')
            ->withSum(['expenses as expense_total' => fn ($builder) => $period($builder, 'expense_date')], 'amount')
            ->when($sort === 'sales_asc', fn ($builder) => $builder->orderBy('sales_total'))
            ->when($sort === 'expenses_desc', fn ($builder) => $builder->orderByDesc('expense_total'))
            ->when($sort === 'expenses_asc', fn ($builder) => $builder->orderBy('expense_total'))
            ->when($sort === 'profit_desc', fn ($builder) => $builder->orderByRaw('(COALESCE(sales_total, 0) - COALESCE(expense_total, 0)) DESC'))
            ->when($sort === 'profit_asc', fn ($builder) => $builder->orderByRaw('(COALESCE(sales_total, 0) - COALESCE(expense_total, 0)) ASC'))
            ->when($sort === 'sales_desc', fn ($builder) => $builder->orderByDesc('sales_total'));
    }

    private function applyBusinessReportPeriod($query, string $column, Request $request)
    {
        if ($request->filled('date_from')) {
            $query->where($column, '>=', Carbon::parse($request->input('date_from'), config('app.timezone'))->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where($column, '<=', Carbon::parse($request->input('date_to'), config('app.timezone'))->endOfDay());
        }

        return $query;
    }

    private function businessReportFilterLabels(Request $request): array
    {
        $business = $request->filled('business_id') ? Business::find($request->integer('business_id')) : null;

        return array_filter([
            'Business: '.($business?->business_name ?? 'All businesses'),
            $request->filled('date_from') ? 'From: '.Carbon::parse($request->input('date_from'), config('app.timezone'))->format('n/j/Y') : null,
            $request->filled('date_to') ? 'To: '.Carbon::parse($request->input('date_to'), config('app.timezone'))->format('n/j/Y') : null,
            $request->filled('report_type') ? 'Report Type: '.$request->input('report_type') : null,
            $request->filled('status') ? 'Status: '.$request->input('status') : null,
        ]);
    }

    /** Set the report period to today when the user has not supplied a date. */
    private function useCurrentBusinessReportDates(Request $request): void
    {
        $today = now()->timezone(config('app.timezone'))->toDateString();

        $request->merge([
            'date_from' => $request->filled('date_from') ? $request->input('date_from') : $today,
            'date_to' => $request->filled('date_to') ? $request->input('date_to') : $today,
        ]);
    }

    /** Create a valid Office Open XML workbook instead of HTML content disguised as .xls. */
    private function createBusinessReportsWorkbook($summaries, Carbon $generatedAt, array $filters): string
    {
        $escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $rows = [
            ['TradeFlow Company-wise Performance Report'],
            ['Generated', $generatedAt->format('n/j/Y, g:i A')],
            ['Filters', implode(' | ', $filters)],
            [],
            ['Business', 'Sales', 'Expenses', 'Estimated Profit'],
        ];

        foreach ($summaries as $business) {
            $sales = (float) ($business->sales_total ?? 0);
            $expenses = (float) ($business->expense_total ?? 0);
            $rows[] = [$business->business_name, $sales, $expenses, $sales - $expenses];
        }

        $sheetRows = [];
        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach ($row as $columnIndex => $value) {
                $coordinate = chr(65 + $columnIndex).($rowIndex + 1);
                $style = $rowIndex === 4 ? ' s="1"' : '';
                $cells[] = is_int($value) || is_float($value)
                    ? '<c r="'.$coordinate.'"'.$style.'><v>'.number_format($value, 2, '.', '').'</v></c>'
                    : '<c r="'.$coordinate.'" t="inlineStr"'.$style.'><is><t>'.$escape($value).'</t></is></c>';
            }
            $sheetRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'tradeflow-report-');
        $path = $temporaryFile.'.xlsx';
        @unlink($temporaryFile);
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create the Excel report.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Company Performance" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="32" customWidth="1"/><col min="2" max="4" width="18" customWidth="1"/></cols><sheetData>'.implode('', $sheetRows).'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }

    private function applyAdminFilters($query, Request $request): void
    {
        $query
            ->when($request->name, fn ($q, $value) => $q->where('name', 'like', "%{$value}%"))
            ->when($request->email, fn ($q, $value) => $q->where('email', 'like', "%{$value}%"))
            ->when($request->status, fn ($q, $value) => $q->where('status', $value))
            ->when($request->created_by, fn ($q, $value) => $q->where('created_by', $value))
            ->when($request->date_from, fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($request->date_to, fn ($q, $value) => $q->whereDate('created_at', '<=', $value));
    }

    private function platformPermissions(): array
    {
        return [
            'admins.view', 'admins.create', 'admins.update', 'admins.suspend', 'admins.permissions',
            'sub_admins.view', 'sub_admins.create', 'sub_admins.update', 'sub_admins.assign_business',
            'businesses.view', 'businesses.create', 'businesses.update', 'businesses.assign', 'businesses.transfer', 'businesses.suspend',
            'subscriptions.view', 'subscriptions.manage',
            'tickets.view', 'tickets.assign', 'tickets.reply', 'tickets.close',
            'notifications.send', 'activity.view', 'activity.export', 'audit.view', 'reports.view', 'reports.export',
        ];
    }

    private function filteredAuditLogQuery(Request $request): array
    {
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'], 'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
            'role' => ['nullable', 'string', 'max:100'], 'module' => ['nullable', 'string', 'max:100'], 'action' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date', 'required_with:date_to'],
            'date_to' => ['nullable', 'date', 'required_with:date_from', 'after_or_equal:date_from'],
            'time_from' => ['nullable', 'date_format:H:i'], 'time_to' => ['nullable', 'date_format:H:i'],
            'month' => ['nullable', 'integer', 'between:1,12', 'required_with:year'],
            'year' => ['nullable', 'integer', 'between:2000,2100', 'required_with:month'],
            'search' => ['nullable', 'string', 'max:255'], 'ip_address' => ['nullable', 'ip'],
        ]);

        $filters = array_replace([
            'date_from' => null, 'date_to' => null, 'time_from' => null, 'time_to' => null,
            'month' => null, 'year' => null,
        ], $filters);
        // The audit workspace opens on a bounded, useful period instead of an
        // empty table while still preventing an unbounded audit-log query.
        if (!filled($filters['date_from']) && !filled($filters['date_to']) && !filled($filters['month']) && !filled($filters['year'])) {
            $today = now()->timezone(config('app.timezone'))->toDateString();
            $filters['date_from'] = $today;
            $filters['date_to'] = $today;
        }
        $hasDateRange = filled($filters['date_from']) && filled($filters['date_to']);
        $hasMonthRange = filled($filters['month']) && filled($filters['year']);

        if ((filled($filters['time_from']) || filled($filters['time_to'])) && !$hasDateRange) {
            throw ValidationException::withMessages(['date_from' => 'Select Date From and Date To before applying a time range.']);
        }

        if (!$hasDateRange && !$hasMonthRange) {
            return [$filters, null];
        }

        [$start, $end] = $this->auditLogPeriod($filters, $hasDateRange);

        $query = AuditLog::query()
            ->select([
                'id', 'user_id', 'user_name', 'role', 'actor_role', 'business_id',
                'module', 'action', 'description', 'route', 'record_type', 'record_id',
                'old_values', 'new_values', 'ip_address', 'user_agent', 'occurred_at', 'created_at',
            ])
            ->with(['user:id,name', 'business:id,business_name'])
            ->when($filters['user_id'] ?? null, fn ($q, $value) => $q->where('user_id', $value))
            ->when($filters['business_id'] ?? null, fn ($q, $value) => $q->where('business_id', $value))
            ->when($filters['role'] ?? null, fn ($q, $value) => $q->where(fn ($inner) => $inner->where('role', $value)->orWhere('actor_role', $value)))
            ->when($filters['module'] ?? null, fn ($q, $value) => $q->where('module', $value))
            ->when($filters['action'] ?? null, fn ($q, $value) => $q->where('action', $value))
            ->whereBetween('created_at', [$start, $end])
            ->when($filters['ip_address'] ?? null, fn ($q, $value) => $q->whereIn('ip_address', AuditIpResolver::searchable($value)))
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('description', 'like', "%{$value}%")
                ->orWhere('route', 'like', "%{$value}%")
                ->orWhere('action', 'like', "%{$value}%")
                ->orWhere('user_name', 'like', "%{$value}%")
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$value}%"))
                ->orWhereHas('business', fn ($business) => $business->where('business_name', 'like', "%{$value}%"))));

        return [$filters, $query];
    }

    private function auditLogPeriod(array $filters, bool $hasDateRange): array
    {
        $timezone = config('app.timezone');

        if ($hasDateRange) {
            $start = Carbon::parse($filters['date_from'], $timezone)->startOfDay();
            $end = Carbon::parse($filters['date_to'], $timezone)->endOfDay();

            if (filled($filters['time_from'])) {
                $start = Carbon::createFromFormat('Y-m-d H:i', $filters['date_from'].' '.$filters['time_from'], $timezone);
            }

            if (filled($filters['time_to'])) {
                $end = Carbon::createFromFormat('Y-m-d H:i', $filters['date_to'].' '.$filters['time_to'], $timezone);
            }
        } else {
            $start = Carbon::create((int) $filters['year'], (int) $filters['month'], 1, 0, 0, 0, $timezone)->startOfMonth();
            $end = $start->copy()->endOfMonth();
        }

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['time_to' => 'Time To must be after Time From.']);
        }

        return [$start, $end];
    }

    private function ensureAuditLogExportPeriod($query): void
    {
        if ($query === null) {
            throw ValidationException::withMessages(['date_from' => 'Select a date range or month and year before exporting audit logs.']);
        }
    }

    private function auditLogPayload(AuditLog $log): array
    {
        return [
            'id' => $log->id, 'occurred_at' => $this->auditLogDate($log), 'company' => $log->business?->business_name ?: 'Platform',
            'user' => $log->user_name ?: $log->user?->name ?: 'System', 'role' => $log->role ?: $log->actor_role ?: 'system',
            'module' => $log->module ?: 'General', 'action' => $log->action, 'description' => $log->description ?: $log->action,
            'ip_address' => AuditIpResolver::display($log->ip_address, '—'), 'route' => $log->route, 'record_type' => $log->record_type, 'record_id' => $log->record_id,
            'old_values' => $log->old_values, 'new_values' => $log->new_values, 'user_agent' => $log->user_agent,
        ];
    }

    private function auditLogDate(AuditLog $log): string
    {
        $date = $log->occurred_at ?? $log->created_at;

        return $date ? Carbon::parse($date)->timezone(config('app.timezone'))->format('n/j/Y, g:i A') : '—';
    }

    private function audit(string $action, Request $request, string $module = 'Admin', ?int $recordId = null, ?array $old = null, ?array $new = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'actor_id' => auth()->id(),
            'actor_role' => auth()->user()?->role,
            'action' => $action,
            'description' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => app(AuditIpResolver::class)->capture($request),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    private function activity(Request $request, string $module, string $action, string $description, $subject = null): void
    {
        ActivityLog::create([
            'actor_id' => auth()->id(),
            'actor_role' => auth()->user()?->role,
            'actor_name_snapshot' => auth()->user()?->name,
            'business_id' => auth()->user()?->business_id,
            'module' => $module,
            'action' => $action,
            'route_name' => $request->route()?->getName(),
            'method' => $request->method(),
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'ip_address' => app(AuditIpResolver::class)->capture($request),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'session_id' => $request->session()->getId(),
            'occurred_at' => now(),
        ]);
    }
}
