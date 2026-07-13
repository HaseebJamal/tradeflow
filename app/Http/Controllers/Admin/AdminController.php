<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessReport;
use App\Models\BusinessUserAssignment;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Supplier;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('super-admin.dashboard', $this->platformDashboardData());
    }

    private function platformDashboardData(): array
    {
        $monthlyRevenue = Schema::hasColumn('subscriptions', 'amount')
            ? Subscription::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount')
            : Subscription::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)
                ->join('subscription_plans', 'subscriptions.subscription_plan_id', '=', 'subscription_plans.id')->sum('subscription_plans.price');

        return [
            'totalBusinesses' => Business::count(),
            'pendingApprovals' => Business::whereIn('status', ['Pending', 'pending'])->count(),
            'activeBusinesses' => Business::whereIn('status', ['Approved', 'approved'])->count(),
            'rejectedBusinesses' => Business::whereIn('status', ['Rejected', 'rejected'])->count(),
            'suspendedBusinesses' => Business::whereIn('status', ['Suspended', 'suspended'])->count(),
            'totalUsers' => User::count(),
            'activeSubscriptions' => Subscription::where('status', 'Active')->count(),
            'expiredSubscriptions' => Subscription::where('status', 'Expired')->count(),
            'monthlyRevenue' => $monthlyRevenue,
            'ticketsCount' => SupportTicket::where('status', 'Open')->count(),
            'securityAlerts' => ActivityLog::where('module', 'Security')->whereDate('occurred_at', '>=', today()->subDays(7))->count(),
        ];
    }

    public function platformAdmins(Request $request)
    {
        $query = User::withCount(['children', 'businessAssignments'])
            ->with('creator')
            ->where('role', 'platform_admin');

        $this->applyAdminFilters($query, $request);

        return view('super-admin.administration.platform-admins', [
            'admins' => $query->latest()->paginate(20)->withQueryString(),
            'permissions' => $this->platformPermissions(),
        ]);
    }

    public function platformSubAdmins(Request $request)
    {
        $query = User::with(['parent', 'creator'])->withCount('businessAssignments')->where('role', 'platform_sub_admin');
        $this->applyAdminFilters($query, $request);

        return view('super-admin.administration.platform-sub-admins', [
            'subAdmins' => $query->latest()->paginate(20)->withQueryString(),
            'platformAdmins' => User::where('role', 'platform_admin')->where('status', 'active')->orderBy('name')->get(),
            'permissions' => $this->platformPermissions(),
        ]);
    }

    public function storePlatformUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(['platform_admin', 'platform_sub_admin'])],
            'parent_user_id' => ['nullable', 'exists:users,id'],
            'password' => ['required', 'confirmed', 'min:8'],
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
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'parent_user_id' => ['nullable', 'exists:users,id'],
            'permissions' => ['nullable', 'array'],
            'password' => ['nullable', 'confirmed', 'min:8'],
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
            'assignments' => BusinessUserAssignment::with(['business.owner', 'user', 'assigner'])->where('status', 'Active')->latest()->paginate(25),
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
            'activities' => ActivityLog::with(['actor', 'business'])->whereIn('actor_role', ['super_admin', 'platform_admin', 'platform_sub_admin'])->latest('occurred_at')->paginate(30)->withQueryString(),
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
            'activities' => $query->latest('occurred_at')->paginate(40)->withQueryString(),
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
                ->paginate(15),
        ]);
    }

    public function businessShow(Business $business)
    {
        return view('super-admin.business-show', ['business' => $business->load(['owner', 'documents', 'subscription.plan'])]);
    }

    public function updateStatus(Request $request, Business $business)
    {
        $data = $request->validate(['status' => ['required', 'in:pending,approved,rejected,suspended']]);
        $oldStatus = $business->status;
        $business->update(['status' => $data['status']]);
        $this->audit('Changed business status from '.$oldStatus.' to '.$data['status'].' for '.$business->business_name, $request);

        return back()->with('success', 'Business status updated.');
    }

    public function updateBusinessStatus(Request $request, Business $business)
    {
        return $this->updateStatus($request, $business);
    }

    public function users(Request $request)
    {
        $query = User::with('business');
        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        $query
            ->when($request->filled('role'), fn ($builder) => $builder->where('role', $request->input('role')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->input('status')))
            ->when($request->filled('business_id'), fn ($builder) => $builder->where('business_id', $request->integer('business_id')));

        return view('super-admin.users', [
            'users' => $query->latest()->paginate(20)->withQueryString(),
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

        $filters = $request->validate([
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'status' => ['nullable', 'in:Active,Expired,Cancelled'],
            'payment_method' => ['nullable', 'in:Cash,Bank Transfer,JazzCash Manual,Easypaisa Manual'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $subscriptions = Subscription::with(['business', 'plan'])
            ->when($filters['business_id'] ?? null, fn ($query, $value) => $query->where('business_id', $value))
            ->when($filters['subscription_plan_id'] ?? null, fn ($query, $value) => $query->where('subscription_plan_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['payment_method'] ?? null, fn ($query, $value) => $query->where('payment_method', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        $selectedBusinessId = $filters['business_id'] ?? null;
        $assignableBusinesses = Business::query()
            ->where(function ($query) use ($selectedBusinessId) {
                $query->whereIn('status', ['Approved', 'approved']);

                // A Super Admin may open subscription management from any
                // company row. Keep that target available even if its current
                // approval state is not Approved.
                if ($selectedBusinessId) {
                    $query->orWhere('id', $selectedBusinessId);
                }
            })
            ->orderBy('business_name')
            ->get();

        return view('super-admin.subscriptions', [
            'plans' => SubscriptionPlan::withCount('subscriptions')->orderBy('price')->get(),
            'businesses' => $assignableBusinesses,
            'subscriptions' => $subscriptions,
            'selectedBusinessId' => $selectedBusinessId,
            'stats' => [
                'active' => Subscription::where('status', 'Active')->count(),
                'expired' => Subscription::where('status', 'Expired')->count(),
                'cancelled' => Subscription::where('status', 'Cancelled')->count(),
                'monthly_revenue' => Subscription::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount'),
            ],
        ]);
    }

    public function storePlan(Request $request)
    {
        $plan = SubscriptionPlan::create($request->validate([
            'name' => ['required', 'max:100'], 'price' => ['required', 'numeric', 'min:0'], 'product_limit' => ['required', 'integer', 'min:0'], 'staff_limit' => ['required', 'integer', 'min:0'], 'order_limit' => ['required', 'integer', 'min:0'], 'status' => ['required', 'in:Active,Inactive'],
        ]));
        $this->audit('Subscription plan created: '.$plan->name, $request, 'Subscriptions', $plan->id, null, $plan->only(['name', 'price', 'product_limit', 'staff_limit', 'order_limit', 'status']));

        return back()->with('success', 'Subscription plan created.');
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $data = $request->validate([
            'name' => ['required', 'max:100'], 'price' => ['required', 'numeric', 'min:0'], 'product_limit' => ['required', 'integer', 'min:0'], 'staff_limit' => ['required', 'integer', 'min:0'], 'order_limit' => ['required', 'integer', 'min:0'], 'status' => ['required', 'in:Active,Inactive'],
        ]);
        $old = $plan->only(array_keys($data));
        $plan->update($data);
        $this->audit('Subscription plan updated: '.$plan->name, $request, 'Subscriptions', $plan->id, $old, $plan->fresh()->only(array_keys($data)));

        return back()->with('success', 'Subscription plan updated.');
    }

    public function destroyPlan(Request $request, SubscriptionPlan $plan)
    {
        if ($plan->subscriptions()->exists()) {
            $old = $plan->status;
            $plan->update(['status' => 'Inactive']);
            $this->audit('Subscription plan deactivated because it has subscription history: '.$plan->name, $request, 'Subscriptions', $plan->id, ['status' => $old], ['status' => 'Inactive']);

            return back()->with('success', 'Plan has subscription history, so it was deactivated instead of deleted.');
        }

        $old = $plan->only(['name', 'price', 'product_limit', 'staff_limit', 'order_limit', 'status']);
        $plan->delete();
        $this->audit('Subscription plan deleted: '.$old['name'], $request, 'Subscriptions', $plan->id, $old);

        return back()->with('success', 'Subscription plan deleted.');
    }

    public function activateSubscription(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'exists:businesses,id'],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:Cash,Bank Transfer,JazzCash Manual,Easypaisa Manual'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:Active,Expired,Cancelled'],
        ]);

        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
        $data['amount'] = $data['amount'] ?? $plan->price;
        $data['starts_at'] = $data['starts_at'] ?? now()->toDateString();
        $data['ends_at'] = $data['ends_at'] ?? now()->addMonth()->toDateString();
        $data['status'] = $this->resolvedSubscriptionStatus($data['status'], $data['ends_at']);

        $subscription = Subscription::updateOrCreate(['business_id' => $data['business_id']], $data);
        $this->audit('Subscription created or assigned for business #'.$data['business_id'], $request, 'Subscriptions', $subscription->id, null, $subscription->only(['subscription_plan_id', 'amount', 'payment_method', 'starts_at', 'ends_at', 'status']));

        return back()->with('success', 'Subscription updated.');
    }

    public function updateSubscription(Request $request, Subscription $subscription)
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:Cash,Bank Transfer,JazzCash Manual,Easypaisa Manual'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', 'in:Active,Expired,Cancelled'],
        ]);

        $data['amount'] = $data['amount'] ?? $subscription->plan?->price ?? 0;
        $data['starts_at'] = $data['starts_at'] ?? $subscription->starts_at?->toDateString() ?? now()->toDateString();
        $data['ends_at'] = $data['ends_at'] ?? $subscription->ends_at?->toDateString() ?? now()->addMonth()->toDateString();
        $data['status'] = $this->resolvedSubscriptionStatus($data['status'], $data['ends_at']);
        $old = $subscription->only(['subscription_plan_id', 'amount', 'payment_method', 'starts_at', 'ends_at', 'status']);
        $subscription->update($data);
        $this->audit('Subscription updated for business #'.$subscription->business_id, $request, 'Subscriptions', $subscription->id, $old, $subscription->fresh()->only(array_keys($old)));

        return back()->with('success', 'Subscription updated.');
    }

    public function cancelSubscription(Request $request, Subscription $subscription)
    {
        if ($subscription->status === 'Cancelled') {
            return back()->with('success', 'This subscription is already cancelled.');
        }

        $old = $subscription->only(['status', 'ends_at']);
        $subscription->update(['status' => 'Cancelled', 'ends_at' => $subscription->ends_at ?? now()->toDateString()]);
        $this->audit('Subscription cancelled for business #'.$subscription->business_id, $request, 'Subscriptions', $subscription->id, $old, $subscription->fresh()->only(array_keys($old)));

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

    private function expireDueSubscriptions(Request $request): void
    {
        Subscription::where('status', 'Active')
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', '<', now()->toDateString())
            ->each(function (Subscription $subscription) use ($request): void {
                $old = ['status' => $subscription->status, 'ends_at' => $subscription->ends_at?->toDateString()];
                $subscription->update(['status' => 'Expired']);
                $this->audit('Subscription expired for business #'.$subscription->business_id, $request, 'Subscriptions', $subscription->id, $old, ['status' => 'Expired', 'ends_at' => $old['ends_at']]);
            });
    }

    private function resolvedSubscriptionStatus(string $requestedStatus, ?string $endsAt): string
    {
        return $requestedStatus === 'Active' && $endsAt && now()->startOfDay()->gt(\Carbon\Carbon::parse($endsAt)->startOfDay())
            ? 'Expired'
            : $requestedStatus;
    }

    public function supportTickets()
    {
        return view('super-admin.support-tickets', [
            'tickets' => SupportTicket::with(['business', 'user', 'assignedAdmin', 'assignedSubAdmin', 'messages.sender'])->latest()->paginate(20),
        ]);
    }

    public function updateTicket(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'message' => ['nullable', 'string'],
            'admin_reply' => ['nullable'],
            'status' => ['required', 'in:Open,Assigned,In Progress,Waiting for User,Escalated,Resolved,Closed,Reopened,Pending'],
            'priority' => ['nullable', 'in:Low,Medium,High,Urgent'],
            'resolution' => ['nullable', 'string'],
            'internal_note' => ['nullable', 'boolean'],
        ]);

        if (!$ticket->ticket_number) {
            $ticket->ticket_number = 'TF-TKT-'.now()->format('Ymd').'-'.str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT);
        }

        $old = $ticket->only(['status', 'priority', 'assigned_admin_id', 'assigned_sub_admin_id']);
        $ticket->fill([
            'admin_reply' => $data['admin_reply'] ?? $ticket->admin_reply,
            'status' => $data['status'],
            'priority' => $data['priority'] ?? $ticket->priority,
            'assigned_admin_id' => $ticket->assigned_admin_id ?? auth()->id(),
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

    public function categories()
    {
        return view('super-admin.categories', ['categories' => Category::whereNull('business_id')->latest()->paginate(20)]);
    }

    public function storeCategory(Request $request)
    {
        Category::updateOrCreate(['id' => $request->id], $request->validate(['name' => ['required', 'max:100'], 'status' => ['required', 'in:Active,Inactive']]) + ['type' => 'Product', 'business_id' => null]);
        return back()->with('success', 'Category saved.');
    }

    public function payments()
    {
        return view('super-admin.payments', ['payments' => Payment::with(['customer.business', 'order'])->latest()->paginate(25)]);
    }

    public function notifications()
    {
        return view('super-admin.notifications', ['announcements' => Announcement::latest()->paginate(20), 'businesses' => Business::all()]);
    }

    public function storeAnnouncement(Request $request)
    {
        Announcement::create($request->validate(['title' => ['required'], 'message' => ['required'], 'target_type' => ['required', 'in:All Businesses,Specific Business,Specific Role'], 'business_id' => ['nullable', 'exists:businesses,id'], 'role' => ['nullable']]));
        return back()->with('success', 'Announcement saved.');
    }

    public function auditLogs(Request $request)
    {
        [$filters, $query] = $this->filteredAuditLogQuery($request);

        return view('super-admin.audit-logs', [
            'logs' => $query->latest('occurred_at')->paginate(30)->withQueryString(),
            'modules' => AuditLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'businesses' => Business::orderBy('business_name')->get(['id', 'business_name']),
            'actions' => AuditLog::query()->whereNotNull('action')->distinct()->orderBy('action')->pluck('action'),
            'roles' => AuditLog::query()->whereNotNull('role')->distinct()->orderBy('role')->pluck('role'),
            'filters' => $filters,
        ]);
    }

    public function liveAuditLogs(Request $request)
    {
        $validated = $request->validate(['after_id' => ['nullable', 'integer', 'min:0']]);
        $afterId = max(0, (int) ($validated['after_id'] ?? 0));
        [, $query] = $this->filteredAuditLogQuery($request);
        $logs = $query->where('id', '>', $afterId)->latest('id')->take(50)->get()
            ->sortBy('id')->values()->map(fn (AuditLog $log) => $this->auditLogPayload($log));

        return response()->json(['logs' => $logs, 'last_id' => $logs->last()['id'] ?? $afterId]);
    }

    public function exportAuditLogsCsv(Request $request): StreamedResponse
    {
        [, $query] = $this->filteredAuditLogQuery($request);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date & Time', 'Company', 'User', 'Role', 'Module', 'Action', 'Description', 'Route', 'IP Address', 'Record']);
            $query->latest('occurred_at')->chunkById(250, function ($logs) use ($out): void {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $this->auditLogDate($log), $log->business?->business_name ?: 'Platform', $log->user_name ?: $log->user?->name ?: 'System',
                        $log->role ?: $log->actor_role, $log->module, $log->action, $log->description, $log->route, $log->ip_address,
                        trim(($log->record_type ?: '').' #'.($log->record_id ?: '')),
                    ]);
                }
            });
            fclose($out);
        }, 'tradeflow-platform-audit-logs-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportAuditLogsPdf(Request $request)
    {
        [, $query] = $this->filteredAuditLogQuery($request);
        // Dompdf keeps the complete table layout in memory. A large audit trail
        // can otherwise exceed PHP's memory limit before a response is sent.
        // CSV remains available for complete, unbounded exports.
        $pdfRowLimit = 200;
        $logs = $query->latest('occurred_at')->limit($pdfRowLimit)->get();

        return Pdf::loadView('super-admin.audit-logs-pdf', compact('logs', 'pdfRowLimit'))->setPaper('a4', 'landscape')
            ->stream('tradeflow-platform-audit-logs-'.now()->format('Ymd-His').'.pdf');
    }

    public function settings()
    {
        return view('super-admin.settings', ['settings' => PlatformSetting::firstOrCreate([]), 'plans' => SubscriptionPlan::all()]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate(['company_name' => ['required'], 'support_email' => ['nullable','email'], 'support_phone' => ['nullable'], 'trial_days' => ['required','integer','min:0'], 'default_plan_id' => ['nullable','exists:subscription_plans,id'], 'max_upload_size' => ['required','integer','min:1'], 'logo' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048']]);
        if ($request->hasFile('logo')) $data['logo'] = $request->file('logo')->store('platform', 'public');
        PlatformSetting::firstOrCreate([])->update($data);
        $this->audit('Settings updated', $request);
        return back()->with('success', 'Settings updated.');
    }

    public function businessReports(Request $request)
    {
        $query = BusinessReport::with('business');
        foreach (['business_id', 'report_type', 'month', 'year', 'status'] as $filter) {
            if ($request->filled($filter)) $query->where($filter, $request->input($filter));
        }
        $sales = Order::whereNotIn('status', ['Cancelled', 'Void', 'Returned'])
            ->when($request->filled('business_id'), fn ($builder) => $builder->where('business_id', $request->integer('business_id')))
            ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('order_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('order_date', '<=', $request->date_to))
            ->when($request->filled('month'), fn ($builder) => $builder->whereMonth('order_date', $request->integer('month')))
            ->when($request->filled('year'), fn ($builder) => $builder->whereYear('order_date', $request->integer('year')));
        $expenses = Expense::query()
            ->when($request->filled('business_id'), fn ($builder) => $builder->where('business_id', $request->integer('business_id')))
            ->when($request->filled('date_from'), fn ($builder) => $builder->whereDate('expense_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($builder) => $builder->whereDate('expense_date', '<=', $request->date_to))
            ->when($request->filled('month'), fn ($builder) => $builder->whereMonth('expense_date', $request->integer('month')))
            ->when($request->filled('year'), fn ($builder) => $builder->whereYear('expense_date', $request->integer('year')));
        $period = fn ($builder, string $column) => $builder
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate($column, '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate($column, '<=', $request->date_to))
            ->when($request->filled('month'), fn ($q) => $q->whereMonth($column, $request->integer('month')))
            ->when($request->filled('year'), fn ($q) => $q->whereYear($column, $request->integer('year')));
        $companySummaries = Business::when($request->filled('business_id'), fn ($builder) => $builder->whereKey($request->integer('business_id')))
            ->withSum(['orders as sales_total' => fn ($builder) => $period($builder->whereNotIn('status', ['Cancelled', 'Void', 'Returned']), 'order_date')], 'grand_total')
            ->withSum(['expenses as expense_total' => fn ($builder) => $period($builder, 'expense_date')], 'amount')
            ->orderByDesc('sales_total')->get();
        $purchases = Purchase::query()
            ->when($request->filled('business_id'), fn ($builder) => $builder->where('business_id', $request->integer('business_id')));
        $period($purchases, 'purchase_date');

        return view('super-admin.business-reports.index', [
            'reports' => $query->latest()->paginate(20)->withQueryString(),
            'businesses' => Business::all(),
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
        return Pdf::loadView('super-admin.business-reports.pdf', ['report' => $report->load('business.owner')])
            ->stream('business-report-'.$report->id.'.pdf');
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
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'], 'ip_address' => ['nullable', 'ip'],
        ]);

        $query = AuditLog::with(['user', 'business'])
            ->when($filters['user_id'] ?? null, fn ($q, $value) => $q->where('user_id', $value))
            ->when($filters['business_id'] ?? null, fn ($q, $value) => $q->where('business_id', $value))
            ->when($filters['role'] ?? null, fn ($q, $value) => $q->where(fn ($inner) => $inner->where('role', $value)->orWhere('actor_role', $value)))
            ->when($filters['module'] ?? null, fn ($q, $value) => $q->where('module', $value))
            ->when($filters['action'] ?? null, fn ($q, $value) => $q->where('action', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('occurred_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('occurred_at', '<=', $value))
            ->when($filters['ip_address'] ?? null, fn ($q, $value) => $q->where('ip_address', $value))
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('description', 'like', "%{$value}%")
                ->orWhere('route', 'like', "%{$value}%")
                ->orWhere('action', 'like', "%{$value}%")));

        return [$filters, $query];
    }

    private function auditLogPayload(AuditLog $log): array
    {
        return [
            'id' => $log->id, 'occurred_at' => $this->auditLogDate($log), 'company' => $log->business?->business_name ?: 'Platform',
            'user' => $log->user_name ?: $log->user?->name ?: 'System', 'role' => $log->role ?: $log->actor_role ?: 'system',
            'module' => $log->module ?: 'General', 'action' => $log->action, 'description' => $log->description ?: $log->action,
            'ip_address' => $log->ip_address ?: '—', 'route' => $log->route, 'record_type' => $log->record_type, 'record_id' => $log->record_id,
            'old_values' => $log->old_values, 'new_values' => $log->new_values, 'user_agent' => $log->user_agent,
        ];
    }

    private function auditLogDate(AuditLog $log): string
    {
        $date = $log->occurred_at ?? $log->created_at;

        return $date ? Carbon::parse($date)->timezone(config('app.timezone'))->format('d M, Y h:i A') : '—';
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
            'ip_address' => $request->ip(),
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
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'session_id' => $request->session()->getId(),
            'occurred_at' => now(),
        ]);
    }
}
