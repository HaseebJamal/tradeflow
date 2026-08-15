<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseRefundSettlement;

/** Derives receipt recovery status only from recorded GRNs and settlements. */
class PurchaseRefundStatusService
{
    /** @return array{recoverable_amount:float,credited_amount:float,refunded_amount:float,remaining_amount:float,status:?string} */
    public function summary(Purchase $purchase, array $financialSummary): array
    {
        $recoverable = $this->money($financialSummary['receipt_adjustments'] ?? 0);
        if ($recoverable <= 0) {
            return ['recoverable_amount' => 0.0, 'credited_amount' => 0.0, 'refunded_amount' => 0.0, 'remaining_amount' => 0.0, 'status' => null];
        }

        // A GRN credit applied to an unpaid balance is already a recorded AP
        // adjustment. Only the part paid above the adjusted liability remains
        // recoverable from the supplier as cash/bank/other settlement.
        $refundDue = max(0, $this->money(($financialSummary['paid_amount'] ?? 0) - ($financialSummary['net_liability'] ?? 0)));
        $credited = min($recoverable, max(0, $this->money($recoverable - $refundDue)));
        $refunded = min($refundDue, $this->money(PurchaseRefundSettlement::query()
            ->where('business_id', $purchase->business_id)
            ->where('purchase_id', $purchase->id)
            ->sum('amount')));
        $remaining = max(0, $this->money($recoverable - $credited - $refunded));

        return [
            'recoverable_amount' => $recoverable,
            'credited_amount' => $credited,
            'refunded_amount' => $refunded,
            'remaining_amount' => $remaining,
            'status' => $remaining <= 0.009
                ? 'Refunded / Fully Adjusted'
                : (($credited > 0 || $refunded > 0) ? 'Partially Refunded' : 'Refund / Credit Pending'),
        ];
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
