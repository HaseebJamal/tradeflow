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

        $trialBalance = $accounts->map(function (Account $account) use ($businessId) {
            $lines = JournalEntryLine::where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('business_id', $businessId)->where('status', 'posted'));
            $debit = (clone $lines)->sum('debit');
            $credit = (clone $lines)->sum('credit');
            $balance = $account->normal_balance === 'Debit' ? $debit - $credit : $credit - $debit;

            return compact('account', 'debit', 'credit', 'balance');
        });

        $sales = Order::where('business_id', $businessId)->whereNotIn('status', ['Cancelled'])->sum('grand_total');
        $cashReceived = JournalEntryLine::whereHas('account', fn ($q) => $q->where('business_id', $businessId)->whereIn('name', ['Cash', 'Bank']))
            ->whereHas('journalEntry', fn ($q) => $q->where('business_id', $businessId)->where('status', 'posted'))
            ->sum('debit');
        $accountsReceivable = $trialBalance->firstWhere('account.name', 'Accounts Receivable')['balance'] ?? 0;
        $totalExpenses = Expense::where('business_id', $businessId)->sum('amount');

        $customerSummaries = $customers->map(function (Customer $customer) {
            $lines = JournalEntryLine::where('customer_id', $customer->id);
            $debit = (clone $lines)->sum('debit');
            $credit = (clone $lines)->sum('credit');
            return ['customer' => $customer, 'debit' => $debit, 'credit' => $credit, 'balance' => $debit - $credit];
        })->filter(fn ($row) => $row['debit'] > 0 || $row['credit'] > 0 || $row['balance'] != 0);

        $supplierSummaries = $suppliers->map(function (Supplier $supplier) {
            $lines = JournalEntryLine::where('supplier_id', $supplier->id);
            $debit = (clone $lines)->sum('debit');
            $credit = (clone $lines)->sum('credit');
            return ['supplier' => $supplier, 'debit' => $debit, 'credit' => $credit, 'balance' => $credit - $debit];
        })->filter(fn ($row) => $row['debit'] > 0 || $row['credit'] > 0 || $row['balance'] != 0);

        return view('business.khata.index', [
            'accounts' => $accounts,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'ledgerLines' => $lineQuery->latest()->paginate(30)->withQueryString(),
            'trialBalance' => $trialBalance,
            'customerSummaries' => $customerSummaries,
            'supplierSummaries' => $supplierSummaries,
            'journalEntries' => JournalEntry::where('business_id', $businessId)->latest()->limit(20)->get(),
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
