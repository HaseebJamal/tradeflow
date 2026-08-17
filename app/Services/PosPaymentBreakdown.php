<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * Builds the authoritative tender breakdown for a POS sale. Amounts are kept
 * as whole rupees because the existing POS cash flow and request validation
 * use whole-number money.
 */
class PosPaymentBreakdown
{
    public const SINGLE_METHODS = ['Cash', 'Credit', 'Card', 'Bank Transfer', 'Jazz Cash', 'Easypaisa', 'Cheque', 'Other'];

    public const SPLIT_METHODS = ['Cash', 'Card', 'Bank Transfer', 'Jazz Cash', 'Easypaisa', 'Cheque', 'Other'];

    /** @return array{type:string,method:string,lines:array<int,array{method:string,amount:int,reference:?string}>,paid:int,balance:int,cash_received:?int,change:int} */
    public function calculate(array $data, int $grandTotal): array
    {
        $type = (string) ($data['payment_type'] ?? 'Cash');

        if ($type === 'Split') {
            return $this->split($data['split_payments'] ?? [], $grandTotal);
        }

        if (! in_array($type, self::SINGLE_METHODS, true)) {
            throw ValidationException::withMessages(['payment_type' => 'Select a supported payment method.']);
        }

        if ((string) ($data['payment_method'] ?? '') !== $type) {
            throw ValidationException::withMessages(['payment_method' => 'The selected payment method does not match the payment type.']);
        }

        if ($type === 'Credit') {
            return $this->result('Credit', 'Credit', [], 0, $grandTotal, null, 0);
        }

        $received = $this->whole($data['cash_received'] ?? 0);
        if ($type === 'Cash') {
            if ($received < $grandTotal) {
                throw ValidationException::withMessages(['cash_received' => 'Insufficient cash received. Required amount is Rs '.$grandTotal.'.']);
            }

            return $this->result('Cash', 'Cash', [[
                'method' => 'Cash',
                'amount' => $grandTotal,
                'reference' => $this->reference($data['reference'] ?? null),
            ]], $grandTotal, 0, $received, $received - $grandTotal);
        }

        if ($received < 1) {
            throw ValidationException::withMessages(['cash_received' => 'Enter the received amount for this payment type.']);
        }
        if ($received > $grandTotal) {
            throw ValidationException::withMessages(['cash_received' => 'Payment amount cannot exceed the amount due for this payment method.']);
        }

        return $this->result($type, $type, [[
            'method' => $type,
            'amount' => $received,
            'reference' => $this->reference($data['reference'] ?? null),
        ]], $received, $grandTotal - $received, null, 0);
    }

    /** @param array<int, array<string, mixed>> $payments */
    private function split(array $payments, int $grandTotal): array
    {
        if ($payments === []) {
            throw ValidationException::withMessages(['split_payments' => 'Add at least one payment method.']);
        }

        $seen = [];
        $cashTendered = 0;
        $nonCashTotal = 0;
        $prepared = [];
        foreach ($payments as $index => $payment) {
            $method = (string) ($payment['method'] ?? '');
            $amount = $this->whole($payment['amount'] ?? 0);
            if (! in_array($method, self::SPLIT_METHODS, true)) {
                throw ValidationException::withMessages(["split_payments.{$index}.method" => 'Select a supported payment method.']);
            }
            if (isset($seen[$method])) {
                throw ValidationException::withMessages(['split_payments' => 'Use each payment method only once.']);
            }
            if ($amount < 1) {
                throw ValidationException::withMessages(["split_payments.{$index}.amount" => 'Each payment amount must be greater than Rs 0.']);
            }

            $seen[$method] = true;
            $prepared[] = ['method' => $method, 'amount' => $amount, 'reference' => $this->reference($payment['reference'] ?? null)];
            if ($method === 'Cash') {
                $cashTendered += $amount;
            } else {
                $nonCashTotal += $amount;
            }
        }

        if ($nonCashTotal > $grandTotal) {
            throw ValidationException::withMessages(['split_payments' => 'Non-cash payment amounts cannot exceed the amount due.']);
        }

        $cashApplied = min($cashTendered, max(0, $grandTotal - $nonCashTotal));
        $change = max(0, $cashTendered - $cashApplied);
        $lines = collect($prepared)
            ->map(function (array $payment) use ($cashApplied): array {
                if ($payment['method'] === 'Cash') {
                    $payment['amount'] = $cashApplied;
                }

                return $payment;
            })
            ->filter(fn (array $payment) => $payment['amount'] > 0)
            ->values()
            ->all();
        $paid = min($grandTotal, $nonCashTotal + $cashApplied);

        return $this->result('Split', 'Split', $lines, $paid, $grandTotal - $paid, $cashTendered > 0 ? $cashTendered : null, $change);
    }

    /** @param array<int, array{method:string,amount:int,reference:?string}> $lines */
    private function result(string $type, string $method, array $lines, int $paid, int $balance, ?int $cashReceived, int $change): array
    {
        return compact('type', 'method', 'lines', 'paid', 'balance', 'change') + [
            'cash_received' => $cashReceived,
        ];
    }

    private function whole(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
            return 0;
        }

        return (int) $value;
    }

    private function reference(mixed $value): ?string
    {
        $reference = trim((string) $value);

        return $reference !== '' ? $reference : null;
    }
}
