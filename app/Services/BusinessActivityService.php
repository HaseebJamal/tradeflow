<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use App\Notifications\BusinessActivityNotification;
use App\Services\CompanyPermissionService;

class BusinessActivityService
{
    /**
     * Persist one authoritative audit record after a successful business
     * transaction, then notify only users inside that business who have
     * notification access. Platform administrators receive their own platform
     * alerts through the dedicated platform notification workflows.
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
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 1000),
        ]);

        $permissions = app(CompanyPermissionService::class);
        User::query()
            ->where('business_id', $business->id)
            ->whereIn('role', ['business_owner', 'custom_staff'])
            ->where('status', 'active')
            ->get()
            ->filter(fn (User $recipient) => $permissions->allowsUser($recipient, 'notifications.view', $business))
            ->each(fn (User $recipient) => $recipient->notify(new BusinessActivityNotification($business, $module, $action, $recordId, $newValues ?? [])));
    }
}
