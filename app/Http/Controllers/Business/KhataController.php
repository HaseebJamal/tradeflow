<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Order;
use App\Models\Supplier;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KhataController extends Controller
{
    public function index(Request $request, AccountingService $accounting)
    {
        $filters = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'voucher_type' => ['nullable', 'string', 'max:50'],
            'supplier_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:draft,posted,void'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        if (($filters['date_from'] ?? null) && ($filters['date_to'] ?? null) && $filters['date_to'] < $filters['date_from']) {
            return back()->withErrors(['date_to' => 'Date To must be after or equal to Date From.'])->withInput();
        }

        $businessId = auth()->user()->business_id;
        $accounting->ensureDefaultAccounts($businessId);

        $lineQuery = JournalEntryLine::query()
            ->with(['journalEntry', 'account', 'customer', 'supplier'])
            ->whereHas('journalEntry', fn ($q) => $q->where('business_id', $businessId))
            ->when($filters['account_id'] ?? null, fn ($q, $value) => $q->where('account_id', $value))
            ->when($filters['customer_id'] ?? null, fn ($q, $value) => $q->where('customer_id', $value))
            ->when($filters['supplier_id'] ?? null, fn ($q, $value) => $q->where('supplier_id', $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->whereHas('journalEntry', fn ($entry) => $entry->where('status', $value)))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereHas('journalEntry', fn ($entry) => $entry->whereDate('entry_date', '>=', $value)))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereHas('journalEntry', fn ($entry) => $entry->whereDate('entry_date', '<=', $value)))
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('description', 'like', "%{$value}%")
                ->orWhereHas('journalEntry', fn ($entry) => $entry->where('voucher_number', 'like', "%{$value}%")->orWhere('description', 'like', "%{$value}%"))));

        $accounts = Account::where('business_id', $businessId)->orderBy('code')->get();
        $customers = Customer::where('business_id', $businessId)->orderBy('name')->get();
        $suppliers = Supplier::where('business_id', $businessId)->orderBy('supplier_name')->get();

        $ledgerNet = (float) ((clone $lineQuery)->selectRaw('COALESCE(SUM(debit - credit), 0) as net_balance')->value('net_balance') ?? 0);
        $ledgerLines = (clone $lineQuery)
            ->reorder()
            ->orderByDesc('journal_entry_lines.id')
            ->paginate(10, ['*'], 'ledger_page')
            ->withQueryString();

        // The table is newest-first, so carry the total from newer entries into
        // the current page before walking backwards through the visible lines.
        if ($firstLine = $ledgerLines->getCollection()->first()) {
            $newerNet = (float) ((clone $lineQuery)
                ->where('journal_entry_lines.id', '>', $firstLine->id)
                ->selectRaw('COALESCE(SUM(debit - credit), 0) as net_balance')
                ->value('net_balance') ?? 0);
            $runningBalance = $ledgerNet - $newerNet;
            $ledgerLines->getCollection()->transform(function (JournalEntryLine $line) use (&$runningBalance): JournalEntryLine {
                $line->setAttribute('running_balance', $runningBalance);
                $runningBalance -= (float) $line->debit - (float) $line->credit;

                return $line;
            });
        }

        $postedLineQuery = (clone $lineQuery)->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'));
        $accountTotals = (clone $postedLineQuery)
            ->reorder()
            ->select('account_id')
            ->selectRaw('SUM(debit) as ledger_debit, SUM(credit) as ledger_credit')
            ->groupBy('account_id');
        $accountBalanceQuery = Account::query()
            ->where('accounts.business_id', $businessId)
            ->leftJoinSub($accountTotals, 'ledger_totals', 'ledger_totals.account_id', '=', 'accounts.id')
            ->select('accounts.*')
            ->selectRaw('COALESCE(ledger_totals.ledger_debit, 0) as ledger_debit, COALESCE(ledger_totals.ledger_credit, 0) as ledger_credit');
        $toBalanceRow = static function (Account $account): array {
            $debit = (float) $account->ledger_debit;
            $credit = (float) $account->ledger_credit;

            return [
                'account' => $account,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $account->normal_balance === 'Debit' ? $debit - $credit : $credit - $debit,
            ];
        };
        $trialBalance = (clone $accountBalanceQuery)->orderBy('accounts.code')->paginate(10, ['*'], 'trial_page')->withQueryString();
        $trialBalance->setCollection($trialBalance->getCollection()->map($toBalanceRow));
        $balanceSheet = (clone $accountBalanceQuery)
            ->whereIn('accounts.account_type', ['Asset', 'Liability', 'Equity'])
            ->orderBy('accounts.code')
            ->paginate(10, ['*'], 'balance_page')
            ->withQueryString();
        $balanceSheet->setCollection($balanceSheet->getCollection()->map($toBalanceRow));

        $sales = Order::where('business_id', $businessId)->whereNotIn('status', ['Cancelled'])->sum('grand_total');
        $cashReceived = JournalEntryLine::whereHas('account', fn ($q) => $q->where('business_id', $businessId)->whereIn('name', ['Cash', 'Bank']))
            ->whereHas('journalEntry', fn ($q) => $q->where('business_id', $businessId)->where('status', 'posted'))
            ->sum('debit');
        $accountsReceivable = 0;
        if ($receivable = $accounts->firstWhere('name', 'Accounts Receivable')) {
            $debit = (float) ((clone $postedLineQuery)->where('account_id', $receivable->id)->sum('debit'));
            $credit = (float) ((clone $postedLineQuery)->where('account_id', $receivable->id)->sum('credit'));
            $accountsReceivable = $receivable->normal_balance === 'Debit' ? $debit - $credit : $credit - $debit;
        }
        $totalExpenses = Expense::where('business_id', $businessId)->sum('amount');

        $customerSummaries = (clone $lineQuery)
            ->reorder()
            ->whereNotNull('customer_id')
            ->select('customer_id')
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('customer_id')
            ->havingRaw('SUM(debit) <> 0 OR SUM(credit) <> 0')
            ->with('customer:id,name,business_name')
            ->orderBy('customer_id')
            ->paginate(10, ['*'], 'customer_page')
            ->withQueryString();
        $customerSummaries->getCollection()->transform(static function (JournalEntryLine $line): array {
            $debit = (float) $line->total_debit;
            $credit = (float) $line->total_credit;

            return ['customer' => $line->customer, 'debit' => $debit, 'credit' => $credit, 'balance' => $debit - $credit];
        });

        $supplierSummaries = (clone $lineQuery)
            ->reorder()
            ->whereNotNull('supplier_id')
            ->select('supplier_id')
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('supplier_id')
            ->havingRaw('SUM(debit) <> 0 OR SUM(credit) <> 0')
            ->with('supplier:id,supplier_name,company_name')
            ->orderBy('supplier_id')
            ->paginate(10, ['*'], 'supplier_page')
            ->withQueryString();
        $supplierSummaries->getCollection()->transform(static function (JournalEntryLine $line): array {
            $debit = (float) $line->total_debit;
            $credit = (float) $line->total_credit;

            return ['supplier' => $line->supplier, 'debit' => $debit, 'credit' => $credit, 'balance' => $credit - $debit];
        });

        $journalEntries = JournalEntry::where('business_id', $businessId)
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('entry_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('entry_date', '<=', $value))
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->where(fn ($entry) => $entry->where('voucher_number', 'like', "%{$value}%")->orWhere('description', 'like', "%{$value}%")))
            ->latest()
            ->paginate(10, ['*'], 'journal_page')
            ->withQueryString();

        return view('business.khata.index', [
            'accounts' => $accounts,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'ledgerLines' => $ledgerLines,
            'trialBalance' => $trialBalance,
            'balanceSheet' => $balanceSheet,
            'customerSummaries' => $customerSummaries,
            'supplierSummaries' => $supplierSummaries,
            'journalEntries' => $journalEntries,
            'voucherNumber' => $accounting->voucherNumber($businessId),
            'totalSales' => $sales,
            'accountsReceivable' => $accountsReceivable,
            'cashReceived' => $cashReceived,
            'totalExpenses' => $totalExpenses,
            'netProfit' => $sales - $totalExpenses,
        ]);
    }

    public function storeJournal(Request $request, AccountingService $accounting)
    {
        return back()->withErrors(['journal' => 'Journal entries are generated automatically from purchases, sales, payments, returns, and expenses.']);

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'voucher_number' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer'],
            'lines.*.debit' => ['nullable', 'integer', 'min:0'],
            'lines.*.credit' => ['nullable', 'integer', 'min:0'],
            'lines.*.description' => ['nullable', 'string'],
            'lines.*.customer_id' => ['nullable', Rule::exists('customers', 'id')->where('business_id', auth()->user()->business_id)],
            'lines.*.supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('business_id', auth()->user()->business_id)],
        ]);

        $lines = collect($data['lines'])
            ->filter(fn ($line) => ((float) ($line['debit'] ?? 0)) > 0 || ((float) ($line['credit'] ?? 0)) > 0)
            ->values()
            ->all();

        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => 'Journal entry must have at least two debit/credit lines.']);
        }

        try {
            $accounting->post(auth()->user()->business_id, [
                'voucher_number' => $data['voucher_number'],
                'entry_date' => $data['entry_date'],
                'description' => $data['description'] ?? null,
            ], $lines);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['lines' => $exception->getMessage()]);
        }

        return back()->with('success', 'Journal entry posted.');
    }
}
