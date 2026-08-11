<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PlatformPayment;
use App\Models\RenewalInvoice;
use App\Models\SupportTicket;
use App\Models\User;

/**
 * Supplies the Super Admin sidebar with module-specific actionable counts.
 *
 * These are deliberately separate from the global notification bell. Each
 * count is based on the underlying work item so historical notifications and
 * completed records never inflate a sidebar badge.
 */
class SuperAdminSidebarBadgeService
{
    /**
     * @return array{support: int, payments: int, trial_access: int, trial_access_critical: bool}
     */
    public function forUser(?User $user): array
    {
        $empty = [
            'support' => 0,
            'payments' => 0,
            'trial_access' => 0,
            'trial_access_critical' => false,
        ];

        // This component is only rendered in the Super Admin shell. Keep the
        // guard here as well so a reused component can never disclose global
        // operational counts to another account type.
        if (! $user || $user->role !== 'super_admin') {
            return $empty;
        }

        $accessAttention = $this->accessAttention();

        return [
            // There is no separate read flag on support tickets today. An
            // unresolved ticket is therefore the real outstanding support
            // work; resolving or closing it immediately removes it.
            'support' => SupportTicket::query()
                ->whereNotIn('status', ['Resolved', 'Closed'])
                ->count(),
            // Pending payment verification and actionable renewal invoices
            // are separate records, so both need Super Admin attention.
            'payments' => PlatformPayment::query()
                ->where('status', 'Pending')
                ->count()
                + RenewalInvoice::query()
                    ->whereIn('status', ['Generated', 'Sent', 'Pending Payment', 'Overdue'])
                    ->count(),
            'trial_access' => $accessAttention['count'],
            'trial_access_critical' => $accessAttention['has_critical'],
        ];
    }

    /**
     * Count each affected business once using the same lifecycle state that
     * powers Trial & Access. Chunking keeps this bounded without N+1 queries.
     *
     * @return array{count: int, has_critical: bool}
     */
    private function accessAttention(): array
    {
        $lifecycle = app(SubscriptionLifecycleService::class);
        $count = 0;
        $hasCritical = false;

        $lifecycle->attentionCandidateBusinesses()
            ->select('id')
            ->with(['subscription:id,business_id,status,payment_status,starts_at,ends_at,trial_start_at,trial_end_at'])
            ->orderBy('id')
            ->chunkById(200, function ($businesses) use ($lifecycle, &$count, &$hasCritical): void {
                foreach ($businesses as $business) {
                    $state = $lifecycle->state($business->subscription);
                    $isRestricted = in_array($state['status'], ['Pending', 'Suspended', 'Cancelled'], true)
                        || ! $state['subscription'];
                    $isCritical = $state['is_expired'] || $isRestricted;

                    if (! $state['is_expiring_soon'] && ! $isCritical) {
                        continue;
                    }

                    $count++;
                    $hasCritical = $hasCritical || $isCritical;
                }
            });

        return ['count' => $count, 'has_critical' => $hasCritical];
    }
}
