<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use App\Notifications\ActionableBusinessNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The narrow policy boundary between an audit event and a bell notification.
 * New callers should publish only unresolved, actionable conditions here.
 */
class BusinessNotificationPolicy
{
    public function __construct(private readonly CompanyPermissionService $permissions) {}

    /** @param array<string, mixed> $details */
    public function handleBusinessActivity(Business $business, string $module, string $action, ?int $recordId, array $details = []): void
    {
        if (self::isActionableActivity($module, $action, $details) && $module === 'Deliveries' && $recordId) {
            $reason = trim((string) ($details['reason'] ?? ''));
            $this->publish(
                $this->recipients($business, 'deliveries.view'),
                'Delivery needs attention',
                'A delivery could not be completed.'.($reason !== '' ? ' Reason: '.$reason.'.' : ''),
                $business->id,
                'delivery',
                'critical',
                'delivery-failed:'.$business->id.':'.$recordId,
                ['related_type' => \App\Models\Delivery::class, 'related_id' => $recordId]
            );

            return;
        }

        if ($module === 'Deliveries' && in_array($action, ['Failed delivery reopened', 'Delivery completed', 'Delivery cancelled'], true) && $recordId) {
            $this->resolveForBusiness($business->id, 'delivery-failed:'.$business->id.':'.$recordId);

            return;
        }

        // Cashier activity remains audit-only unless a closed shift has a
        // real variance requiring the business owner to review it.
        if (self::isActionableActivity($module, $action, $details) && $module === 'POS' && $recordId) {
            $variance = (float) $details['variance'];
            $this->publish(
                $this->owners($business),
                'Register cash variance',
                'A register closed with a '.($variance < 0 ? 'cash shortage' : 'cash excess').' of Rs '.number_format(abs($variance), 2).'.',
                $business->id,
                'pos',
                'warning',
                'register-variance:'.$business->id.':'.$recordId,
                ['related_type' => \App\Models\PosRegister::class, 'related_id' => $recordId]
            );
        }
    }

    /**
     * Deliberately narrow: an audit record is not automatically a bell event.
     * This pure rule also protects future callers from reintroducing routine
     * sale, POS, view, print, or profile-update notification noise.
     *
     * @param array<string, mixed> $details
     */
    public static function isActionableActivity(string $module, string $action, array $details = []): bool
    {
        if ($module === 'Deliveries' && $action === 'Delivery marked failed') {
            return true;
        }

        return $module === 'POS'
            && $action === 'POS register closed'
            && abs((float) ($details['variance'] ?? 0)) > 0.0001;
    }

    /** @return Collection<int, User> */
    public function inventoryRecipients(Business $business): Collection
    {
        return $this->recipients($business, 'inventory.view');
    }

    /** @return Collection<int, User> */
    public function owners(Business $business): Collection
    {
        return User::query()
            ->where('business_id', $business->id)
            ->where('role', 'business_owner')
            ->whereIn('status', ['active', 'Active'])
            ->get();
    }

    /** @return Collection<int, User> */
    public function recipients(Business $business, string $modulePermission): Collection
    {
        return User::query()
            ->where('business_id', $business->id)
            ->whereIn('role', ['business_owner', 'custom_staff'])
            ->whereIn('status', ['active', 'Active'])
            ->get()
            ->filter(fn (User $user) => $user->role === 'business_owner'
                || ($this->permissions->allowsUser($user, 'notifications.view', $business)
                    && $this->permissions->allowsUser($user, $modulePermission, $business)))
            ->values();
    }

    /** @param iterable<User> $recipients @param array<string, mixed> $context */
    public function publish(iterable $recipients, string $title, string $message, int $businessId, string $category, string $priority, string $actionableKey, array $context = []): void
    {
        foreach ($recipients as $recipient) {
            DB::transaction(function () use ($recipient, $title, $message, $businessId, $category, $priority, $actionableKey, $context): void {
                $lockedRecipient = User::query()->lockForUpdate()->find($recipient->id);
                if (! $lockedRecipient || $lockedRecipient->unreadNotifications()->where('data->actionable_key', $actionableKey)->exists()) {
                    return;
                }

                $lockedRecipient->notify(new ActionableBusinessNotification(
                    $title,
                    $message,
                    $businessId,
                    $category,
                    $priority,
                    $actionableKey,
                    $context,
                ));
            });
        }
    }

    public function resolveForBusiness(int $businessId, string $actionableKey): void
    {
        User::query()->where('business_id', $businessId)->each(function (User $user) use ($actionableKey): void {
            $user->unreadNotifications()
                ->where('data->actionable_key', $actionableKey)
                ->update(['read_at' => now(config('app.timezone'))]);
        });
    }
}
