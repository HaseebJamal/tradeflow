<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessReport;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
            'ordersCount' => Order::count(),
            'activeSubscriptions' => Subscription::where('status', 'Active')->count(),
            'expiredSubscriptions' => Subscription::where('status', 'Expired')->count(),
            'monthlyRevenue' => $monthlyRevenue,
            'ticketsCount' => SupportTicket::where('status', 'Open')->count(),
            'businesses' => Business::with('owner')->latest()->take(8)->get(),
        ]);
    }

    public function businesses()
    {
        return view('super-admin.businesses', ['businesses' => Business::with(['owner', 'documents', 'subscription.plan'])->latest()->paginate(15)]);
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
        $user->update($data);
        $this->audit('User '.$data['status'].': '.$user->email, $request);
        return back()->with('success', 'User status updated.');
    }

    public function subscriptions()
    {
        return view('super-admin.subscriptions', [
            'plans' => SubscriptionPlan::all(),
            'businesses' => Business::whereIn('status', ['Approved', 'approved'])->get(),
            'subscriptions' => Subscription::with(['business', 'plan'])->latest()->paginate(20),
        ]);
    }

    public function storePlan(Request $request)
    {
        SubscriptionPlan::updateOrCreate(['id' => $request->id], $request->validate([
            'name' => ['required', 'max:100'], 'price' => ['required', 'numeric', 'min:0'], 'product_limit' => ['required', 'integer', 'min:0'], 'staff_limit' => ['required', 'integer', 'min:0'], 'order_limit' => ['required', 'integer', 'min:0'], 'status' => ['required', 'in:Active,Inactive'],
        ]));
        return back()->with('success', 'Plan saved.');
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

        Subscription::updateOrCreate(['business_id' => $data['business_id']], $data);
        $this->audit('Subscription updated for business #'.$data['business_id'], $request);

        return back()->with('success', 'Subscription updated.');
    }

    public function supportTickets()
    {
        return view('super-admin.support-tickets', ['tickets' => SupportTicket::with(['business', 'user'])->latest()->paginate(20)]);
    }

    public function updateTicket(Request $request, SupportTicket $ticket)
    {
        $ticket->update($request->validate(['admin_reply' => ['nullable'], 'status' => ['required', 'in:Open,Pending,Closed']]));
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

    public function auditLogs()
    {
        return view('super-admin.audit-logs', ['logs' => AuditLog::with('user')->latest()->paginate(30)]);
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
        return Pdf::loadView('super-admin.business-reports.pdf', ['report' => $report->load('business.owner')])->download('business-report-'.$report->id.'.pdf');
    }

    private function audit(string $action, Request $request): void
    {
        AuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'ip_address' => $request->ip()]);
    }
}
