<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\KhataLedger;

class CustomerOpeningBalanceService
{
    public function __construct(private AccountingService $accounting)
    {
    }

    /**
     * A customer opening balance is recorded once at creation. Re-checking the
     * source ledger makes this safe if a request is retried.
     */
    public function recordFor(Customer $customer): void
    {
        $amount = (int) $customer->opening_balance;

        if ($amount <= 0) {
            return;
        }

        if (! $this->hasLedger($customer)) {
            KhataLedger::create([
                'business_id' => $customer->business_id,
                'customer_id' => $customer->id,
                'entry_type' => 'opening_balance',
                'type' => 'credit',
                'amount' => $amount,
                'customer_debit' => 0,
                'customer_credit' => $amount,
                'business_debit' => $amount,
                'business_credit' => 0,
                'description' => 'Customer opening balance',
                'balance' => $customer->current_balance,
                'balance_after' => $customer->current_balance,
                'entry_date' => now()->toDateString(),
            ]);
        }

        if (JournalEntry::where('business_id', $customer->business_id)
            ->where('reference_type', 'customer_opening_balance')
            ->where('reference_id', $customer->id)
            ->exists()) {
            return;
        }

        $this->accounting->ensureDefaultAccounts($customer->business_id);
        $receivable = Account::where('business_id', $customer->business_id)->where('name', 'Accounts Receivable')->first();
        $equity = Account::where('business_id', $customer->business_id)->where('name', 'Owner Equity')->first();

        if (! $receivable || ! $equity) {
            return;
        }

        $this->accounting->post($customer->business_id, [
            'reference_type' => 'customer_opening_balance',
            'reference_id' => $customer->id,
            'description' => 'Customer opening balance',
        ], [
            ['account_id' => $receivable->id, 'customer_id' => $customer->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Customer opening balance'],
            ['account_id' => $equity->id, 'customer_id' => $customer->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Customer opening balance'],
        ]);
    }

    private function hasLedger(Customer $customer): bool
    {
        return KhataLedger::where('business_id', $customer->business_id)
            ->where('customer_id', $customer->id)
            ->where('entry_type', 'opening_balance')
            ->exists();
    }
}
