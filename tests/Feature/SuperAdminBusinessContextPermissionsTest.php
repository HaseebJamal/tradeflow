<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Services\CompanyPermissionService;
use App\Services\SubscriptionManagementAccessService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SuperAdminBusinessContextPermissionsTest extends TestCase
{
    public function test_super_admin_in_business_context_is_limited_by_company_permissions(): void
    {
        $business = new Business;
        $business->setRawAttributes(['id' => 10]);
        $superAdmin = new User([
            'role' => 'super_admin',
            // This is applied temporarily by SuperAdminBusinessContextMiddleware.
            'business_id' => $business->id,
        ]);
        $this->mockPermissionCache(['products.view' => false]);

        $permissions = app(CompanyPermissionService::class);

        $this->assertFalse($permissions->allowsUser($superAdmin, 'products.view', $business));
    }

    public function test_super_admin_in_business_context_can_use_an_enabled_company_permission(): void
    {
        $business = new Business;
        $business->setRawAttributes(['id' => 10]);
        $superAdmin = new User(['role' => 'super_admin', 'business_id' => $business->id]);
        $this->mockPermissionCache(['products.view' => true]);

        $this->assertTrue(app(CompanyPermissionService::class)->allowsUser($superAdmin, 'products.view', $business));
    }

    public function test_super_admin_keeps_unrestricted_platform_permission_checks_without_business_context(): void
    {
        $superAdmin = new User(['role' => 'super_admin']);

        $this->assertTrue(app(CompanyPermissionService::class)->allowsUser($superAdmin, 'products.view'));
    }

    public function test_staff_permissions_are_capped_by_company_access_without_an_owner_role_check(): void
    {
        $business = new Business;
        $business->setRawAttributes(['id' => 10]);
        $staff = new User([
            'role' => 'custom_staff',
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => ['staff.create', 'staff.edit', 'staff.permissions'],
        ]);

        $configured = [
                'staff.view' => true,
                'staff.create' => true,
                'staff.edit' => true,
                'staff.permissions' => true,
            ];
        $definitions = [
                'modules' => ['staff' => 'staff.view'],
                'permissions' => [
                    'staff.view' => 'staff.view',
                    'staff.create' => 'staff.create',
                    'staff.edit' => 'staff.edit',
                    'staff.permissions' => 'staff.permissions',
                ],
            ];
        Cache::shouldReceive('remember')->andReturnUsing(
            fn (string $key) => $key === 'tradeflow.company-permissions.10' ? $configured : $definitions,
        );

        $permissions = app(CompanyPermissionService::class);

        $this->assertTrue($permissions->allowsUser($staff, 'staff.view', $business));
        $this->assertTrue($permissions->allowsUser($staff, 'staff.create', $business));
        $this->assertTrue($permissions->allowsUser($staff, 'staff.edit', $business));
        $this->assertTrue($permissions->allowsUser($staff, 'staff.permissions', $business));
    }

    public function test_granted_module_action_allows_module_entry_but_not_other_actions(): void
    {
        $business = new Business;
        $business->setRawAttributes(['id' => 10]);
        $staff = new User([
            'role' => 'custom_staff',
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => ['products.create'],
        ]);

        $configured = [
            'products.view' => true,
            'products.create' => true,
            'products.edit' => true,
        ];
        $definitions = [
            'modules' => ['products' => 'products.view'],
            'permissions' => [
                'products.view' => 'products.view',
                'products.create' => 'products.create',
                'products.edit' => 'products.edit',
            ],
        ];
        Cache::shouldReceive('remember')->andReturnUsing(
            fn (string $key) => $key === 'tradeflow.company-permissions.10' ? $configured : $definitions,
        );

        $permissions = app(CompanyPermissionService::class);

        $this->assertTrue($permissions->allowsUser($staff, 'products.view', $business));
        $this->assertTrue($permissions->allowsUser($staff, 'products.create', $business));
        $this->assertFalse($permissions->allowsUser($staff, 'products.edit', $business));
    }

    public function test_customers_visibility_permission_is_resolved_consistently_for_owner_and_staff(): void
    {
        $business = new Business;
        $business->setRawAttributes(['id' => 10]);
        $this->mockCustomersPermissionCache();

        $owner = new User(['role' => 'business_owner', 'business_id' => $business->id, 'status' => 'active']);
        $this->assertTrue(app(CompanyPermissionService::class)->allowsUser($owner, 'customers.view', $business));
    }

    public function test_staff_requires_customers_view_for_customers_access(): void
    {
        $business = new Business;
        $business->setRawAttributes(['id' => 10]);
        $this->mockCustomersPermissionCache();

        $staffWithAccess = new User([
            'role' => 'custom_staff',
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => ['customers.view'],
        ]);
        $staffWithoutAccess = new User([
            'role' => 'custom_staff',
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => ['customers.create'],
        ]);

        $permissions = app(CompanyPermissionService::class);
        $this->assertTrue($permissions->allowsUser($staffWithAccess, 'customers.view', $business));
        $this->assertFalse($permissions->allowsUser($staffWithoutAccess, 'customers.view', $business));
        $this->assertFalse($permissions->allowsUser($staffWithoutAccess, 'customers.edit', $business));
    }

    public function test_legacy_customer_aliases_normalise_to_canonical_keys(): void
    {
        $permissions = app(CompanyPermissionService::class);

        $this->assertSame('customers.view', $permissions->normalise('view_customers'));
        $this->assertSame('customers.view', $permissions->normalise('customers.index'));
        $this->assertSame('customers.create', $permissions->normalise('create_customer'));
    }

    public function test_delivery_actions_automatically_include_the_delivery_workspace_permission(): void
    {
        $permissions = app(CompanyPermissionService::class);

        $this->assertSame(
            ['deliveries.update_status', 'deliveries.view'],
            $permissions->withRequiredPermissions(['deliveries.update_status'])
        );
    }

    public function test_purchase_capabilities_automatically_include_supplier_lookup_access(): void
    {
        $permissions = app(CompanyPermissionService::class);

        $this->assertSame(
            ['purchases.create', 'suppliers.view'],
            $permissions->withRequiredPermissions(['purchases.create'])
        );
    }

    public function test_legacy_delivery_action_assignment_keeps_staff_access_scoped_to_delivery_view(): void
    {
        $business = new Business;
        $business->setRawAttributes(['id' => 10]);
        $staff = new User([
            'role' => 'custom_staff',
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => ['deliveries.update_status'],
        ]);
        $configured = ['deliveries.update_status' => true];
        $definitions = [
            'modules' => ['deliveries' => 'deliveries.view'],
            'permissions' => [
                'deliveries.view' => 'deliveries.view',
                'deliveries.update_status' => 'deliveries.update_status',
            ],
        ];
        Cache::shouldReceive('remember')->andReturnUsing(
            fn (string $key) => $key === 'tradeflow.company-permissions.10' ? $configured : $definitions,
        );

        $permissions = app(CompanyPermissionService::class);

        $this->assertTrue($permissions->allowsUser($staff, 'deliveries.view', $business));
        $this->assertTrue($permissions->allowsUser($staff, 'deliveries.update_status', $business));
        $this->assertFalse($permissions->allowsUser($staff, 'deliveries.edit', $business));
    }

    public function test_subscription_summary_requires_an_owner_or_explicit_subscription_management_action(): void
    {
        $business = new Business;
        $business->setRawAttributes(['id' => 10]);
        $this->mockSubscriptionPermissionCache();

        $owner = new User(['role' => 'business_owner', 'business_id' => $business->id, 'status' => 'active']);
        $viewOnlyStaff = new User([
            'role' => 'custom_staff',
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => ['subscriptions.view'],
        ]);
        $manager = new User([
            'role' => 'custom_staff',
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => ['subscriptions.view', 'subscriptions.upgrade'],
        ]);
        $previewingSuperAdmin = new User([
            'role' => 'super_admin',
            'business_id' => $business->id,
            'status' => 'active',
        ]);

        $access = app(SubscriptionManagementAccessService::class);

        $this->assertTrue($access->canManage($owner, $business));
        $this->assertFalse($access->canManage($viewOnlyStaff, $business));
        $this->assertTrue($access->canManage($manager, $business));
        $this->assertTrue($access->canManage($previewingSuperAdmin, $business));
    }

    public function test_email_change_approval_permission_is_scoped_to_the_staff_module(): void
    {
        $business = new Business;
        $business->setRawAttributes(['id' => 10]);
        $staffAdmin = new User([
            'role' => 'custom_staff',
            'business_id' => $business->id,
            'status' => 'active',
            'permissions' => ['users.approve_email_change'],
        ]);
        $configured = [
            'staff.view' => true,
            'users.approve_email_change' => true,
        ];
        $definitions = [
            'modules' => ['staff' => 'staff.view'],
            'permissions' => [
                'staff.view' => 'staff.view',
                'users.approve_email_change' => 'users.approve_email_change',
            ],
            'permission_modules' => [
                'staff.view' => 'staff',
                'users.approve_email_change' => 'staff',
            ],
        ];
        Cache::shouldReceive('remember')->andReturnUsing(
            fn (string $key) => $key === 'tradeflow.company-permissions.10' ? $configured : $definitions,
        );

        $this->assertTrue(
            app(CompanyPermissionService::class)->allowsUser($staffAdmin, 'users.approve_email_change', $business)
        );
    }

    private function mockPermissionCache(array $configuredPermissions): void
    {
        Cache::shouldReceive('remember')->twice()->andReturn(
            $configuredPermissions,
            [
                'modules' => ['products' => 'products.view'],
                'permissions' => ['products.view' => 'products.view'],
            ],
        );
    }

    private function mockCustomersPermissionCache(): void
    {
        $configured = [
                'customers.view' => true,
                'customers.create' => true,
                'customers.edit' => true,
                'customers.archive' => true,
                'customers.restore' => true,
            ];
        $definitions = [
                'modules' => ['customers' => 'customers.view'],
                'permissions' => [
                    'customers.view' => 'customers.view',
                    'customers.create' => 'customers.create',
                    'customers.edit' => 'customers.edit',
                    'customers.archive' => 'customers.archive',
                    'customers.restore' => 'customers.restore',
                ],
            ];

        Cache::shouldReceive('remember')->andReturnUsing(
            fn (string $key) => $key === 'tradeflow.company-permissions.10' ? $configured : $definitions,
        );
    }

    private function mockSubscriptionPermissionCache(): void
    {
        $configured = [
            'subscriptions.view' => true,
            'subscriptions.request' => true,
            'subscriptions.upgrade' => true,
            'subscriptions.downgrade' => true,
            'subscriptions.renew' => true,
            'subscriptions.cancel' => true,
        ];
        $definitions = [
            'modules' => ['subscriptions' => 'subscriptions.view'],
            'permissions' => array_combine(array_keys($configured), array_keys($configured)),
        ];

        Cache::shouldReceive('remember')->andReturnUsing(
            fn (string $key) => $key === 'tradeflow.company-permissions.10' ? $configured : $definitions,
        );
    }
}
