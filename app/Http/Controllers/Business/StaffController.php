<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    private const STAFF_ROLES = [
        'manager' => 'Manager',
        'sales_staff' => 'Sales Staff',
        'inventory_staff' => 'Inventory Staff',
        'accountant' => 'Accountant',
        'delivery_staff' => 'Delivery Staff',
    ];

    private const PERMISSION_GROUPS = [
        'products' => [
            'products.view' => 'View Products',
            'products.add' => 'Add Products',
            'products.edit' => 'Edit Products',
            'products.delete' => 'Delete Products',
        ],
        'inventory' => [
            'inventory.view' => 'View Stock',
            'inventory.add' => 'Add Stock',
            'inventory.adjust' => 'Adjust Stock',
        ],
        'customers' => [
            'customers.view' => 'View Customers',
            'customers.add' => 'Add Customers',
            'customers.edit' => 'Edit Customers',
        ],
        'orders' => [
            'orders.view' => 'View Orders',
            'orders.create' => 'Create Orders',
            'orders.status' => 'Update Order Status',
            'orders.cancel' => 'Cancel Orders',
        ],
        'payments' => [
            'payments.view' => 'View Payments',
            'payments.record' => 'Record Payments',
        ],
        'khata' => [
            'khata.view' => 'View Khata',
            'khata.add' => 'Add Ledger Entry',
        ],
        'deliveries' => [
            'deliveries.view' => 'View Deliveries',
            'deliveries.update' => 'Update Delivery Status',
        ],
        'invoices' => [
            'invoices.view' => 'View Invoice',
            'invoices.print' => 'Print Invoice',
        ],
        'expenses' => [
            'expenses.view' => 'View Expenses',
            'expenses.add' => 'Add Expenses',
        ],
        'reports' => [
            'reports.view' => 'View Reports',
            'reports.export' => 'Export Reports',
        ],
        'staff' => [
            'staff.view' => 'View Staff',
            'staff.create' => 'Create Staff',
            'staff.edit' => 'Edit Staff',
        ],
        'settings' => [
            'settings.manage' => 'Manage Settings',
        ],
    ];

    private const ROLE_DEFAULTS = [
        'manager' => [
            'products.view', 'products.add', 'products.edit',
            'inventory.view', 'inventory.add', 'inventory.adjust',
            'customers.view', 'customers.add', 'customers.edit',
            'orders.view', 'orders.create', 'orders.status', 'orders.cancel',
            'payments.view', 'payments.record',
            'reports.view', 'reports.export',
        ],
        'sales_staff' => [
            'products.view',
            'customers.view', 'customers.add', 'customers.edit',
            'orders.view', 'orders.create',
            'payments.view',
        ],
        'inventory_staff' => [
            'products.view', 'products.add', 'products.edit',
            'inventory.view', 'inventory.add', 'inventory.adjust',
        ],
        'accountant' => [
            'payments.view', 'payments.record',
            'khata.view', 'khata.add',
            'expenses.view', 'expenses.add',
            'invoices.view', 'invoices.print',
            'reports.view', 'reports.export',
        ],
        'delivery_staff' => [
            'deliveries.view', 'deliveries.update',
            'payments.view',
        ],
    ];

    public function index(Request $request)
    {
        $query = $this->staffQuery()->with('staffProfile');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhereHas('staffProfile', fn ($profile) => $profile->where('employee_id', 'like', "%{$search}%"))
            );
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return view('business.staff.index', [
            'staff' => $query->latest()->paginate(15)->withQueryString(),
            'stats' => $this->stats(),
            'roles' => self::STAFF_ROLES,
            'permissionGroups' => self::PERMISSION_GROUPS,
            'roleDefaults' => self::ROLE_DEFAULTS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $userData = $this->userData($request, $data);
        $userData['password'] = Hash::make($data['password']);
        $userData['business_id'] = $this->businessId();
        $userData['status'] = 'active';
        $userData['permissions'] = $this->normalizePermissions($data['permissions'] ?? [], $data['role']);

        if ($request->hasFile('profile_image')) {
            $userData['profile_image'] = $request->file('profile_image')->store('profile_images', 'public');
        }

        $staff = User::create($userData);
        $staff->staffProfile()->create($this->profileData($data));

        return back()->with('success', 'Staff account created.');
    }

    public function show(User $staff)
    {
        $staff = $this->scopedStaff($staff)->load('staffProfile');

        return view('business.staff.show', [
            'staff' => $staff,
            'permissionGroups' => self::PERMISSION_GROUPS,
            'activity' => $this->activity($staff),
        ]);
    }

    public function edit(User $staff)
    {
        return view('business.staff.edit', [
            'staff' => $this->scopedStaff($staff)->load('staffProfile'),
            'roles' => self::STAFF_ROLES,
            'permissionGroups' => self::PERMISSION_GROUPS,
            'roleDefaults' => self::ROLE_DEFAULTS,
        ]);
    }

    public function update(Request $request, User $staff)
    {
        $staff = $this->scopedStaff($staff);
        $data = $this->validated($request, $staff);
        $userData = $this->userData($request, $data);
        $userData['permissions'] = $this->normalizePermissions($data['permissions'] ?? [], $data['role']);

        if (!empty($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }

        if ($request->hasFile('profile_image')) {
            if ($staff->profile_image && Storage::disk('public')->exists($staff->profile_image)) {
                Storage::disk('public')->delete($staff->profile_image);
            }
            $userData['profile_image'] = $request->file('profile_image')->store('profile_images', 'public');
        }

        $staff->update($userData);
        $staff->staffProfile()->updateOrCreate(['user_id' => $staff->id], $this->profileData($data));

        return redirect()->route('business.staff.show', $staff)->with('success', 'Staff account updated.');
    }

    public function updateStatus(Request $request, User $staff)
    {
        $staff = $this->scopedStaff($staff);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'inactive', 'suspended'])]]);
        $staff->update(['status' => $data['status']]);

        return back()->with('success', 'Staff status updated.');
    }

    public function destroy(User $staff)
    {
        $staff = $this->scopedStaff($staff);

        if ($staff->profile_image && Storage::disk('public')->exists($staff->profile_image)) {
            Storage::disk('public')->delete($staff->profile_image);
        }

        $staff->delete();

        return redirect()->route('business.staff')->with('success', 'Staff account deleted.');
    }

    private function validated(Request $request, ?User $staff = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($staff?->id)],
            'cnic' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'employee_id' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::in(array_keys(self::STAFF_ROLES))],
            'employment_type' => ['required', Rule::in(['Full Time', 'Part Time', 'Temporary'])],
            'joining_date' => ['nullable', 'date'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'status' => [$staff ? 'required' : 'nullable', Rule::in(['active', 'inactive', 'suspended'])],
            'password' => [$staff ? 'nullable' : 'required', 'confirmed', 'min:8'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in($this->allowedPermissionValues())],
        ]);
    }

    private function userData(Request $request, array $data): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'status' => $data['status'] ?? 'active',
        ];
    }

    private function profileData(array $data): array
    {
        return [
            'employee_id' => $data['employee_id'] ?? null,
            'father_name' => $data['father_name'] ?? null,
            'cnic' => $data['cnic'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'employment_type' => $data['employment_type'],
            'joining_date' => $data['joining_date'] ?? null,
            'salary' => $data['salary'] ?? null,
        ];
    }

    private function normalizePermissions(array $selected, string $role): array
    {
        $selected = $selected ?: (self::ROLE_DEFAULTS[$role] ?? []);
        $modules = [];

        foreach ($selected as $permission) {
            $permission = strtolower($permission);
            $module = str_contains($permission, '.') ? str($permission)->before('.')->toString() : $permission;
            if (array_key_exists($module, self::PERMISSION_GROUPS)) {
                $modules[] = $module;
            }
        }

        return array_values(array_unique([...$modules, ...$selected]));
    }

    private function allowedPermissionValues(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(self::PERMISSION_GROUPS),
            ...array_values(array_map('array_keys', self::PERMISSION_GROUPS))
        )));
    }

    private function staffQuery()
    {
        return User::query()
            ->where('business_id', $this->businessId())
            ->whereIn('role', array_keys(self::STAFF_ROLES));
    }

    private function scopedStaff(User $staff): User
    {
        abort_unless(
            $staff->business_id === $this->businessId() && !in_array($staff->role, ['business_owner', 'super_admin'], true),
            404
        );

        return $staff;
    }

    private function stats(): array
    {
        $base = $this->staffQuery();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'inactive' => (clone $base)->whereIn('status', ['inactive', 'suspended'])->count(),
            'managers' => (clone $base)->where('role', 'manager')->count(),
            'sales' => (clone $base)->where('role', 'sales_staff')->count(),
            'delivery' => (clone $base)->where('role', 'delivery_staff')->count(),
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
        ];
    }

    private function businessId(): int
    {
        $user = auth()->user();
        $businessId = $user?->business_id ?: $user?->ownedBusiness()->value('id');

        if ($businessId && !$user->business_id) {
            $user->forceFill(['business_id' => $businessId])->save();
        }

        if (!$businessId) {
            throw ValidationException::withMessages([
                'business_id' => 'Your account is not linked with a business yet.',
            ]);
        }

        return (int) $businessId;
    }
}
