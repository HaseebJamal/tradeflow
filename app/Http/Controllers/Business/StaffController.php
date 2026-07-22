<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\StoreStaffRequest;
use App\Http\Requests\Business\UpdateStaffRequest;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PermissionDefinition;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\CompanyPermissionService;
use App\Support\BusinessStaffRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->staffQuery()->with('staffProfile');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
            );
        }

        if ($request->filled('role')) {
            $query->whereHas('staffProfile', fn ($profile) => $profile->where('custom_role_name', $request->string('role')->toString()));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        } else {
            $query->where('status', '!=', 'archived');
        }

        $staff = $query->latest()->paginate(12)->withQueryString();
        $staff->getCollection()->each(function (User $member): void {
            $member->setAttribute('can_delete', $this->canBeSafelyDeleted($member));
        });

        return view('business.staff.index', [
            'staff' => $staff,
            'stats' => $this->stats(),
            'roles' => BusinessStaffRoles::ROLES,
            'customRoleNames' => $this->customRoleNames(),
            'permissionGroups' => $this->companyPermissionGroups(),
            'roleDefaults' => collect(array_keys(BusinessStaffRoles::ROLES))
                ->mapWithKeys(fn (string $role) => [$role => BusinessStaffRoles::defaults($role)])
                ->all(),
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $this->ensureStaffCapacity();
        $data = $request->validated();
        $this->assertAssignableRole('custom_staff', 'staff.create');
        $this->assertCustomRoleName($data['role']);
        $this->assertRequestedPermissionsAssignable($data['permissions'] ?? []);
        $imagePath = $request->hasFile('profile_image')
            ? $request->file('profile_image')->store('profile_images', 'public')
            : null;

        try {
            $staff = DB::transaction(function () use ($data, $imagePath) {
                $actor = auth()->user();
                $staff = User::create([
                    'business_id' => $this->businessId(),
                    'parent_user_id' => $actor->id,
                    'created_by' => $actor->id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => Hash::make($data['password']),
                    'role' => 'custom_staff',
                    'status' => $data['status'],
                    'profile_image' => $imagePath,
                    'permissions' => $this->normalisePermissions($data['permissions'] ?? []),
                ]);

                $staff->staffProfile()->create($this->profileData($data));
                $this->audit('staff created', $staff, null, $staff->only(['name', 'email', 'role', 'status', 'permissions']));

                return $staff;
            });
        } catch (Throwable $exception) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        return redirect()->route('business.staff')
            ->with('success', 'Staff account created for '.$staff->name.'.')
            ->with('staff_draft_created', true);
    }

    public function show(User $staff): View
    {
        $staff = $this->scopedStaff($staff, 'staff.view')->load(['staffProfile', 'creator', 'business']);
        $this->audit('staff viewed', $staff);

        return view('business.staff.show', [
            'staff' => $staff,
            'activity' => $this->activity($staff),
            'roles' => BusinessStaffRoles::ROLES,
            'customRoleNames' => $this->customRoleNames(),
        ]);
    }

    public function edit(User $staff): View
    {
        return view('business.staff.edit', [
            'staff' => $this->scopedStaff($staff)->load('staffProfile'),
            'roles' => BusinessStaffRoles::ROLES,
            'customRoleNames' => $this->customRoleNames(),
            'permissionGroups' => $this->companyPermissionGroups(),
            'roleDefaults' => collect(array_keys(BusinessStaffRoles::ROLES))
                ->mapWithKeys(fn (string $role) => [$role => BusinessStaffRoles::defaults($role)])
                ->all(),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $staff): RedirectResponse
    {
        $staff = $this->scopedStaff($staff);
        $data = $request->validated();
        $this->guardAgainstSelfAdministration($staff, $data);
        $this->assertAssignableRole('custom_staff', 'staff.edit');
        $this->assertCustomRoleName($data['role'], $staff);
        $this->assertRequestedPermissionsAssignable($data['permissions'] ?? [], $staff);
        $oldImage = $staff->profile_image;
        $newImage = $request->hasFile('profile_image')
            ? $request->file('profile_image')->store('profile_images', 'public')
            : null;

        try {
            DB::transaction(function () use ($staff, $data, $newImage) {
                $oldValues = $staff->only(['name', 'email', 'phone', 'role', 'status', 'permissions']);
                $userData = [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'role' => 'custom_staff',
                    'status' => $data['status'],
                    'permissions' => $this->normalisePermissions($data['permissions'] ?? []),
                ];

                if (!empty($data['password'])) {
                    $userData['password'] = Hash::make($data['password']);
                }
                if ($newImage) {
                    $userData['profile_image'] = $newImage;
                }

                $staff->update($userData);
                $staff->staffProfile()->updateOrCreate(['user_id' => $staff->id], $this->profileData($data));
                if ($staff->status !== 'active') {
                    $this->invalidateSessions($staff);
                }
                $this->audit('staff updated', $staff, $oldValues, $staff->fresh()->only(['name', 'email', 'phone', 'role', 'status', 'permissions']));
            });
        } catch (Throwable $exception) {
            if ($newImage) {
                Storage::disk('public')->delete($newImage);
            }

            throw $exception;
        }

        if ($newImage && $oldImage && Storage::disk('public')->exists($oldImage)) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->route('business.staff.show', $staff)->with('success', 'Staff account updated.');
    }

    public function updateStatus(Request $request, User $staff): RedirectResponse
    {
        $staff = $this->scopedStaff($staff);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive', 'suspended', 'archived'])]]);
        $this->guardAgainstSelfAdministration($staff, $data);
        $oldStatus = $staff->status;
        $staff->update(['status' => $data['status']]);
        if ($staff->status !== 'active') {
            $this->invalidateSessions($staff);
        }
        $this->audit('staff status changed', $staff, ['status' => $oldStatus], ['status' => $data['status']]);

        return back()->with('success', 'Staff status changed to '.ucfirst($data['status']).'.');
    }

    public function resetPassword(Request $request, User $staff): RedirectResponse
    {
        $staff = $this->scopedStaff($staff);
        $this->guardAgainstSelfAdministration($staff, [], true);
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], ['password.confirmed' => 'Password and confirm password do not match.']);

        $staff->update(['password' => Hash::make($data['password'])]);
        $this->audit('staff password reset', $staff);

        return back()->with('success', 'Password reset for '.$staff->name.'.');
    }

    public function archive(User $staff): RedirectResponse
    {
        $staff = $this->scopedStaff($staff);
        $this->guardAgainstSelfAdministration($staff, ['status' => 'archived']);
        $oldStatus = $staff->status;
        $staff->update(['status' => 'archived']);
        $this->invalidateSessions($staff);
        $this->audit('staff archived', $staff, ['status' => $oldStatus], ['status' => 'archived']);

        return redirect()->route('business.staff')->with('success', 'Staff account archived. Historical records were retained.');
    }

    public function restore(User $staff): RedirectResponse
    {
        $staff = $this->scopedStaff($staff);
        $this->guardAgainstSelfAdministration($staff, ['status' => 'inactive']);
        abort_unless($staff->status === 'archived', 404);
        $staff->update(['status' => 'inactive']);
        $this->audit('staff restored', $staff, ['status' => 'archived'], ['status' => 'inactive']);

        return back()->with('success', 'Staff account restored as inactive. Activate it when access should resume.');
    }

    public function destroy(User $staff): RedirectResponse
    {
        $staff = $this->scopedStaff($staff);
        $this->guardAgainstSelfAdministration($staff, [], true);

        if (!$this->canBeSafelyDeleted($staff)) {
            return $this->archive($staff);
        }

        DB::transaction(function () use ($staff): void {
            $this->audit('staff deleted', $staff);
            $staff->staffProfile()->delete();
            $staff->delete();
        });

        return redirect()->route('business.staff')->with('success', 'Staff account deleted.');
    }

    private function profileData(array $data): array
    {
        return [
            'custom_role_name' => $data['role'],
            'father_name' => $data['father_name'] ?? null,
            'cnic' => $data['cnic'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'employment_type' => $data['employment_type'],
            'joining_date' => $data['joining_date'],
            'salary' => $data['salary'] ?? null,
        ];
    }

    private function normalisePermissions(array $selected): array
    {
        $companyPermissions = app(CompanyPermissionService::class);
        $actor = auth()->user();
        $available = $companyPermissions->allowedDefinitionsFor($actor)->pluck('permission_key')->all();

        $selected = collect($selected)
            ->map(fn ($permission) => $companyPermissions->normalise((string) $permission))
            ->filter(fn (string $permission) => in_array($permission, $available, true))
            ->filter(fn (string $permission) => $companyPermissions->allows($actor, $permission))
            ->filter(fn (string $permission) => $this->actorCanGrant($permission))
            ->unique()
            ->values()
            ->all();

        $modules = collect($selected)
            ->map(fn (string $permission) => str($permission)->before('.')->toString())
            ->unique()
            ->values()
            ->all();

        return array_values(array_unique([...$modules, ...$selected]));
    }

    private function assertRequestedPermissionsAssignable(array $selected, ?User $target = null): void
    {
        $companyPermissions = app(CompanyPermissionService::class);
        $actor = auth()->user();
        $available = $companyPermissions->allowedDefinitionsFor($actor)
            ->pluck('permission_key')
            ->map(fn ($permission) => $companyPermissions->normalise((string) $permission))
            ->all();
        $requested = collect($selected)
            ->map(fn ($permission) => $companyPermissions->normalise((string) $permission))
            ->unique()
            ->values()
            ->all();
        $outsideCeiling = collect($requested)->contains(fn (string $permission) => !in_array($permission, $available, true)
            || !$this->actorCanGrant($permission));

        if (!$outsideCeiling) {
            return;
        }

        $this->audit('Privilege Escalation Blocked', $target ?? $actor, null, ['blocked' => true]);
        throw ValidationException::withMessages([
            'permissions' => 'You cannot assign permissions that you do not have.',
        ]);
    }

    private function actorCanGrant(string $permission): bool
    {
        $actor = auth()->user();
        $companyPermissions = app(CompanyPermissionService::class);

        // A staff manager can delegate only permissions that are both enabled
        // for the company and effective for the manager's own account.
        return $companyPermissions->allowsUser($actor, 'staff.permissions')
            && $companyPermissions->allowsUser($actor, $permission);
    }

    private function guardAgainstSelfAdministration(User $staff, array $data, bool $destructive = false): void
    {
        $actor = auth()->user();
        if (!$actor || $actor->id !== $staff->id) {
            return;
        }

        if ($destructive || (array_key_exists('status', $data) && $data['status'] !== $staff->status)) {
            $this->audit('Privilege Escalation Blocked', $staff, null, ['blocked' => true]);
            throw ValidationException::withMessages([
                'staff' => 'You cannot change your own account status from Roles & Users.',
            ]);
        }

        $requestedRole = trim((string) ($data['role'] ?? ''));
        $currentRole = trim((string) ($staff->staffProfile?->custom_role_name ?? ''));
        if ($requestedRole !== '' && $requestedRole !== $currentRole) {
            $this->audit('Self Role Change Blocked', $staff, null, ['blocked' => true]);
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }

        if (array_key_exists('permissions', $data)
            && $this->canonicalPermissions($data['permissions'] ?? []) !== $this->canonicalPermissions($staff->permissions ?? [])) {
            $this->audit('Self Permission Change Blocked', $staff, null, ['blocked' => true]);
            throw ValidationException::withMessages(['permissions' => 'You cannot change your own permissions.']);
        }
    }

    private function canonicalPermissions(array $permissions): array
    {
        $companyPermissions = app(CompanyPermissionService::class);

        return collect($permissions)
            ->map(fn ($permission) => $companyPermissions->normalise((string) $permission))
            ->filter(fn (string $permission) => str_contains($permission, '.'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function companyPermissionGroups(): array
    {
        $companyPermissions = app(CompanyPermissionService::class);
        $grouped = $companyPermissions->allowedDefinitionsFor(auth()->user())
            ->groupBy(fn (PermissionDefinition $definition) => strtolower($definition->module));

        $order = ['dashboard', 'products', 'units', 'categories', 'inventory', 'suppliers', 'purchases', 'purchase_returns', 'customers', 'sales', 'pos', 'sales_returns', 'deliveries', 'accounting', 'expenses', 'reports', 'notifications', 'staff', 'audit_logs', 'settings'];

        return collect($order)
            ->filter(fn (string $module) => $grouped->has($module))
            ->mapWithKeys(fn (string $module) => [$module => $grouped->get($module)])
            ->all();
    }

    private function staffQuery()
    {
        return User::query()
            ->where('business_id', $this->businessId())
            ->whereIn('role', array_keys(BusinessStaffRoles::ROLES));
    }

    private function scopedStaff(User $staff, string $requiredPermission = 'staff.edit'): User
    {
        $actor = auth()->user();
        if ($actor && $actor->id === $staff->id && $staff->role === 'business_owner') {
            $this->audit('Self Role Change Blocked', $staff, null, ['blocked' => true]);
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }
        $isValidTarget = $staff->business_id === $this->businessId()
            && array_key_exists($staff->role, BusinessStaffRoles::ROLES);
        $canManageStaff = app(CompanyPermissionService::class)->allowsUser($actor, $requiredPermission);

        if (!$isValidTarget || !$canManageStaff) {
            throw ValidationException::withMessages(['staff' => 'You do not have permission to perform this action.']);
        }

        return $staff;
    }

    private function canBeSafelyDeleted(User $staff): bool
    {
        $businessId = $staff->business_id;
        $hasOrders = Order::where('business_id', $businessId)->where('created_by', $staff->id)->exists();
        $hasDeliveries = Delivery::where('business_id', $businessId)->where('delivery_staff_id', $staff->id)->exists();
        $hasPayments = Schema::hasColumn('payments', 'created_by')
            && Payment::where('business_id', $businessId)->where('created_by', $staff->id)->exists();
        $hasAccounting = Schema::hasTable('journal_entries')
            && DB::table('journal_entries')->where('business_id', $businessId)
                ->where(fn ($query) => $query->where('created_by', $staff->id)->orWhere('posted_by', $staff->id))
                ->exists();
        $hasAuditTrail = AuditLog::where('business_id', $businessId)
            ->where(fn ($query) => $query->where('user_id', $staff->id)->orWhere('actor_id', $staff->id))
            ->exists();

        return !($hasOrders || $hasDeliveries || $hasPayments || $hasAccounting || $hasAuditTrail);
    }

    private function assertAssignableRole(string $role, string $requiredPermission): void
    {
        $actor = auth()->user();
        $canAssign = app(CompanyPermissionService::class)->allowsUser($actor, $requiredPermission);

        if (!$canAssign) {
            throw ValidationException::withMessages(['role' => 'You do not have permission to assign this business role.']);
        }
    }

    private function assertCustomRoleName(string $role, ?User $target = null): void
    {
        $reservedRoles = ['super admin', 'business owner', 'platform admin', 'platform sub admin', 'administrator', 'admin'];
        if (!in_array(strtolower(trim($role)), $reservedRoles, true)) {
            return;
        }

        $this->audit('Privilege Escalation Blocked', $target ?? auth()->user(), null, ['blocked' => true]);
        throw ValidationException::withMessages([
            'role' => 'Protected platform and owner roles cannot be assigned to staff accounts.',
        ]);
    }

    private function ensureStaffCapacity(): void
    {
        $business = Business::with('subscription.plan')->findOrFail($this->businessId());
        if ($business->subscription?->plan && !in_array($business->subscription->status, ['Trial', 'Active', 'Expiring'], true)) {
            throw ValidationException::withMessages(['staff' => 'Your subscription is not active. Please contact your Platform Administrator.']);
        }
        $limit = $business->subscription?->plan?->staff_limit;

        if ($limit && $this->staffQuery()->where('status', '!=', 'archived')->count() >= $limit) {
            throw ValidationException::withMessages(['staff' => 'Your current subscription staff limit has been reached.']);
        }
    }

    private function stats(): array
    {
        $base = $this->staffQuery()->where('status', '!=', 'archived');

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'inactive' => (clone $base)->whereIn('status', ['inactive', 'suspended'])->count(),
            'roles' => $this->customRoleNames()->count(),
            'with_permissions' => (clone $base)->whereNotNull('permissions')->where('permissions', '!=', '[]')->count(),
            'suspended' => (clone $base)->where('status', 'suspended')->count(),
        ];
    }

    private function activity(User $staff): array
    {
        $ordersCreated = Schema::hasColumn('orders', 'created_by')
            ? Order::where('business_id', $staff->business_id)->where('created_by', $staff->id)->count()
            : 0;
        $paymentsCollected = Schema::hasColumn('payments', 'created_by')
            ? Payment::where('business_id', $staff->business_id)->where('created_by', $staff->id)->sum('amount')
            : 0;

        return [
            'orders_created' => $ordersCreated,
            'deliveries_completed' => Delivery::where('business_id', $staff->business_id)->where('delivery_staff_id', $staff->id)->where('status', 'Delivered')->count(),
            'payments_collected' => $paymentsCollected,
            'last_login' => $staff->last_login_at,
            'last_activity' => $staff->last_activity_at,
        ];
    }

    private function audit(string $action, User $staff, ?array $oldValues = null, ?array $newValues = null): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'actor_id' => auth()->id(),
            'actor_role' => auth()->user()->role,
            'business_id' => $staff->business_id,
            'target_user_id' => $staff->id,
            'module' => 'Staff',
            'action' => $action,
            'record_id' => $staff->id,
            'description' => auth()->user()->name.' '.$action.' for '.$staff->name,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => app(\App\Services\AuditIpResolver::class)->capture(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1000),
        ]);
    }

    private function invalidateSessions(User $staff): void
    {
        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            DB::table('sessions')->where('user_id', $staff->id)->delete();
        }
    }

    private function businessId(): int
    {
        $user = auth()->user();
        $businessId = $user?->business_id ?: $user?->ownedBusiness()->value('id');

        if ($businessId && !$user->business_id) {
            $user->forceFill(['business_id' => $businessId])->save();
        }
        if (!$businessId) {
            throw ValidationException::withMessages(['business_id' => 'Your account is not linked with a business yet.']);
        }

        return (int) $businessId;
    }

    private function customRoleNames()
    {
        return StaffProfile::query()
            ->whereHas('user', fn ($query) => $query->where('business_id', $this->businessId())->where('role', 'custom_staff'))
            ->whereNotNull('custom_role_name')
            ->where('custom_role_name', '!=', '')
            ->distinct()
            ->orderBy('custom_role_name')
            ->pluck('custom_role_name');
    }
}
