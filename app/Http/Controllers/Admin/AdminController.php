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
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard()
    {
        $monthlyRevenue = Schema::hasColumn('subscriptions', 'amount')
            ? Subscription::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount')
            : Subscription::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->join('subscription_plans', 'subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
                ->sum('subscription_plans.price');

        return view('super-admin.dashboard', [
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
        ]);
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

    public function users()
    {
        $query = User::with('business');
        if ($request = request('search')) $query->where(fn($q) => $q->where('name','like',"%$request%")->orWhere('email','like',"%$request%"));
        if (request('role')) $query->where('role', request('role'));
        if (request('status')) $query->where('status', request('status'));
        return view('super-admin.users', ['users' => $query->latest()->paginate(20)->withQueryString()]);
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

        return view('super-admin.subscriptions', [
            'plans' => SubscriptionPlan::withCount('subscriptions')->orderBy('price')->get(),
            'businesses' => Business::whereIn('status', ['Approved', 'approved'])->orderBy('business_name')->get(),
            'subscriptions' => $subscriptions,
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
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'exists:users,id'],
            'business_id' => ['nullable', 'exists:businesses,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $logs = AuditLog::with('user')
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->where(fn ($nested) => $nested
                ->where('action', 'like', "%{$value}%")
                ->orWhere('description', 'like', "%{$value}%")))
            ->when($filters['module'] ?? null, fn ($query, $value) => $query->where('module', $value))
            ->when($filters['user_id'] ?? null, fn ($query, $value) => $query->where('user_id', $value))
            ->when($filters['business_id'] ?? null, fn ($query, $value) => $query->where('business_id', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('super-admin.audit-logs', [
            'logs' => $logs,
            'modules' => AuditLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'businesses' => Business::orderBy('business_name')->get(['id', 'business_name']),
        ]);
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

    public function businessReports()
    {
        $query = BusinessReport::with('business');
        foreach (['business_id', 'report_type', 'month', 'year', 'status'] as $filter) {
            if (request($filter)) $query->where($filter, request($filter));
        }
        return view('super-admin.business-reports.index', [
            'reports' => $query->latest()->paginate(20)->withQueryString(),
            'businesses' => Business::all(),
            'totalBusinesses' => Business::count(),
            'activeBusinesses' => Business::whereIn('status', ['Approved', 'approved'])->count(),
            'platformSales' => Order::sum('grand_total'),
            'monthlyTransactions' => Payment::whereMonth('payment_date', now()->month)->sum('amount'),
            'pendingReports' => BusinessReport::where('status', 'Pending Review')->count(),
            'flaggedBusinesses' => Business::whereIn('status', ['Suspended', 'Rejected'])->count(),
            'highCreditBusinesses' => Business::whereHas('customers', fn($q) => $q->where('current_balance', '>', 100000))->count(),
            'inactiveBusinesses' => Business::whereNotIn('status', ['Approved', 'approved'])->count(),
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
