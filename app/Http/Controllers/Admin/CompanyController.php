<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Http\Requests\Admin\UpdateCompanyRequest;
use App\Mail\CompanyOnboardingAccessMail;
use App\Models\ActivityLog;
use App\Models\Business;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionChangeRequest;
use App\Notifications\SubscriptionStatusNotification;
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
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\PermissionDefinition;
use App\Notifications\CompanyRegistrationNotification;
use App\Notifications\BusinessDetailsChangeDecisionNotification;
use App\Notifications\BusinessDetailsUpdatedNotification;
use App\Notifications\BusinessDocumentVerificationNotification;
use App\Services\AccountingService;
use App\Services\BusinessWorkspaceAccessService;
use App\Services\BusinessDocumentFooterService;
use App\Services\CompanyOnboardingAccessService;
use App\Services\PermanentlyDeleteBusinessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        $query = Business::with(['owner', 'subscription.plan', 'assignments.user', 'documents', 'users:id,business_id,role'])
            ->withCount(['users', 'orders', 'products', 'customers'])
            ->addSelect([
                'suppliers_count' => Supplier::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('suppliers.business_id', 'businesses.id'),
                'purchases_count' => Purchase::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('purchases.business_id', 'businesses.id'),
            ]);

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
                ->orWhere('registration_number', 'like', "%{$search}%")
                ->orWhereHas('owner', fn ($owner) => $owner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")));
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

        $summary = Business::query()
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN LOWER(status) = 'approved' THEN 1 ELSE 0 END) as approved, SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) as pending, SUM(CASE WHEN LOWER(status) = 'suspended' THEN 1 ELSE 0 END) as suspended, SUM(CASE WHEN LOWER(status) = 'rejected' THEN 1 ELSE 0 END) as rejected")
            ->first();

        return view('super-admin.companies.index', [
            'companies' => $query->paginate(10)->withQueryString(),
            'statusFilter' => $status,
            'businessTypes' => collect(['Manufacturer', 'Distributor', 'Wholesaler', 'Retail Shop', 'Other'])
                ->merge(Business::query()->whereNotNull('business_type')->distinct()->pluck('business_type'))
                ->unique()->sort()->values(),
            'plans' => \App\Models\SubscriptionPlan::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }

    public function create()
    {
        return view('super-admin.companies.create', [
            'definitions' => app(\App\Services\CompanyPermissionService::class)->activeDefinitions(),
        ]);
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
            'histories' => $query->latest('changed_at')->paginate(12)->withQueryString(),
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
        $ownerImage = $request->hasFile('owner_profile_image')
            ? $request->file('owner_profile_image')->store('profile_images', 'public')
            : null;

        try {
            $created = DB::transaction(function () use ($request, $data, $ownerImage) {
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
                    'profile_image' => $ownerImage,
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
                foreach (['cnic_image', 'business_document', 'shop_image'] as $documentType) {
                    if ($request->hasFile($documentType)) {
                        BusinessDocument::create([
                            'business_id' => $company->id,
                            'document_type' => $documentType,
                            'file_path' => $request->file($documentType)->store('business-documents', 'public'),
                            'status' => 'Pending Verification',
                        ]);
                    }
                }

                app(AccountingService::class)->ensureDefaultAccounts($company->id);
                $this->applyInitialPermissions($company, $data['permissions'] ?? []);
                $this->recordApprovalLog($company, null, 'Approved', 'Company created and approved by Super Admin');

                User::where('role', 'super_admin')->where('status', 'active')->get()
                    ->each(fn (User $admin) => $admin->notify(new CompanyRegistrationNotification($company)));

                return compact('company', 'owner');
            });
        } catch (Throwable $exception) {
            if ($ownerImage) {
                Storage::disk('public')->delete($ownerImage);
            }
            \App\Models\AuditLog::create([
                'user_id' => auth()->id(),
                'actor_id' => auth()->id(),
                'actor_role' => auth()->user()?->role,
                'module' => 'Companies',
                'action' => 'company creation failed',
                'description' => 'Company creation failed: '.$exception->getMessage(),
                // Keep failed-creation diagnostics useful without recording
                // the owner's login identifier or any credential material.
                'new_values' => collect($data)->only(['business_name', 'business_type', 'company_phone', 'city', 'owner_name', 'owner_phone', 'permissions'])->all(),
                'ip_address' => app(\App\Services\AuditIpResolver::class)->capture($request),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            ]);

            throw $exception;
        }

        $company = $created['company'];
        $owner = $created['owner'];

        $this->audit($request, 'company created', $company, null, $company->only(['business_name', 'status']));
        $this->audit($request, 'footer settings created', $company, null, ['changed_fields' => ['default_footer']]);
        $this->audit($request, 'company permissions assigned', $company, null, ['permissions' => $data['permissions'] ?? []]);
        app(CompanyOnboardingAccessService::class)->remember($request, $company, $owner, $data['temporary_password']);

        return redirect()->route('admin.companies.onboarding', $company)
            ->with('success', 'Company created successfully.');
    }

    public function onboarding(Request $request, Business $company, CompanyOnboardingAccessService $onboarding)
    {
        $company->loadMissing('owner');
        $context = $onboarding->context($request, $company);

        if (! $context) {
            return redirect()->route('admin.companies.show', $company)
                ->with('info', 'The one-time onboarding details are no longer available. Generate new credentials to share access again.');
        }

        return view('super-admin.companies.onboarding', [
            'company' => $company,
            'context' => $context,
            'emailSubject' => $onboarding->emailSubject($context),
            'emailMessage' => $onboarding->emailMessage($context),
            'copyMessage' => $onboarding->copyMessage($context),
        ]);
    }

    public function sendOnboardingEmail(Request $request, Business $company, CompanyOnboardingAccessService $onboarding)
    {
        $company->loadMissing('owner');
        $context = $onboarding->context($request, $company);
        if (! $context) {
            return redirect()->route('admin.companies.show', $company)
                ->with('error', 'The one-time onboarding details are no longer available.');
        }

        if (blank($context['owner_email'])) {
            return back()->with('error', 'The owner does not have an email address.');
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        try {
            Mail::to($context['owner_email'])->send(new CompanyOnboardingAccessMail($data['subject'], $data['message']));
        } catch (Throwable $exception) {
            report($exception);
            $this->audit($request, 'onboarding access email failed', $company, null, ['channel' => 'email', 'status' => 'failed']);

            return back()->withInput()->with('error', 'Access details could not be sent. The company was created and remains available.');
        }

        $this->audit($request, 'onboarding access email sent', $company, null, [
            'channel' => 'email',
            'status' => 'sent',
            'owner_id' => $company->owner_id,
            'sent_at' => now()->toDateTimeString(),
        ]);

        return back()->with('success', 'Access details sent successfully.');
    }

    public function openOnboardingWhatsAppDraft(Request $request, Business $company, CompanyOnboardingAccessService $onboarding)
    {
        $company->loadMissing('owner');
        $context = $onboarding->context($request, $company);
        if (! $context) {
            return redirect()->route('admin.companies.show', $company)
                ->with('error', 'The one-time onboarding details are no longer available.');
        }

        if (blank($context['whatsapp_digits'])) {
            return back()->with('error', 'The owner does not have a valid international phone number.');
        }

        $this->audit($request, 'onboarding WhatsApp draft opened', $company, null, [
            'channel' => 'whatsapp',
            'status' => 'draft_opened',
            'owner_id' => $company->owner_id,
        ]);

        return redirect()->away('https://wa.me/'.$context['whatsapp_digits'].'?text='.rawurlencode($onboarding->whatsAppMessage($context)));
    }

    public function finishOnboarding(Request $request, Business $company, CompanyOnboardingAccessService $onboarding)
    {
        if (! $onboarding->context($request, $company)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Company onboarding is no longer available.'], 422);
            }

            return redirect()->route('admin.companies.show', $company)
                ->with('error', 'The one-time onboarding details are no longer available.');
        }

        try {
            $onboarding->forget($request);
            $this->audit($request, 'company onboarding completed', $company, null, ['status' => 'completed']);
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Company onboarding could not be completed. Please try again.'], 500);
            }

            return back()->with('error', 'Company onboarding could not be completed. Please try again.');
        }

        $redirect = route('admin.companies.show', $company);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => $redirect]);
        }

        return redirect()->to($redirect)
            ->with('success', 'Company setup has been completed successfully.');
    }

    public function show(Request $request, Business $company)
    {
        $this->audit($request, 'company viewed', $company, null, null);

        return view('super-admin.companies.show', [
            'company' => $company->load([
                'owner',
                'users.staffProfile',
                'documents',
                'documentFooter',
                'selectedPlan',
                'subscription.plan',
                'approvalLogs' => fn ($query) => $query->latest('changed_at')->take(5),
                'approvalLogs.changedBy',
                'companyPermissions',
            ]),
            'adminPlans' => SubscriptionPlan::query()->where('status', 'Active')->whereNull('archived_at')->orderBy('sort_order')->orderBy('name')->get(),
            'activity' => ActivityLog::with('actor')->where('business_id', $company->id)->latest('occurred_at')->take(8)->get(),
            'loginHistory' => ActivityLog::with('actor')->where('business_id', $company->id)->latest('occurred_at')->take(8)->get(),
            'pendingDetailRequestCount' => BusinessDetailChangeRequest::query()
                ->where('business_id', $company->id)
                ->where('status', 'Pending')
                ->count(),
        ]);
    }

    public function editDocumentFooter(Request $request, Business $company)
    {
        $footer = app(BusinessDocumentFooterService::class)->for($company);

        if ($footer->wasRecentlyCreated) {
            $this->audit($request, 'footer settings created', $company, null, ['changed_fields' => ['default_footer']]);
        }

        $this->audit($request, 'footer settings viewed', $company, null, null);

        return view('super-admin.companies.document-footer', compact('company', 'footer'));
    }

    public function updateDocumentFooter(Request $request, Business $company)
    {
        $company->loadMissing('owner');
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/'],
            'business_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($company->owner_id)],
            'website' => ['nullable', 'url', 'max:255'],
            'footer_title' => ['nullable', 'string', 'max:255'],
            'footer_message' => ['nullable', 'string', 'max:500'],
            'powered_by_text' => ['nullable', 'string', 'max:100'],
            'show_company_name' => ['nullable', 'boolean'],
            'show_footer_title' => ['nullable', 'boolean'],
            'show_footer_message' => ['nullable', 'boolean'],
            'show_address' => ['nullable', 'boolean'],
            'show_phone' => ['nullable', 'boolean'],
            'show_email' => ['nullable', 'boolean'],
            'show_website' => ['nullable', 'boolean'],
        ]);

        [$old, $new, $changed] = DB::transaction(function () use ($company, $data, $request): array {
            $lockedCompany = Business::with('owner')->lockForUpdate()->findOrFail($company->id);
            $footer = app(BusinessDocumentFooterService::class)->for($lockedCompany);
            $businessFields = ['business_name', 'address', 'phone', 'website'];
            $footerFields = ['footer_title', 'footer_message', 'powered_by_text', 'show_company_name', 'show_footer_title', 'show_footer_message', 'show_address', 'show_phone', 'show_email', 'show_website', 'show_powered_by'];
            $old = [
                ...$lockedCompany->only($businessFields),
                'business_email' => $lockedCompany->owner?->email,
                ...$footer->only($footerFields),
            ];

            $lockedCompany->fill(collect($data)->only($businessFields)->all())->save();
            if ($lockedCompany->owner && array_key_exists('business_email', $data)) {
                $lockedCompany->owner->update(['email' => $data['business_email']]);
            }
            $footer->fill([
                'footer_title' => $data['footer_title'] ?: $lockedCompany->business_name,
                'footer_message' => $data['footer_message'] ?: null,
                'powered_by_text' => $data['powered_by_text'] ?: app(BusinessDocumentFooterService::class)->platformPoweredByText(),
                'show_company_name' => $request->boolean('show_company_name'),
                'show_footer_title' => $request->boolean('show_footer_title'),
                'show_footer_message' => $request->boolean('show_footer_message'),
                'show_address' => $request->boolean('show_address'),
                'show_phone' => $request->boolean('show_phone'),
                'show_email' => $request->boolean('show_email'),
                'show_website' => $request->boolean('show_website'),
                'show_powered_by' => true,
            ])->save();
            $new = [
                ...$lockedCompany->fresh()->only($businessFields),
                'business_email' => $lockedCompany->owner?->fresh()?->email,
                ...$footer->fresh()->only($footerFields),
            ];
            $changed = array_keys(array_filter($new, fn ($value, string $field) => (string) $value !== (string) ($old[$field] ?? ''), ARRAY_FILTER_USE_BOTH));

            return [$old, $new, $changed];
        });
        if ($changed !== []) {
            $this->audit($request, 'footer settings updated', $company->fresh(), $old, $new);
        }

        return redirect()->route('admin.companies.document-footer.edit', $company)->with('success', 'Receipt footer settings updated.');
    }

    public function resetDocumentFooter(Request $request, Business $company)
    {
        $footer = app(BusinessDocumentFooterService::class)->reset($company);
        $this->audit($request, 'footer settings reset', $company, null, ['footer_id' => $footer->id]);

        return redirect()->route('admin.companies.document-footer.edit', $company)->with('success', 'Receipt footer reset to company defaults.');
    }

    public function openDashboard(Request $request, Business $company)
    {
        $data = $request->validate([
            'destination' => ['nullable', Rule::in([
                'business.dashboard', 'business.products.index', 'business.inventory',
                'business.customers.index', 'business.suppliers.index', 'business.purchases.index',
                'business.purchase-returns.index', 'business.sales.index', 'business.sales.returns.index',
                'business.khata', 'business.deliveries', 'business.expenses.index',
                'business.reports', 'business.staff', 'business.audit-logs.index',
                'business.settings',
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
        $this->audit($request, 'blocked business owner credential access', $company, null, ['operation' => 'password_reset']);

        abort(403, 'Super Admins cannot reset a business owner password.');
    }

    public function resetStaffPassword(Request $request, Business $company, User $staff)
    {
        abort_unless($staff->business_id === $company->id, 404);
        $this->audit($request, 'blocked company credential access', $company, null, ['operation' => 'staff_password_reset', 'staff_id' => $staff->id]);

        abort(403, 'Super Admins cannot reset company user passwords.');
    }

    public function edit(Request $request, Business $company)
    {
        $this->audit($request, 'company edit opened', $company, null, null);

        return view('super-admin.companies.edit', ['company' => $company->load('owner')]);
    }

    public function update(UpdateCompanyRequest $request, Business $company)
    {
        $data = $request->validated();
        $this->ensureVerificationDocumentsAreNew($request, $company);
        $old = $company->only(['business_name', 'business_type', 'business_description', 'phone', 'address', 'city', 'registration_number', 'tax_number', 'logo']);

        $oldOwnerImage = $company->owner?->profile_image;
        $newOwnerImage = $request->hasFile('owner_profile_image')
            ? $request->file('owner_profile_image')->store('profile_images', 'public')
            : null;

        $replacedDocumentPaths = [];
        try {
        DB::transaction(function () use ($request, $company, $data, $newOwnerImage, &$replacedDocumentPaths) {
            $company->update(collect($data)->only(['business_name', 'business_type', 'business_description', 'phone', 'address', 'city', 'registration_number', 'tax_number'])->all());
            $company->owner?->update([
                'name' => $data['owner_name'],
                'phone' => $data['owner_phone'],
            ]);
            if ($request->boolean('remove_owner_profile_image') && $company->owner?->profile_image) {
                $company->owner->update(['profile_image' => null]);
            }
            if ($newOwnerImage && $company->owner) {
                $company->owner->update(['profile_image' => $newOwnerImage]);
            }

            if ($request->boolean('remove_company_logo') && $company->logo) {
                Storage::disk('public')->delete($company->logo);
                $company->update(['logo' => null]);
            }
            if ($request->hasFile('company_logo')) {
                if ($company->logo) Storage::disk('public')->delete($company->logo);
                $company->update(['logo' => $request->file('company_logo')->store('business-logos', 'public')]);
            }
            $replacedDocumentPaths = $this->saveUploadedVerificationDocuments($request, $company);
        });
        } catch (Throwable $exception) {
            if ($newOwnerImage) {
                Storage::disk('public')->delete($newOwnerImage);
            }

            throw $exception;
        }

        if (($newOwnerImage || $request->boolean('remove_owner_profile_image')) && $oldOwnerImage) {
            Storage::disk('public')->delete($oldOwnerImage);
        }
        if ($replacedDocumentPaths) {
            Storage::disk('public')->delete($replacedDocumentPaths);
        }

        $company->refresh()->load('owner');
        $company->owner?->notify(new BusinessDetailsUpdatedNotification($company, $request->user()));
        $this->audit($request, 'company updated', $company, $old, $company->only(array_keys($old)));

        return redirect()->route('admin.companies.show', $company)->with('success', 'Company details updated.');
    }

    /** Add only missing registration documents for a company. */
    public function uploadVerificationDocuments(Request $request, Business $company)
    {
        $request->validate([
            'cnic_image' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'business_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'shop_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $types = ['cnic_image', 'business_document', 'shop_image'];
        if (! collect($types)->contains(fn (string $type) => $request->hasFile($type))) {
            throw ValidationException::withMessages([
                'documents' => 'Choose at least one verification document to upload.',
            ]);
        }

        $this->ensureVerificationDocumentsAreNew($request, $company);

        $replacedPaths = DB::transaction(fn () => $this->saveUploadedVerificationDocuments($request, $company));
        if ($replacedPaths) {
            Storage::disk('public')->delete($replacedPaths);
        }

        $this->audit($request, 'verification documents uploaded', $company, null, [
            'document_types' => collect($types)->filter(fn (string $type) => $request->hasFile($type))->values()->all(),
        ]);

        return back()->with('success', 'Missing verification documents uploaded and queued for review.');
    }

    /**
     * This method is called inside a database transaction by the company
     * update and document-upload flows.
     *
     * @return array<int, string>
     */
    private function saveUploadedVerificationDocuments(Request $request, Business $company): array
    {
        $replacedPaths = [];

        foreach (['cnic_image', 'business_document', 'shop_image'] as $documentType) {
            if (! $request->hasFile($documentType)) {
                continue;
            }

            $document = BusinessDocument::query()
                ->where('business_id', $company->id)
                ->where('document_type', $documentType)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (filled($document?->file_path)) {
                throw ValidationException::withMessages([
                    $documentType => $this->verificationDocumentLabel($documentType).' has already been uploaded and cannot be replaced.',
                ]);
            }

            $newPath = $request->file($documentType)->store('business-documents', 'public');

            $attributes = [
                'file_path' => $newPath,
                'status' => 'Pending Verification',
                'verified_by' => null,
                'verified_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'reupload_requested_by' => null,
                'reupload_requested_at' => null,
                'reupload_reason' => null,
            ];

            if ($document) {
                $document->update($attributes);
                continue;
            }

            BusinessDocument::create($attributes + [
                'business_id' => $company->id,
                'document_type' => $documentType,
            ]);
        }

        return array_values(array_unique($replacedPaths));
    }

    /** Prevent both UI and direct requests from overwriting registration evidence. */
    private function ensureVerificationDocumentsAreNew(Request $request, Business $company): void
    {
        $requestedTypes = collect(['cnic_image', 'business_document', 'shop_image'])
            ->filter(fn (string $type) => $request->hasFile($type));

        if ($requestedTypes->isEmpty()) {
            return;
        }

        $existingTypes = $company->documents()
            ->whereIn('document_type', $requestedTypes->all())
            ->whereNotNull('file_path')
            ->pluck('document_type');

        if ($existingTypes->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages(
            $existingTypes->mapWithKeys(fn (string $type) => [
                $type => $this->verificationDocumentLabel($type).' has already been uploaded and cannot be replaced.',
            ])->all()
        );
    }

    private function verificationDocumentLabel(string $type): string
    {
        return [
            'cnic_image' => 'CNIC',
            'business_document' => 'Business document',
            'shop_image' => 'Shop image',
        ][$type] ?? 'Verification document';
    }

    public function detailChangeRequests(Request $request)
    {
        $requests = BusinessDetailChangeRequest::with(['business', 'requester', 'reviewer'])
            ->when($request->integer('business_id'), fn ($query, int $businessId) => $query->where('business_id', $businessId))
            ->when($request->filled('status'), fn ($query, string $status) => $query->where('status', ucfirst(strtolower($status))))
            ->latest()
            ->paginate(12)
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
        $this->audit($request, 'business details change approved for application', $changeRequest->business, $this->auditableChangeValues($changeRequest->old_values), $this->auditableChangeValues($changeRequest->requested_values));

        return back()->with('success', 'Request approved. Review it once more, then use Apply Changes to update the business and notify its owner.');
    }

    public function applyDetailChangeRequest(Request $request, BusinessDetailChangeRequest $changeRequest)
    {
        abort_unless($changeRequest->status === 'Approved', 404);
        $company = $changeRequest->business()->with('owner')->firstOrFail();
        $oldValues = $company->only(['business_name', 'phone', 'address', 'city', 'category', 'logo']);
        $oldOwnerEmail = $company->owner?->email;
        $updates = collect($changeRequest->requested_values ?? [])
            ->only(['business_name', 'phone', 'address', 'city', 'category', 'logo'])
            ->all();

        DB::transaction(function () use ($company, $updates, $changeRequest): void {
            $company->update($updates);
            $requestedEmail = data_get($changeRequest->requested_values, 'owner_email');
            if ($requestedEmail && $company->owner && $requestedEmail !== $company->owner->email) {
                $company->owner->update(['email' => $requestedEmail]);
            }
            $changeRequest->update(['status' => 'Applied']);
        });

        if (!empty($updates['logo']) && $oldValues['logo'] && $oldValues['logo'] !== $updates['logo']) {
            Storage::disk('public')->delete($oldValues['logo']);
        }

        $auditOldValues = $oldValues;
        $auditNewValues = $updates;
        if (data_get($changeRequest->requested_values, 'owner_email')) {
            $auditOldValues['owner_email_changed'] = (bool) $oldOwnerEmail;
            $auditNewValues['owner_email_changed'] = true;
        }
        $this->audit($request, 'business details change applied', $company, $auditOldValues, $auditNewValues);
        $changeRequest->refresh()->load('business');
        $company->owner?->notify(new BusinessDetailsChangeDecisionNotification($changeRequest));

        return back()->with('success', 'Approved business-detail changes were applied. The business owner has been notified.');
    }

    public function rejectDetailChangeRequest(Request $request, BusinessDetailChangeRequest $changeRequest)
    {
        abort_unless($changeRequest->status === 'Pending', 404);
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);
        $changeRequest->update([
            'status' => 'Rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? null,
        ]);

        $changeRequest->load('business');
        $this->audit($request, 'business details change rejected', $changeRequest->business, $this->auditableChangeValues($changeRequest->old_values), $this->auditableChangeValues($changeRequest->requested_values));
        $changeRequest->business->owner?->notify(new BusinessDetailsChangeDecisionNotification($changeRequest));

        return back()->with('success', 'Business-detail change request rejected. The business owner has been notified.');
    }

    public function verifyDocument(Request $request, Business $company, BusinessDocument $document)
    {
        abort_unless($document->business_id === $company->id, 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'request_reupload'])],
            'reason' => ['nullable', 'string', 'max:2000', 'required_unless:decision,approve'],
        ], [
            'reason.required_unless' => 'Provide a reason before rejecting a document or requesting a re-upload.',
        ]);

        $document = DB::transaction(function () use ($document, $data): BusinessDocument {
            $lockedDocument = BusinessDocument::whereKey($document->id)
                ->where('business_id', $document->business_id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = match (strtolower(trim((string) $lockedDocument->status))) {
                'pending', 'pending verification' => 'Pending Verification',
                'verified' => 'Verified',
                'rejected' => 'Rejected',
                're-upload requested' => 'Re-upload Requested',
                default => $lockedDocument->status ?: 'Pending Verification',
            };
            $allowedDecisions = match ($currentStatus) {
                'Pending Verification' => ['approve', 'reject', 'request_reupload'],
                'Rejected' => ['request_reupload'],
                default => [],
            };

            if (!in_array($data['decision'], $allowedDecisions, true)) {
                throw ValidationException::withMessages([
                    'decision' => 'This verification action is not available for the document’s current status.',
                ]);
            }

            $updates = match ($data['decision']) {
                'approve' => [
                    'status' => 'Verified',
                    'verified_by' => auth()->id(),
                    'verified_at' => now(),
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                    'reupload_requested_by' => null,
                    'reupload_requested_at' => null,
                    'reupload_reason' => null,
                ],
                'reject' => [
                    'status' => 'Rejected',
                    'rejected_by' => auth()->id(),
                    'rejected_at' => now(),
                    'rejection_reason' => $data['reason'],
                ],
                default => [
                    'status' => 'Re-upload Requested',
                    'reupload_requested_by' => auth()->id(),
                    'reupload_requested_at' => now(),
                    'reupload_reason' => $data['reason'],
                ],
            };

            $lockedDocument->update($updates);

            return $lockedDocument->fresh();
        });

        $action = match ($data['decision']) {
            'approve' => 'document verified',
            'reject' => 'document rejected',
            default => 'document re-upload requested',
        };
        $this->audit($request, $action, $company, null, [
            'document_type' => $document->document_type,
            'document_status' => $document->status,
            'reason_provided' => filled($data['reason'] ?? null),
        ]);
        $company->loadMissing('owner');
        $company->owner?->notify(new BusinessDocumentVerificationNotification($document));

        return back()->with('success', 'Document verification status updated.');
    }

    public function updateStatus(Request $request, Business $company)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'suspended'])],
            'decision' => ['nullable', Rule::in(['status', 'request_changes'])],
            'admin_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $oldStatus = strtolower((string) $company->status);

        if (($data['decision'] ?? 'status') === 'request_changes') {
            if ($oldStatus !== 'pending') {
                return back()->withErrors(['status' => 'Registration changes can only be requested while a registration is pending.']);
            }

            if (blank($data['admin_note'] ?? null)) {
                throw ValidationException::withMessages(['admin_note' => 'Provide a note when requesting registration changes.']);
            }

            $company->update(['subscription_request_status' => 'Changes Requested', 'subscription_admin_note' => $data['admin_note']]);
            $company->owner?->notify(new SubscriptionStatusNotification('Changes Requested', 'TradeFlow requested changes to your registration: '.$data['admin_note'], $company->id));
            $this->audit($request, 'registration changes requested', $company, null, ['note' => $data['admin_note']]);

            return back()->with('success', 'Registration changes requested. The business owner has been notified.');
        }

        $newStatus = strtolower($data['status']);
        if ($oldStatus === $newStatus) {
            return back()->with('success', 'Company status is already '.ucfirst($newStatus).'.');
        }

        $allowedTransitions = [
            'pending' => ['approved', 'rejected'],
            'approved' => ['suspended'],
            'suspended' => ['approved'],
            'rejected' => [],
        ];

        if (!in_array($newStatus, $allowedTransitions[$oldStatus] ?? [], true)) {
            $message = $oldStatus === 'approved' && $newStatus === 'rejected'
                ? 'An approved company cannot be rejected through the registration review workflow.'
                : ($oldStatus === 'approved' && $newStatus === 'pending'
                    ? 'An approved company cannot be moved back to pending through the registration review workflow.'
                : ($oldStatus === 'rejected'
                    ? 'Rejected registrations must be reopened through the dedicated reopen workflow before they can be changed.'
                    : 'This company status transition is not allowed.'));

            return back()->withErrors(['status' => $message]);
        }

        if ($newStatus === 'approved' && ! $this->hasConfirmedRegistrationPlan($company)) {
            throw ValidationException::withMessages(['status' => 'Select or confirm a subscription plan before approving this business.']);
        }

        if ($oldStatus === 'pending' && $newStatus === 'approved' && ! $this->hasVerifiedRegistrationDocuments($company)) {
            throw ValidationException::withMessages(['status' => 'Verify all required registration documents before approving this business.']);
        }

        if ($newStatus === 'rejected' && blank($data['admin_note'] ?? null)) {
            throw ValidationException::withMessages(['admin_note' => 'Provide a reason when rejecting a registration.']);
        }

        DB::transaction(function () use ($company, $oldStatus, $newStatus, $data) {
            $company->update(['status' => ucfirst($newStatus)]);
            if ($newStatus === 'approved') {
                $this->startPendingSubscriptionTrial($company);
                $company->owner?->notify(new SubscriptionStatusNotification('Registration Approved', 'Your business registration has been approved.', $company->id));
            } elseif ($newStatus === 'rejected') {
                Subscription::where('business_id', $company->id)->where('status', 'Pending')->update(['status' => 'Cancelled']);
                $company->update(['subscription_request_status' => 'Rejected', 'subscription_admin_note' => $data['admin_note'] ?? null]);
                $company->owner?->notify(new SubscriptionStatusNotification('Registration Rejected', 'Your business registration was rejected.'.(!empty($data['admin_note']) ? ' Reason: '.$data['admin_note'] : ''), $company->id));
            }
            $this->recordApprovalLog($company, $oldStatus, $newStatus, $data['admin_note'] ?? null);
        });

        $auditAction = match ([$oldStatus, $newStatus]) {
            ['pending', 'approved'] => 'registration approved',
            ['approved', 'suspended'] => 'company suspended',
            ['suspended', 'approved'] => 'company reactivated',
            default => 'company status changed',
        };
        $this->audit($request, $auditAction, $company, ['status' => $oldStatus], ['status' => $newStatus, 'note' => $data['admin_note'] ?? null]);

        return back()->with('success', 'Company status updated.');
    }

    private function hasVerifiedRegistrationDocuments(Business $company): bool
    {
        $documents = $company->documents()
            ->whereIn('document_type', ['cnic_image', 'business_document', 'shop_image'])
            ->get()
            ->keyBy('document_type');

        // Super Admin-created companies can legitimately have no registration
        // uploads. Public registrations always have all three documents.
        if ($documents->isEmpty()) {
            return true;
        }

        return collect(['cnic_image', 'business_document', 'shop_image'])
            ->every(fn (string $type) => $documents->get($type)?->status === 'Verified');
    }

    private function startPendingSubscriptionTrial(Business $company): void
    {
        $subscription = Subscription::with('plan')->where('business_id', $company->id)->lockForUpdate()->first();
        if (! $subscription || $subscription->status !== 'Pending') {
            return;
        }

        $trialDays = max(0, (int) ($company->requested_trial_days ?? $subscription->plan?->trial_days ?? 14));
        if (! $company->trial_eligible || $trialDays === 0) {
            $subscription->update([
                'status' => 'Pending',
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addMonth()->toDateString(),
                'payment_status' => 'Pending',
            ]);
            $company->update(['subscription_request_status' => 'Approved']);
            $company->owner?->notify(new SubscriptionStatusNotification('Payment Required', 'Your registration is approved. Payment confirmation is required before subscription activation.', $company->id));
            return;
        }

        $subscription->update([
            'status' => 'Trial',
            'starts_at' => now()->toDateString(),
            'trial_start_at' => now()->toDateString(),
            'trial_end_at' => now()->addDays($trialDays)->toDateString(),
            'ends_at' => now()->addDays($trialDays)->toDateString(),
            'payment_status' => 'Pending',
        ]);
        $company->update(['subscription_request_status' => 'Activated']);
        $company->owner?->notify(new SubscriptionStatusNotification('Trial Activated', 'Your '.$subscription->plan?->name.' trial is active until '.$subscription->trial_end_at?->format('d M, Y').'.', $company->id));
    }

    public function updateRegistrationPlan(Request $request, Business $company)
    {
        abort_unless(strtolower((string) $company->status) === 'pending', 422, 'Only pending registrations can have their plan review updated.');

        $data = $request->validate([
            'plan_action' => ['required', Rule::in(['keep', 'change', 'require_selection'])],
            'selected_plan_id' => ['nullable', 'integer'],
            'billing_cycle' => ['nullable', Rule::in(['Monthly', 'Yearly'])],
            'trial_eligible' => ['nullable', 'boolean'],
            'requested_trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'change_reason' => ['nullable', 'string', 'max:2000'],
            'admin_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $subscription = Subscription::where('business_id', $company->id)->lockForUpdate()->first();
        $oldPlanId = $company->selected_plan_id ?? $subscription?->subscription_plan_id;
        $oldBillingCycle = $company->selected_billing_cycle ?? $subscription?->billing_cycle;
        $oldAmount = $company->selected_plan_price ?? $subscription?->amount;

        if ($data['plan_action'] === 'require_selection') {
            DB::transaction(function () use ($company, $data) {
                Subscription::where('business_id', $company->id)
                    ->where('status', 'Pending')
                    ->update(['status' => 'Cancelled']);

                $company->update([
                    'selected_plan_id' => null,
                    'selected_billing_cycle' => null,
                    'selected_plan_price' => null,
                    'selected_plan_snapshot' => null,
                    'subscription_request_status' => 'Pending Review',
                    'subscription_admin_note' => $data['admin_note'] ?? 'Plan selection required before approval.',
                ]);
            });
            $company->owner?->notify(new SubscriptionStatusNotification('Plan Selection Required', 'TradeFlow requires a subscription plan selection before your registration can be approved.', $company->id));
            $this->audit($request, 'registration plan removed', $company, ['selected_plan_id' => $oldPlanId], ['selected_plan_id' => null]);

            return back()->with('success', 'Plan selection was cleared. Approval will remain blocked until a plan is confirmed.');
        }

        $planId = (int) ($data['selected_plan_id'] ?? $oldPlanId);
        if ($planId < 1) {
            throw ValidationException::withMessages(['selected_plan_id' => 'Select an active subscription plan before confirming the registration.']);
        }
        $plan = SubscriptionPlan::query()->where('status', 'Active')->whereNull('archived_at')->findOrFail($planId);
        $cycle = $data['billing_cycle'] ?? $company->selected_billing_cycle ?? $subscription?->billing_cycle ?? 'Monthly';
        $amount = $plan->priceFor($cycle);
        $planChanged = $oldPlanId !== null
            && ($oldPlanId !== $plan->id || ($company->selected_billing_cycle ?? $subscription?->billing_cycle) !== $cycle);
        if ($planChanged && blank($data['change_reason'] ?? null)) {
            throw ValidationException::withMessages(['change_reason' => 'Provide a reason when changing the registration plan or billing cycle.']);
        }

        DB::transaction(function () use ($company, $subscription, $plan, $cycle, $amount, $data, $oldPlanId, $planChanged, $request) {
            $trialDays = $request->boolean('trial_eligible') ? (int) ($data['requested_trial_days'] ?? $plan->trial_days) : 0;
            $company->update([
                'selected_plan_id' => $plan->id,
                'selected_billing_cycle' => $cycle,
                'selected_plan_price' => $amount,
                'selected_plan_snapshot' => $this->planSnapshot($plan, $cycle, $amount),
                'trial_eligible' => $request->boolean('trial_eligible'),
                'requested_trial_days' => $trialDays,
                'subscription_request_status' => $planChanged ? 'Changed by Admin' : 'Approved',
                'subscription_admin_note' => $data['admin_note'] ?? null,
                'plan_selected_at' => $company->plan_selected_at ?? now(),
            ]);

            if ($subscription) {
                $subscription->update([
                    'subscription_plan_id' => $plan->id,
                    'billing_cycle' => $cycle,
                    'amount' => $amount,
                    'status' => $subscription->status === 'Cancelled' ? 'Pending' : $subscription->status,
                ]);
            } else {
                $subscription = Subscription::create(['business_id' => $company->id, 'subscription_plan_id' => $plan->id, 'billing_cycle' => $cycle, 'amount' => $amount, 'status' => 'Pending', 'payment_status' => 'Pending']);
            }

            if ($planChanged) {
                SubscriptionChangeRequest::create([
                    'business_id' => $company->id,
                    'subscription_id' => $subscription->id,
                    'current_plan_id' => $oldPlanId,
                    'requested_plan_id' => $plan->id,
                    'requested_by' => $request->user()->id,
                    'type' => 'Registration Change',
                    'billing_cycle' => $cycle,
                    'expected_amount' => $amount,
                    'note' => $data['change_reason'],
                    'status' => 'Changed by Admin',
                    'reviewed_by' => $request->user()->id,
                    'reviewed_at' => now(),
                ]);
            }
        });

        if ($planChanged) {
            $company->owner?->notify(new SubscriptionStatusNotification('Subscription Plan Updated', 'TradeFlow updated your registration plan to '.$plan->name.'.', $company->id));
        } else {
            $company->owner?->notify(new SubscriptionStatusNotification('Plan Selection Confirmed', 'Your '.$plan->name.' plan selection was confirmed.', $company->id));
        }
        if ($request->boolean('trial_eligible')) {
            $company->owner?->notify(new SubscriptionStatusNotification('Trial Approved', 'Your registration includes a '.(int) ($data['requested_trial_days'] ?? $plan->trial_days).'-day trial after approval.', $company->id));
        }
        $this->audit($request, $planChanged ? 'registration plan changed' : 'registration plan confirmed', $company, ['selected_plan_id' => $oldPlanId, 'billing_cycle' => $oldBillingCycle, 'amount' => $oldAmount], ['selected_plan_id' => $plan->id, 'billing_cycle' => $cycle, 'amount' => $amount, 'reason' => $data['change_reason'] ?? null]);
        $this->audit($request, $request->boolean('trial_eligible') ? 'trial approved' : 'trial disabled', $company, null, ['trial_days' => $request->boolean('trial_eligible') ? (int) ($data['requested_trial_days'] ?? $plan->trial_days) : 0]);

        return back()->with('success', $planChanged ? 'Registration plan updated and the owner has been notified.' : 'Registration plan confirmed.');
    }

    private function hasConfirmedRegistrationPlan(Business $company): bool
    {
        $planId = $company->selected_plan_id;

        return SubscriptionPlan::query()->whereKey($planId)->where('status', 'Active')->whereNull('archived_at')->exists();
    }

    private function planSnapshot(SubscriptionPlan $plan, string $cycle, int $amount): array
    {
        return ['plan_name' => $plan->name, 'billing_cycle' => $cycle, 'selected_price' => $amount, 'monthly_price' => $plan->priceFor('Monthly'), 'yearly_price' => $plan->priceFor('Yearly'), 'trial_days' => (int) $plan->trial_days, 'product_limit' => (int) $plan->product_limit, 'staff_limit' => (int) $plan->staff_limit, 'order_limit' => (int) $plan->order_limit, 'plan_status' => $plan->status, 'included_modules' => $plan->included_modules ?? []];
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

    public function destroy(Request $request, Business $company, PermanentlyDeleteBusinessService $deletionService)
    {
        $deletionService->delete($company, $request);

        return redirect()->route('admin.companies.index')->with('success', 'Company permanently deleted.');
    }

    private function audit(Request $request, string $action, Business $company, ?array $old, ?array $new): void
    {
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(), 'actor_id' => auth()->id(), 'actor_role' => auth()->user()?->role,
            'business_id' => $company->id, 'module' => 'Companies', 'action' => $action, 'description' => ucfirst($action).' for '.$company->business_name,
            'old_values' => $old, 'new_values' => $new, 'ip_address' => app(\App\Services\AuditIpResolver::class)->capture($request), 'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);
    }

    private function auditableChangeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return collect($values)
            ->except(['owner_email', 'email', 'password', 'password_confirmation'])
            ->all();
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
        $permissionService = app(\App\Services\CompanyPermissionService::class);
        $selected = $permissionService->withRequiredPermissions($permissions);
        $definitions = $permissionService->activeDefinitions();
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
