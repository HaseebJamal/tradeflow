<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Services\CompanyPermissionService;
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
}
