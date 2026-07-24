<?php

namespace Tests\Feature;

use App\Services\CompanyPermissionService;
use Tests\TestCase;

class DashboardPermissionAliasTest extends TestCase
{
    public function test_retired_dashboard_card_aliases_resolve_to_canonical_cards(): void
    {
        $permissions = app(CompanyPermissionService::class);

        $this->assertSame(
            'dashboard.card_receivables',
            $permissions->normalise('dashboard.card_pending_customer_payments')
        );
        $this->assertSame(
            'dashboard.card_payables',
            $permissions->normalise('dashboard.card_pending_supplier_payments')
        );
    }
}
