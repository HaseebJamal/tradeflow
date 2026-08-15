<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Customer;
use App\Models\KhataLedger;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Keeps an authorised manual customer-balance edit auditable and in sync. */
class CustomerBalanceAdjustmentService
{
    public function __construct(private AccountingService $accounting)
    {
    }

    public function set(Customer $customer, float $newBalance, User $user): Customer
    {
        return DB::transaction(function () use ($customer, $newBalance, $user): Customer {
            $locked = Customer::query()
                ->where('business_id', $customer->business_id)
                ->lockForUpdate()
                ->findOrFail($customer->id);

            $previousBalance = round((float) $locked->current_balance, 2);
            $newBalance = round($newBalance, 2);
            $adjustment = round($newBalance - $previousBalance, 2);

            if (abs($adjustment) < 0.005) {
                return $locked;
            }

            $locked->update(['current_balance' => $newBalance]);

            KhataLedger::create([
                'business_id' => $locked->business_id,
                'customer_id' => $locked->id,
                'entry_type' => 'balance_adjustment',
                'type' => $adjustment > 0 ? 'credit' : 'debit',
                'amount' => abs($adjustment),
                'customer_debit' => $adjustment < 0 ? abs($adjustment) : 0,
                'customer_credit' => $adjustment > 0 ? $adjustment : 0,
                'business_debit' => $adjustment > 0 ? $adjustment : 0,
                'business_credit' => $adjustment < 0 ? abs($adjustment) : 0,
                'description' => 'Customer balance adjustment: Rs '.number_format($previousBalance, 2).' to Rs '.number_format($newBalance, 2),
                'balance' => $newBalance,
                'balance_after' => $newBalance,
                'entry_date' => now()->toDateString(),
            ]);

            $this->accounting->ensureDefaultAccounts($locked->business_id);
            $accounts = Account::query()
                ->where('business_id', $locked->business_id)
                ->whereIn('name', ['Accounts Receivable', 'Owner Equity'])
                ->pluck('id', 'name');

            if (! isset($accounts['Accounts Receivable'], $accounts['Owner Equity'])) {
                throw ValidationException::withMessages(['current_balance' => 'The accounts required for this balance adjustment are not configured.']);
            }

            $amount = abs($adjustment);
            $this->accounting->post($locked->business_id, [
                'reference_type' => 'customer_balance_adjustment',
                'reference_id' => $locked->id,
                'description' => 'Customer balance adjustment for '.$locked->display_name,
            ], $adjustment > 0
                ? [
                    ['account_id' => $accounts['Accounts Receivable'], 'customer_id' => $locked->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Customer balance adjustment'],
                    ['account_id' => $accounts['Owner Equity'], 'customer_id' => $locked->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Customer balance adjustment'],
                ]
                : [
                    ['account_id' => $accounts['Owner Equity'], 'customer_id' => $locked->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Customer balance adjustment'],
                    ['account_id' => $accounts['Accounts Receivable'], 'customer_id' => $locked->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Customer balance adjustment'],
                ]);

            return $locked->fresh();
        });
    }
}
