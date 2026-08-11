<?php

namespace Tests\Unit;

use App\Models\RenewalInvoice;
use Tests\TestCase;

class RenewalInvoiceActionableStatusTest extends TestCase
{
    public function test_only_explicit_open_renewal_statuses_are_actionable_for_admin(): void
    {
        $actionable = RenewalInvoice::ADMIN_ACTIONABLE_STATUSES;

        $this->assertContains('Generated', $actionable);
        $this->assertContains('Pending Payment', $actionable);
        $this->assertContains('Overdue', $actionable);

        $this->assertNotContains('Sent', $actionable);
        $this->assertNotContains('Paid', $actionable);
        $this->assertNotContains('Cancelled', $actionable);
        $this->assertNotContains('Completed', $actionable);
        $this->assertNotContains('Verified', $actionable);
    }

    public function test_actionable_scope_uses_the_explicit_status_list(): void
    {
        $query = RenewalInvoice::query()->actionableForAdmin();

        $this->assertSame(RenewalInvoice::ADMIN_ACTIONABLE_STATUSES, $query->getBindings());
        $this->assertStringContainsString('where', strtolower($query->toSql()));
    }
}
