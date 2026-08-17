<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BalanceAdjustment;
use App\Models\Customer;
use App\Models\JournalEntryLine;
use App\Models\KhataLedger;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Posts correction-only balance changes. Payments and returns must keep using
 * their own services: this service always leaves an immutable adjustment trail.
 */
class BalanceAdjustmentService
{
    public const CUSTOMER_TYPES = ['increase_receivable', 'decrease_receivable'];
    public const SUPPLIER_TYPES = ['increase_payable', 'decrease_payable'];
    public const REASONS = ['Opening Balance Correction', 'Reconciliation', 'Data Migration Correction', 'Write-Off', 'Manual Credit', 'Manual Debit', 'Other'];

    public function __construct(
        private readonly AccountingService $accounting,
        private readonly DocumentNumberService $numbers,
        private readonly BusinessActivityService $activity,
    ) {}

    /** @param array{adjustment_type:string,amount:numeric,reason:string,external_reference?:?string,notes?:?string,submission_token:string} $data */
    public function postCustomer(Customer $customer, User $actor, array $data): BalanceAdjustment
    {
        return DB::transaction(function () use ($customer, $actor, $data): BalanceAdjustment {
            $locked = Customer::query()->where('business_id', $customer->business_id)->lockForUpdate()->findOrFail($customer->id);
            return $this->post($locked, $actor, $data, false);
        });
    }

    /** @param array{adjustment_type:string,amount:numeric,reason:string,external_reference?:?string,notes?:?string,submission_token:string} $data */
    public function postSupplier(Supplier $supplier, User $actor, array $data): BalanceAdjustment
    {
        return DB::transaction(function () use ($supplier, $actor, $data): BalanceAdjustment {
            $locked = Supplier::query()->where('business_id', $supplier->business_id)->lockForUpdate()->findOrFail($supplier->id);
            return $this->post($locked, $actor, $data, false);
        });
    }

    public function reverse(BalanceAdjustment $adjustment, User $actor, string $submissionToken): BalanceAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor, $submissionToken): BalanceAdjustment {
            $original = BalanceAdjustment::query()->where('business_id', $adjustment->business_id)->lockForUpdate()->findOrFail($adjustment->id);
            if ($existing = BalanceAdjustment::where('business_id', $original->business_id)->where('submission_token', $submissionToken)->first()) return $existing;
            if ($original->reversed_at || $original->reversal()->exists()) throw ValidationException::withMessages(['adjustment' => 'This adjustment has already been reversed.']);

            $opposite = match ($original->adjustment_type) {
                'increase_receivable' => 'decrease_receivable', 'decrease_receivable' => 'increase_receivable',
                'increase_payable' => 'decrease_payable', 'decrease_payable' => 'increase_payable',
                default => throw ValidationException::withMessages(['adjustment' => 'This adjustment cannot be reversed.']),
            };
            $data = [
                'adjustment_type' => $opposite,
                'amount' => (float) $original->amount,
                'reason' => 'Reversal',
                'external_reference' => $original->reference,
                'notes' => 'Reversal of '.$original->reference,
                'submission_token' => $submissionToken,
            ];

            $party = $original->party_type === 'customer'
                ? Customer::query()->where('business_id', $original->business_id)->lockForUpdate()->findOrFail($original->party_id)
                : Supplier::query()->where('business_id', $original->business_id)->lockForUpdate()->findOrFail($original->party_id);
            $reversal = $this->post($party, $actor, $data, true, $original);
            $original->update(['reversed_at' => now()]);
            return $reversal;
        });
    }

    /** @return array{adjustment:float,new_balance:float} */
    public static function preview(float $current, string $type, float $amount): array
    {
        $amount = round($amount, 2);
        $increase = in_array($type, ['increase_receivable', 'increase_payable'], true);
        $new = round($current + ($increase ? $amount : -$amount), 2);
        if ($amount <= 0 || $new < -0.004) throw ValidationException::withMessages(['amount' => 'Adjustment amount must produce a non-negative balance.']);
        return ['adjustment' => $increase ? $amount : -$amount, 'new_balance' => max(0, $new)];
    }

    /** @param Customer|Supplier $party @param array<string,mixed> $data */
    private function post(Customer|Supplier $party, User $actor, array $data, bool $isReversal, ?BalanceAdjustment $reverses = null): BalanceAdjustment
    {
        $isCustomer = $party instanceof Customer;
        $allowedTypes = $isCustomer ? self::CUSTOMER_TYPES : self::SUPPLIER_TYPES;
        $type = (string) ($data['adjustment_type'] ?? '');
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $reason = trim((string) ($data['reason'] ?? ''));
        $token = (string) ($data['submission_token'] ?? '');
        if (! in_array($type, $allowedTypes, true)) throw ValidationException::withMessages(['adjustment_type' => 'Choose a valid balance adjustment type.']);
        if ($amount <= 0) throw ValidationException::withMessages(['amount' => 'Adjustment amount must be greater than zero.']);
        if ((! $isReversal && ! in_array($reason, self::REASONS, true)) || ($reason === 'Other' && blank($data['notes'] ?? null))) throw ValidationException::withMessages(['reason' => 'Choose a reason; notes are required for Other.']);
        if (! $isReversal && $reason === 'Write-Off') throw ValidationException::withMessages(['reason' => 'Write-offs are not supported by the current accounting configuration.']);
        if ($token === '') throw ValidationException::withMessages(['submission_token' => 'A fresh submission token is required.']);

        $partyType = $isCustomer ? 'customer' : 'supplier';
        if ($existing = BalanceAdjustment::where('business_id', $party->business_id)->where('submission_token', $token)->first()) {
            if ($existing->party_type === $partyType && (int) $existing->party_id === (int) $party->id) return $existing;
            throw ValidationException::withMessages(['submission_token' => 'This adjustment submission was already used.']);
        }

        $previous = $isCustomer ? round((float) $party->current_balance, 2) : $this->supplierPayable($party);
        $preview = self::preview($previous, $type, $amount);
        $reference = $this->numbers->next($party->business_id, 'balance_adjustment');
        $adjustment = BalanceAdjustment::create([
            'business_id' => $party->business_id, 'party_type' => $partyType, 'party_id' => $party->id,
            'reference' => $reference, 'adjustment_type' => $type, 'amount' => $amount,
            'previous_balance' => $previous, 'new_balance' => $preview['new_balance'], 'reason' => $reason,
            'external_reference' => filled($data['external_reference'] ?? null) ? trim((string) $data['external_reference']) : null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            'submission_token' => $token, 'created_by' => $actor->id,
            'reverses_adjustment_id' => $reverses?->id,
        ]);

        $this->accounting->ensureDefaultAccounts($party->business_id);
        $accounts = Account::where('business_id', $party->business_id)->whereIn('name', [$isCustomer ? 'Accounts Receivable' : 'Accounts Payable', 'Owner Equity'])->pluck('id', 'name');
        $control = $isCustomer ? 'Accounts Receivable' : 'Accounts Payable';
        if (! isset($accounts[$control], $accounts['Owner Equity'])) throw ValidationException::withMessages(['amount' => 'Required control accounts are not configured.']);

        $increase = $preview['adjustment'] > 0;
        $description = ($isCustomer ? 'Customer receivable' : 'Supplier payable').' adjustment '.$reference.' — '.$reason;
        $controlDebit = $isCustomer ? $increase : ! $increase;
        $this->accounting->post($party->business_id, [
            'reference_type' => $partyType.'_balance_adjustment', 'reference_id' => $adjustment->id,
            'voucher_number' => $reference, 'description' => $description,
        ], $controlDebit ? [
            ['account_id' => $accounts[$control], $partyType.'_id' => $party->id, 'debit' => $amount, 'credit' => 0, 'description' => $description],
            ['account_id' => $accounts['Owner Equity'], $partyType.'_id' => $party->id, 'debit' => 0, 'credit' => $amount, 'description' => $description],
        ] : [
            ['account_id' => $accounts['Owner Equity'], $partyType.'_id' => $party->id, 'debit' => $amount, 'credit' => 0, 'description' => $description],
            ['account_id' => $accounts[$control], $partyType.'_id' => $party->id, 'debit' => 0, 'credit' => $amount, 'description' => $description],
        ]);

        if ($isCustomer) {
            $party->update(['current_balance' => $preview['new_balance']]);
            KhataLedger::create([
                'business_id' => $party->business_id, 'customer_id' => $party->id, 'entry_type' => 'balance_adjustment',
                'type' => $increase ? 'credit' : 'debit', 'amount' => $amount,
                'customer_debit' => $increase ? 0 : $amount, 'customer_credit' => $increase ? $amount : 0,
                'business_debit' => $increase ? $amount : 0, 'business_credit' => $increase ? 0 : $amount,
                'description' => $description, 'balance' => $preview['new_balance'], 'balance_after' => $preview['new_balance'], 'entry_date' => now()->toDateString(),
            ]);
        }

        $verb = $increase ? 'increased' : 'decreased';
        $this->activity->record($party->business_id, $isCustomer ? 'Customers' : 'Suppliers', $partyType.'_balance_adjusted', $adjustment->id, null, [
            $partyType.'_name' => $isCustomer ? $party->display_name : $party->supplier_name,
            'amount' => $amount, 'reason' => $reason, 'reference' => $reference, 'direction' => $verb,
            'balance_kind' => $isCustomer ? 'receivable' : 'payable', 'previous_balance' => $previous, 'new_balance' => $preview['new_balance'],
        ]);
        return $adjustment;
    }

    private function supplierPayable(Supplier $supplier): float
    {
        $lines = JournalEntryLine::query()->where('supplier_id', $supplier->id)
            ->whereHas('journalEntry', fn ($query) => $query->where('business_id', $supplier->business_id)->where('status', 'posted'))
            ->whereHas('account', fn ($query) => $query->where('name', 'Accounts Payable'));
        return round((float) $lines->sum('credit') - (float) $lines->sum('debit'), 2);
    }
}
