<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use App\Notifications\BusinessActivityNotification;

class BusinessActivityService
{
    /**
     * Persist one authoritative audit record after a successful business
     * transaction, then notify active Super Admins about that same record.
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

        User::query()->where('role', 'super_admin')->where('status', 'active')
            ->each(fn (User $admin) => $admin->notify(new BusinessActivityNotification($business, $module, $action, $recordId, $newValues ?? [])));
    }
}
