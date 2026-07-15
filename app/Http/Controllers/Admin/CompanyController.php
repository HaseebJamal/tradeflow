<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyRequest;
use App\Models\ActivityLog;
use App\Models\Business;
use App\Models\BusinessDetailChangeRequest;
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
use App\Notifications\CompanyRegistrationNotification;
use App\Notifications\BusinessDetailsChangeDecisionNotification;
use App\Notifications\BusinessDetailsUpdatedNotification;
use App\Services\AccountingService;
use App\Services\BusinessWorkspaceAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompanyController extends Controller
{
    public function index(Request $request, ?string $status = null)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name_asc', 'name_desc'])],
        ]);
        $query = Business::with(['owner', 'subscription.plan', 'assignments.user'])
            ->withCount(['users', 'orders', 'companyPermissions as permissions_count' => fn ($permission) => $permission
                ->where('allowed', true)
                ->where('permission_key', 'like', '%.view')]);

        $status ??= $request->string('status')->lower()->value();
        if ($request->boolean('filters_applied') && empty($filters['date_from']) && empty($filters['date_to'])) {
            $filters['date_from'] = now()->toDateString();
            $filters['date_to'] = now()->toDateString();
        }
        if ($status) {
            $query->whereRaw('LOWER(status) = ?', [strtolower($status)]);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($q) => $q->where('business_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")));
        }
        $query
            ->when($filters['business_type'] ?? null, fn ($q, $value) => $q->where('business_type', $value))
            ->when($filters['city'] ?? null, fn ($q, $value) => $q->where('city', 'like', "%{$value}%"))
            ->when($filters['plan_id'] ?? null, fn ($q, $value) => $q->whereHas('subscription', fn ($subscription) => $subscription->where('subscription_plan_id', $value)))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '<=', $value));

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'name_asc' => $query->orderBy('business_name'),
            'name_desc' => $query->orderByDesc('business_name'),
            default => $query->latest(),
        };

        return view('super-admin.companies.index', [
            'companies' => $query->paginate(20)->withQueryString(),
            'statusFilter' => $status,
            'businessTypes' => collect(['Manufacturer', 'Distributor', 'Wholesaler', 'Retail Shop', 'Other'])
                ->merge(Business::query()->whereNotNull('business_type')->distinct()->pluck('business_type'))
                ->unique()->sort()->values(),
            'plans' => \App\Models\SubscriptionPlan::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return view('super-admin.companies.create', ['definitions' => PermissionDefinition::where('status', 'active')->orderBy('module')->orderBy('label')->get()]);
    }

    public function approvalHistory(Request $request)
    {
        $filters = $request->validate([
            'company_id' => ['nullable', 'exists:businesses,id'], 'owner_id' => ['nullable', 'exists:users,id'],
            'old_status' => ['nullable', 'string', 'max:40'], 'new_status' => ['nullable', 'string', 'max:40'],
            'changed_by' => ['nullable', 'exists:users,id'], 'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'search' => ['nullable', 'string', 'max:255'],
        ]);
        // Approval history opens on the current day, matching the other
        // operational logs. A one-sided custom date filter is preserved.
        $filters += ['date_from' => null, 'date_to' => null];
        if (!$filters['date_from'] && !$filters['date_to']) {
            $filters['date_from'] = now()->toDateString();
            $filters['date_to'] = now()->toDateString();
        }

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
            'filters' => $filters,
        ]);
    }

    public function approvalHistoryShow(CompanyApprovalLog $approvalLog)
    {
        return view('super-admin.approvals.show', ['log' => $approvalLog->load(['company.owner', 'changedBy'])]);
    }

    public function store(StoreCompanyRequest $request)
    {
        $data = $request->validated();

        try {
            $company = DB::transaction(function () use ($request, $data) {
                if (Business::whereRaw('LOWER(business_name) = ?', [mb_strtolower($data['business_name'])])->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['business_name' => 'A company with this name already exists.']);
                }

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
                    'business_description' => $data['business_description'] ?? null,
                    'phone' => $data['company_phone'],
                    'address' => $data['address'],
                    'city' => $data['city'],
                    'registration_number' => $data['registration_number'] ?? null,
                    'tax_number' => $data['tax_number'] ?? null,
                    'status' => 'Approved',
                ]);

                $owner->update(['business_id' => $company->id]);

                if ($request->hasFile('company_logo')) {
                    $company->update(['logo' => $request->file('company_logo')->store('business-logos', 'public')]);
                }
                if ($request->hasFile('business_document')) {
                    BusinessDocument::create(['business_id' => $company->id, 'document_type' => 'business_document', 'file_path' => $request->file('business_document')->store('business-documents', 'public')]);
                }

                app(AccountingService::class)->ensureDefaultAccounts($company->id);
                $this->applyInitialPermissions($company, $data['permissions'] ?? []);
                $this->recordApprovalLog($company, null, 'Approved', 'Company created and approved by Super Admin');

                User::where('role', 'super_admin')->where('status', 'active')->get()
                    ->each(fn (User $admin) => $admin->notify(new CompanyRegistrationNotification($company)));

                return $company;
            });
        } catch (Throwable $exception) {
            \App\Models\AuditLog::create([
                'user_id' => auth()->id(),
                'actor_id' => auth()->id(),
                'actor_role' => auth()->user()?->role,
                'module' => 'Companies',
                'action' => 'company creation failed',
                'description' => 'Company creation failed: '.$exception->getMessage(),
                'new_values' => collect($data)->only(['business_name', 'business_type', 'company_phone', 'city', 'owner_name', 'owner_email', 'owner_phone', 'permissions'])->all(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);

            throw $exception;
        }

        $this->audit($request, 'company created', $company, null, $company->only(['business_name', 'status']));
        $this->audit($request, 'company permissions assigned', $company, null, ['permissions' => $data['permissions'] ?? []]);

        return redirect()->route('admin.companies.show', $company)
            ->with('success', 'Company created successfully.');
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
                'business.suppliers.index', 'business.purchases.index', 'business.purchase-returns.index', 'business.sales.index', 'business.sales.returns.index', 'business.khata',
                'business.deliveries', 'business.expenses.index', 'business.reports',
                'business.staff', 'business.audit-logs.index', 'business.settings', 'business.pos.index',
            ])],
        ]);

        $request->session()->put([
            'super_admin_business_context_id' => $company->id,
            'super_admin_business_context_name' => $company->business_name,
        ]);
        $destination = $data['destination']
            ?? app(BusinessWorkspaceAccessService::class)->firstEnabledRoute($request->user(), $company)
            ?? 'business.access-denied';

        $this->audit($request, 'login as business started', $company, null, ['destination' => $destination]);

        // The persistent business-context banner identifies the selected company
        // until the Super Admin explicitly returns to the platform dashboard.
        // Do not also show a transient success alert when entering that context.
        return redirect()->route($destination);
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
        $old = $company->only(['business_name', 'business_type', 'business_description', 'phone', 'address', 'city', 'registration_number', 'tax_number', 'logo']);

        DB::transaction(function () use ($request, $company, $data) {
            $company->update(collect($data)->only(['business_name', 'business_type', 'business_description', 'phone', 'address', 'city', 'registration_number', 'tax_number'])->all());
            $ownerData = [
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'],
            ];
            if (!empty($data['owner_password'])) $ownerData['password'] = Hash::make($data['owner_password']);
            $company->owner?->update($ownerData);

            if ($request->boolean('remove_company_logo') && $company->logo) {
                Storage::disk('public')->delete($company->logo);
                $company->update(['logo' => null]);
            }
            if ($request->hasFile('company_logo')) {
                if ($company->logo) Storage::disk('public')->delete($company->logo);
                $company->update(['logo' => $request->file('company_logo')->store('business-logos', 'public')]);
            }
            if ($request->hasFile('business_document')) {
                BusinessDocument::create(['business_id' => $company->id, 'document_type' => 'business_document', 'file_path' => $request->file('business_document')->store('business-documents', 'public')]);
            }
        });

        $company->refresh()->load('owner');
        $company->owner?->notify(new BusinessDetailsUpdatedNotification($company, $request->user()));
        $this->audit($request, 'company updated', $company, $old, $company->only(array_keys($old)));

        return redirect()->route('admin.companies.show', $company)->with('success', 'Company details updated.');
    }

    public function detailChangeRequests(Request $request)
    {
        $requests = BusinessDetailChangeRequest::with(['business', 'requester', 'reviewer'])
            ->when($request->integer('business_id'), fn ($query, int $businessId) => $query->where('business_id', $businessId))
            ->when($request->filled('status'), fn ($query, string $status) => $query->where('status', ucfirst(strtolower($status))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('super-admin.companies.detail-change-requests', [
            'requests' => $requests,
            'businesses' => Business::orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }

    public function approveDetailChangeRequest(Request $request, BusinessDetailChangeRequest $changeRequest)
    {
        abort_unless($changeRequest->status === 'Pending', 404);
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);
        $changeRequest->update([
            'status' => 'Approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);
        $this->audit($request, 'business details change approved for application', $changeRequest->business, $changeRequest->old_values, $changeRequest->requested_values);

        return back()->with('success', 'Request approved. Review it once more, then use Apply Changes to update the business and notify its owner.');
    }

    public function applyDetailChangeRequest(Request $request, BusinessDetailChangeRequest $changeRequest)
    {
        abort_unless($changeRequest->status === 'Approved', 404);
        $company = $changeRequest->business()->with('owner')->firstOrFail();
        $oldValues = $company->only(['business_name', 'phone', 'address', 'city', 'category', 'logo']);
        $updates = collect($changeRequest->requested_values ?? [])
            ->only(['business_name', 'phone', 'address', 'city', 'category', 'logo'])
            ->all();

        DB::transaction(function () use ($company, $updates, $changeRequest): void {
            $company->update($updates);
            $changeRequest->update(['status' => 'Applied']);
        });

        if (!empty($updates['logo']) && $oldValues['logo'] && $oldValues['logo'] !== $updates['logo']) {
            Storage::disk('public')->delete($oldValues['logo']);
        }

        $this->audit($request, 'business details change applied', $company, $oldValues, $updates);
        $changeRequest->refresh()->load('business');
        $company->owner?->notify(new BusinessDetailsChangeDecisionNotification($changeRequest));

        return back()->with('success', 'Approved business-detail changes were applied. The business owner has been notified.');
    }

    public function rejectDetailChangeRequest(Request $request, BusinessDetailChangeRequest $changeRequest)
    {
        abort_unless($changeRequest->status === 'Pending', 404);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);
        $changeRequest->update([
            'status' => 'Rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);

        $changeRequest->load('business');
        $this->audit($request, 'business details change rejected', $changeRequest->business, $changeRequest->old_values, $changeRequest->requested_values);
        $changeRequest->business->owner?->notify(new BusinessDetailsChangeDecisionNotification($changeRequest));

        return back()->with('success', 'Business-detail change request rejected. The business owner has been notified.');
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

        return back()->with('success', 'Company status updated.');
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
        $data = $request->validate([
            'admin_password' => ['required', 'string'],
        ]);

        if (!Hash::check($data['admin_password'], (string) auth()->user()->password)) {
            return back()->withErrors(['admin_password' => 'The Super Admin password is incorrect. The company was not deleted.']);
        }

        $counts = $this->companyDeletionCounts($company);
        $totalRecords = array_sum($counts);
        $companyName = $company->business_name;

        DB::transaction(function () use ($request, $company): void {
            // Related operational records are protected by database foreign-key
            // cascades. Users are deliberately removed afterwards because their
            // business_id is nullable to support platform accounts.
            $this->audit($request, 'company permanently deleted', $company, $company->only(['business_name', 'status']), null);
            $company->delete();

            User::withTrashed()
                ->where('business_id', $company->id)
                ->get()
                ->each
                ->forceDelete();
        });

        return redirect()->route('admin.companies.index')->with(
            'success',
            $companyName.' and '.$totalRecords.' related record'.($totalRecords === 1 ? '' : 's').' were permanently deleted after Super Admin password verification.'
        );
    }

    private function companyDeletionCounts(Business $company): array
    {
        return [
            'staff_accounts' => User::withTrashed()->where('business_id', $company->id)->count(),
            'customers' => Customer::withTrashed()->where('business_id', $company->id)->count(),
            'products' => Product::withTrashed()->where('business_id', $company->id)->count(),
            'orders' => Order::withTrashed()->where('business_id', $company->id)->count(),
            'payments' => Payment::where('business_id', $company->id)->count(),
            'deliveries' => Delivery::where('business_id', $company->id)->count(),
            'invoices' => Invoice::where('business_id', $company->id)->count(),
            'suppliers' => Supplier::withTrashed()->where('business_id', $company->id)->count(),
        ];
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

    private function applyInitialPermissions(Business $company, array $permissions): void
    {
        $selected = collect($permissions)->map(fn ($key) => strtolower((string) $key))->all();
        $definitions = PermissionDefinition::where('status', 'active')->get(['module', 'permission_key']);
        $enabledModules = $definitions
            ->filter(fn (PermissionDefinition $definition) => $definition->permission_key === strtolower($definition->module).'.view')
            ->filter(fn (PermissionDefinition $definition) => in_array(strtolower($definition->permission_key), $selected, true))
            ->pluck('module')->map(fn ($module) => strtolower($module))->all();

        foreach ($definitions as $definition) {
            $key = strtolower($definition->permission_key);
            $isModule = $key === strtolower($definition->module).'.view';
            CompanyPermission::updateOrCreate(
                ['company_id' => $company->id, 'permission_key' => $definition->permission_key],
                ['allowed' => $isModule ? in_array($key, $selected, true) : (in_array(strtolower($definition->module), $enabledModules, true) && in_array($key, $selected, true)), 'assigned_by' => auth()->id()]
            );
        }
    }
}
