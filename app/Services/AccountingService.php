<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountingService
{
    public const DEFAULT_ACCOUNTS = [
        ['1000', 'Cash', 'Asset', 'Debit'],
        ['1010', 'Bank', 'Asset', 'Debit'],
        ['1100', 'Accounts Receivable', 'Asset', 'Debit'],
        ['1200', 'Inventory', 'Asset', 'Debit'],
        ['2000', 'Accounts Payable', 'Liability', 'Credit'],
        ['4000', 'Sales Revenue', 'Income', 'Credit'],
        ['4010', 'Delivery Income', 'Income', 'Credit'],
        ['4090', 'Other Income', 'Income', 'Credit'],
        ['5000', 'Cost of Goods Sold', 'Expense', 'Debit'],
        ['5010', 'Purchases', 'Expense', 'Debit'],
        ['5020', 'Salary Expense', 'Expense', 'Debit'],
        ['5030', 'Rent Expense', 'Expense', 'Debit'],
        ['5040', 'Electricity Expense', 'Expense', 'Debit'],
        ['5050', 'Transport Expense', 'Expense', 'Debit'],
        ['5060', 'Marketing Expense', 'Expense', 'Debit'],
        ['5090', 'General Expense', 'Expense', 'Debit'],
        ['5100', 'Discount Allowed', 'Expense', 'Debit'],
        ['3000', 'Owner Equity', 'Equity', 'Credit'],
    ];

    public function ensureDefaultAccounts(int $businessId): void
    {
        foreach (self::DEFAULT_ACCOUNTS as [$code, $name, $type, $normal]) {
            Account::firstOrCreate(
                ['business_id' => $businessId, 'code' => $code],
                ['name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'status' => 'Active']
            );
        }
    }

    public function voucherNumber(int $businessId, string $prefix = 'JV'): string
    {
        $next = JournalEntry::where('business_id', $businessId)->count() + 1;

        return $prefix.'-'.now()->format('Ymd').'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function post(int $businessId, array $entry, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($businessId, $entry, $lines) {
            $debit = collect($lines)->sum(fn ($line) => round((float) ($line['debit'] ?? 0), 2));
            $credit = collect($lines)->sum(fn ($line) => round((float) ($line['credit'] ?? 0), 2));

            if (round($debit - $credit, 2) !== 0.0 || $debit <= 0) {
                throw new InvalidArgumentException('Journal entry must be balanced before posting.');
            }

            $journal = JournalEntry::create([
                'business_id' => $businessId,
                'voucher_number' => $entry['voucher_number'] ?? $this->voucherNumber($businessId),
                'entry_date' => $entry['entry_date'] ?? now()->toDateString(),
                'reference_type' => $entry['reference_type'] ?? null,
                'reference_id' => $entry['reference_id'] ?? null,
                'description' => $entry['description'] ?? null,
                'status' => 'posted',
                'created_by' => auth()->id(),
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                $account = Account::where('business_id', $businessId)->findOrFail($line['account_id']);
                $journal->lines()->create([
                    'account_id' => $account->id,
                    'customer_id' => $line['customer_id'] ?? null,
                    'supplier_id' => $line['supplier_id'] ?? null,
                    'product_id' => $line['product_id'] ?? null,
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $journal;
        });
    }
}
