<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\BusinessActivityNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosDeliveryAssignmentService
{
    public function __construct(
        private CompanyPermissionService $permissions,
        private BusinessActivityService $activity,
    ) {}

    /** @return Collection<int, User> */
    public function eligibleStaff(User $actor): Collection
    {
        return User::query()
            ->where('business_id', $actor->business_id)
            ->where('role', 'custom_staff')
            ->where('status', 'active')
            ->with('staffProfile')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $staff) => $this->isDeliveryStaff($staff)
                && ($this->permissions->allowsUser($staff, 'deliveries.view')
                    || $this->permissions->allowsUser($staff, 'deliveries.update_status')
                    || $this->permissions->allowsUser($staff, 'deliveries.upload_proof')))
            ->values();
    }

    public function assign(User $actor, int $invoiceId, array $data): Delivery
    {
        if (! $this->permissions->allowsUser($actor, 'deliveries.assign')) {
            throw ValidationException::withMessages(['permission' => 'You do not have permission to assign deliveries.']);
        }

        $delivery = DB::transaction(function () use ($actor, $invoiceId, $data): Delivery {
            $invoice = Invoice::query()
                ->with(['order.items', 'customer'])
                ->where('business_id', $actor->business_id)
                ->lockForUpdate()
                ->find($invoiceId);

            if (! $invoice || ! $invoice->order || $invoice->order->sale_channel !== 'pos') {
                throw ValidationException::withMessages(['invoice' => 'This POS invoice is not available for delivery assignment.']);
            }
            if (in_array($invoice->status, ['Cancelled', 'Void'], true) || in_array($invoice->order->status, ['Cancelled', 'Void'], true)) {
                throw ValidationException::withMessages(['invoice' => 'Cancelled invoices cannot be assigned for delivery.']);
            }
            if ($invoice->order->status === 'Returned') {
                throw ValidationException::withMessages(['invoice' => 'This invoice has been fully returned and cannot be assigned for delivery.']);
            }
            if (! $invoice->order->delivery_required) {
                throw ValidationException::withMessages(['invoice' => 'This POS sale was not marked as requiring delivery at checkout.']);
            }

            $existingDelivery = Delivery::where('business_id', $actor->business_id)
                ->where(function ($delivery) use ($invoice) {
                    $delivery->where('invoice_id', $invoice->id)
                        ->orWhere('order_id', $invoice->order_id);
                })
                ->lockForUpdate()
                ->first();

            $staff = $this->eligibleStaff($actor)->firstWhere('id', (int) $data['delivery_staff_id']);
            if (! $staff) {
                throw ValidationException::withMessages(['delivery_staff_id' => 'Select an active delivery staff member from this business.']);
            }

            // Delivery-required POS sales already have one Pending queue
            // record. Assign that record instead of creating a duplicate.
            if ($existingDelivery) {
                if ($existingDelivery->delivery_staff_id || $existingDelivery->status !== 'Pending') {
                    throw ValidationException::withMessages(['invoice' => 'Delivery has already been assigned for this invoice.']);
                }

                $existingDelivery->update([
                    'delivery_staff_id' => $staff->id,
                    'address' => $data['address'],
                    'note' => $data['note'] ?? $existingDelivery->note,
                    'status' => 'Assigned',
                    'assigned_at' => now(),
                ]);

                return $existingDelivery;
            }

            $delivery = Delivery::create([
                'business_id' => $invoice->business_id,
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'delivery_staff_id' => $staff->id,
                'address' => $data['address'],
                'amount' => $invoice->balance > 0 ? $invoice->balance : $invoice->grand_total,
                'payment_status' => $invoice->payment_status,
                'status' => 'Assigned',
                'assigned_at' => now(),
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ]);

            return $delivery;
        });

        $assignedStaffName = User::whereKey($delivery->delivery_staff_id)->value('name') ?? 'delivery staff';
        $details = [
            'invoice_number' => $delivery->invoice?->invoice_number,
            'delivery_staff_id' => $delivery->delivery_staff_id,
            'notification_title' => 'Delivery Assigned',
            'notification_message' => 'Invoice '.($delivery->invoice?->invoice_number ?? '#'.$delivery->invoice_id).' assigned to '.$assignedStaffName.' for delivery.',
        ];
        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery Assigned', $delivery->id, null, $details);

        // BusinessActivityService notifies users with Notifications access.
        // Delivery staff and the owner still receive this assignment alert when
        // their role deliberately has no Notifications module permission.
        $business = Business::find($delivery->business_id);
        $recipients = User::query()
            ->where('business_id', $delivery->business_id)
            ->whereIn('id', array_filter([$delivery->delivery_staff_id, $business?->owner_id]))
            ->where('status', 'active')
            ->get();
        foreach ($recipients as $recipient) {
            if (!$this->permissions->allowsUser($recipient, 'notifications.view', $business)) {
                $recipient->notify(new BusinessActivityNotification($business, 'Deliveries', 'Delivery Assigned', $delivery->id, $details));
            }
        }

        return $delivery;
    }

    private function isDeliveryStaff(User $staff): bool
    {
        $roleName = (string) ($staff->staffProfile?->custom_role_name ?? '');

        return preg_replace('/[\s_-]+/', '', strtolower($roleName)) === 'deliverystaff';
    }
}
