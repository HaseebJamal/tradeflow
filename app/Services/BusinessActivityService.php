<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;

class BusinessActivityService
{
    public function __construct(private readonly BusinessNotificationPolicy $notificationPolicy) {}

    /**
     * Persist one authoritative audit record after a successful business
     * transaction. A record of activity is not automatically a bell alert;
     * the narrow notification policy handles only unresolved conditions that
     * require attention.
     *
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function record(int $businessId, string $module, string $action, ?int $recordId = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        $business = Business::find($businessId);
        if (!$business) {
            return;
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'actor_id' => auth()->id(),
            'actor_role' => auth()->user()?->role,
            'business_id' => $business->id,
            'module' => $module,
            'action' => $action,
            'record_id' => $recordId,
            'description' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => app(AuditIpResolver::class)->capture(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1000),
        ]);

        $this->notificationPolicy->handleBusinessActivity($business, $module, $action, $recordId, $newValues ?? []);
    }
}
