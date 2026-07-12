<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyRequest;
use App\Models\ActivityLog;
use App\Models\Business;
use App\Models\BusinessDocument;
use App\Models\CompanyApprovalLog;
use App\Models\CompanyPermission;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\PermissionDefinition;
use App\Models\PermissionTemplate;
use App\Notifications\CompanyRegistrationNotification;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CompanyController extends Controller
{
    public function index(Request $request, ?string $status = null)
    {
        $query = Business::with(['owner', 'subscription.plan', 'assignments.user'])
            ->withCount(['users', 'customers', 'orders']);

        $status ??= $request->string('status')->lower()->value();
        if ($status) {
            $query->whereRaw('LOWER(status) = ?', [strtolower($status)]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('business_name', 'like', "%{$search}%")
                ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")));
        }

        return view('super-admin.companies.index', [
            'companies' => $query->latest()->paginate(20)->withQueryString(),
            'statusFilter' => $status,
        ]);
    }

    public function create()
    {
        return view('super-admin.companies.create', ['templates' => PermissionTemplate::where('status', 'active')->orderBy('name')->get()]);
    }

    public function approvalHistory(Request $request)
    {
        $filters = $request->validate([
            'company_id' => ['nullable', 'exists:businesses,id'], 'owner_id' => ['nullable', 'exists:users,id'],
            'old_status' => ['nullable', 'string', 'max:40'], 'new_status' => ['nullable', 'string', 'max:40'],
            'changed_by' => ['nullable', 'exists:users,id'], 'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'search' => ['nullable', 'string', 'max:255'],
        ]);
        $query = CompanyApprovalLog::with(['company.owner', 'changedBy'])
            ->when($filters['company_id'] ?? null, fn ($q, $value) => $q->where('company_id', $value))
            ->when($filters['owner_id'] ?? null, fn ($q, $value) => $q->whereHas('company', fn ($company) => $company->where('owner_id', $value)))
            ->when($filters['old_status'] ?? null, fn ($q, $value) => $q->whereRaw('LOWER(old_status) = ?', [strtolower($value)]))
            ->when($filters['new_status'] ?? null, fn ($q, $value) => $q->whereRaw('LOWER(new_status) = ?', [strtolower($value)]))
            ->when($filters['changed_by'] ?? null, fn ($q, $value) => $q->where('changed_by', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('changed_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('changed_at', '<=', $value))
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where('note', 'like', "%{$value}%"));

        return view('super-admin.approvals.history', [
            'histories' => $query->latest('changed_at')->paginate(20)->withQueryString(),
            'companies' => Business::with('owner')->orderBy('business_name')->get(),
            'owners' => User::where('role', 'business_owner')->orderBy('name')->get(),
            'admins' => User::whereIn('role', ['super_admin', 'platform_admin', 'platform_sub_admin'])->orderBy('name')->get(),
        ]);
    }

    public function approvalHistoryShow(CompanyApprovalLog $approvalLog)
    {
        return view('super-admin.approvals.show', ['log' => $approvalLog->load(['company.owner', 'changedBy'])]);
    }

    public function store(StoreCompanyRequest $request)
    {
        $data = $request->validated();

        $company = DB::transaction(function () use ($request, $data) {
            $owner = User::create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'],
                'password' => Hash::make($data['temporary_password']),
                'role' => 'business_owner',
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            $company = Business::create([
                'owner_id' => $owner->id,
                'created_by' => auth()->id(),
                'business_name' => $data['business_name'],
                'business_type' => $data['business_type'],
                'category' => $data['category'] ?? null,
                'phone' => $data['company_phone'],
                'address' => $data['address'],
                'city' => $data['city'],
                'registration_number' => $data['registration_number'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'status' => $data['initial_status'],
            ]);

            $owner->update(['business_id' => $company->id]);

            if ($request->hasFile('company_logo')) {
                $company->update(['logo' => $request->file('company_logo')->store('business-logos', 'public')]);
            }
            if ($request->hasFile('business_document')) {
                BusinessDocument::create(['business_id' => $company->id, 'document_type' => 'business_document', 'file_path' => $request->file('business_document')->store('business-documents', 'public')]);
            }

            app(AccountingService::class)->ensureDefaultAccounts($company->id);
            $this->applyInitialTemplate($company, $data['permission_template_id'] ?? null);
            $this->recordApprovalLog($company, null, $data['initial_status'], 'Company created by Super Admin');

            User::where('role', 'super_admin')->where('status', 'active')->get()
                ->each(fn (User $admin) => $admin->notify(new CompanyRegistrationNotification($company)));

            return $company;
        });

        $this->audit($request, 'company created', $company, null, $company->only(['business_name', 'status']));

        return redirect()->route('admin.companies.show', $company)
            ->with('success', 'Company created successfully.')
            ->with('clear_company_draft', true);
    }

    public function show(Request $request, Business $company)
    {
        $this->audit($request, 'company viewed', $company, null, null);

        return view('super-admin.companies.show', [
            'company' => $company->load(['owner', 'users.staffProfile', 'documents', 'subscription.plan', 'approvalLogs.changedBy', 'companyPermissions']),
            'activity' => ActivityLog::with('actor')->where('business_id', $company->id)->latest('occurred_at')->take(20)->get(),
            'loginHistory' => ActivityLog::with('actor')->where('business_id', $company->id)->latest('occurred_at')->take(10)->get(),
        ]);
    }

    public function openDashboard(Request $request, Business $company)
    {
        $data = $request->validate([
            'destination' => ['nullable', Rule::in([
                'business.dashboard', 'business.products.index', 'business.inventory', 'business.customers.index',
                'business.suppliers.index', 'business.orders.index', 'business.payments', 'business.khata',
                'business.deliveries', 'business.invoices.index', 'business.expenses.index', 'business.reports',
                'business.staff', 'business.settings', 'business.pos.index',
            ])],
        ]);

        $request->session()->put([
            'super_admin_business_context_id' => $company->id,
            'super_admin_business_context_name' => $company->business_name,
        ]);
        $this->audit($request, 'login as business started', $company, null, ['destination' => $data['destination'] ?? 'business.dashboard']);

        return redirect()->route($data['destination'] ?? 'business.dashboard')
            ->with('success', 'You are now viewing '.$company->business_name.'.');
    }

    public function returnToDashboard(Request $request)
    {
        $companyId = $request->session()->get('super_admin_business_context_id');
        $company = $companyId ? Business::find($companyId) : null;

        if ($company) {
            $this->audit($request, 'login as business ended', $company, null, null);
        }

        $request->session()->forget(['super_admin_business_context_id', 'super_admin_business_context_name']);

        return redirect()->route('admin.dashboard')->with('success', 'Returned to the Super Admin dashboard.');
    }

    public function resetOwnerPassword(Request $request, Business $company)
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], ['password.confirmed' => 'Password and confirm password do not match.']);

        abort_unless($company->owner, 404);
        $company->owner->update(['password' => Hash::make($data['password'])]);
        $this->audit($request, 'business owner password reset', $company, null, ['user_id' => $company->owner->id]);

        return back()->with('success', 'Business owner password reset successfully.');
    }

    public function resetStaffPassword(Request $request, Business $company, User $staff)
    {
        abort_unless($staff->business_id === $company->id && $staff->id !== $company->owner_id, 404);
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], ['password.confirmed' => 'Password and confirm password do not match.']);

        $staff->update(['password' => Hash::make($data['password'])]);
        $this->audit($request, 'staff password reset', $company, null, ['user_id' => $staff->id]);

        return back()->with('success', 'Password reset successfully for '.$staff->name.'.');
    }

    public function edit(Request $request, Business $company)
    {
        $this->audit($request, 'company edit opened', $company, null, null);

        return view('super-admin.companies.edit', ['company' => $company->load('owner')]);
    }

    public function update(UpdateCompanyRequest $request, Business $company)
    {
        $data = $request->validated();
        $old = $company->only(['business_name', 'business_type', 'category', 'phone', 'address', 'city', 'registration_number', 'tax_number']);

        DB::transaction(function () use ($company, $data) {
            $company->update(collect($data)->only(['business_name', 'business_type', 'category', 'phone', 'address', 'city', 'registration_number', 'tax_number'])->all());
            $company->owner?->update([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'] ?? $company->owner->phone,
            ]);
        });

        $this->audit($request, 'company updated', $company, $old, $company->fresh()->only(array_keys($old)));

        return redirect()->route('admin.companies.show', $company)->with('success', 'Company details updated.');
    }

    public function updateStatus(Request $request, Business $company)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'suspended'])],
            'admin_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $oldStatus = strtolower((string) $company->status);
        $newStatus = strtolower($data['status']);
        if ($oldStatus === $newStatus) {
            return back()->with('success', 'Company status is already '.ucfirst($newStatus).'.');
        }

        DB::transaction(function () use ($company, $oldStatus, $newStatus, $data) {
            $company->update(['status' => ucfirst($newStatus)]);
            $this->recordApprovalLog($company, $oldStatus, $newStatus, $data['admin_note'] ?? null);
        });

        $this->audit($request, 'company status changed', $company, ['status' => $oldStatus], ['status' => $newStatus, 'note' => $data['admin_note'] ?? null]);

        return redirect()->route('admin.companies.show', $company)->with('success', 'Company status updated.');
    }

    public function archive(Request $request, Business $company)
    {
        if (strtolower($company->status) === 'archived') {
            return back()->withErrors(['company' => 'This company is already archived.']);
        }

        $oldStatus = $company->status;
        DB::transaction(function () use ($company, $oldStatus) {
            $company->update([
                'archived_status' => $oldStatus,
                'archived_at' => now(),
                'archived_by' => auth()->id(),
                'status' => 'Archived',
            ]);
            $this->recordApprovalLog($company, $oldStatus, 'Archived', 'Company archived by Super Admin');
        });
        $this->audit($request, 'company archived', $company, ['status' => $oldStatus], ['status' => 'Archived']);

        return back()->with('success', 'Company archived. Historical records remain intact.');
    }

    public function restore(Request $request, Business $company)
    {
        abort_unless(strtolower((string) $company->status) === 'archived', 404);
        $restored = $company->archived_status ?: 'Pending';
        DB::transaction(function () use ($company, $restored) {
            $company->update(['status' => $restored, 'archived_at' => null, 'archived_by' => null, 'archived_status' => null]);
            $this->recordApprovalLog($company, 'Archived', $restored, 'Company restored by Super Admin');
        });
        $this->audit($request, 'company restored', $company, ['status' => 'Archived'], ['status' => $restored]);

        return back()->with('success', 'Company restored.');
    }

    public function destroy(Request $request, Business $company)
    {
        $hasRecords = $company->users()->exists()
            || Customer::where('business_id', $company->id)->withTrashed()->exists()
            || Product::where('business_id', $company->id)->withTrashed()->exists()
            || Order::where('business_id', $company->id)->withTrashed()->exists()
            || Payment::where('business_id', $company->id)->exists()
            || Delivery::where('business_id', $company->id)->exists()
            || Invoice::where('business_id', $company->id)->exists()
            || Supplier::where('business_id', $company->id)->withTrashed()->exists();

        if ($hasRecords) {
            return back()->withErrors(['company' => 'This company has operational records and cannot be deleted. Archive it instead.']);
        }

        $this->audit($request, 'company deleted', $company, $company->only(['business_name', 'status']), null);
        $company->delete();

        return redirect()->route('admin.companies.index')->with('success', 'Company deleted.');
    }

    private function audit(Request $request, string $action, Business $company, ?array $old, ?array $new): void
    {
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(), 'actor_id' => auth()->id(), 'actor_role' => auth()->user()?->role,
            'business_id' => $company->id, 'module' => 'Companies', 'action' => $action, 'description' => ucfirst($action).' for '.$company->business_name,
            'old_values' => $old, 'new_values' => $new, 'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    private function recordApprovalLog(Business $company, ?string $oldStatus, string $newStatus, ?string $note): void
    {
        if ($oldStatus !== null && strtolower($oldStatus) === strtolower($newStatus)) {
            return;
        }

        CompanyApprovalLog::create([
            'company_id' => $company->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }

    private function applyInitialTemplate(Business $company, ?int $templateId): void
    {
        if (!$templateId) {
            return;
        }

        $allowed = PermissionTemplate::findOrFail($templateId)->items()->where('allowed', true)->pluck('permission_key')->all();
        foreach (PermissionDefinition::where('status', 'active')->pluck('permission_key') as $key) {
            CompanyPermission::updateOrCreate(
                ['company_id' => $company->id, 'permission_key' => $key],
                ['allowed' => in_array($key, $allowed, true), 'assigned_by' => auth()->id()]
            );
        }
    }
}
