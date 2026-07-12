<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function __construct(private AccountingService $accounting) {}

    public function index(Request $request)
    {
        $businessId = auth()->user()->business_id;
        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:Active,Inactive'],
            'city' => ['nullable', 'string', 'max:100'],
            'created_by' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'clear' => ['nullable', 'boolean'],
        ]);

        $dateFrom = $request->boolean('clear') ? null : ($filters['date_from'] ?? now()->startOfMonth()->toDateString());
        $dateTo = $request->boolean('clear') ? null : ($filters['date_to'] ?? now()->toDateString());

        $query = Supplier::where('business_id', $businessId)
            ->with('creator')
            ->when($filters['name'] ?? null, fn ($q, $value) => $q->where('supplier_name', 'like', "%{$value}%"))
            ->when($filters['company'] ?? null, fn ($q, $value) => $q->where('company_name', 'like', "%{$value}%"))
            ->when($filters['phone'] ?? null, fn ($q, $value) => $q->where('phone', 'like', "%{$value}%"))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['city'] ?? null, fn ($q, $value) => $q->where('city', 'like', "%{$value}%"))
            ->when($filters['created_by'] ?? null, fn ($q, $value) => $q->where('created_by', $value))
            ->when($dateFrom, fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($dateTo, fn ($q, $value) => $q->whereDate('created_at', '<=', $value));

        return view('business.suppliers.index', [
            'suppliers' => $query->latest()->paginate(20)->withQueryString(),
            'creators' => User::where('business_id', $businessId)->orderBy('name')->get(),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            $supplier = Supplier::create($this->validated($request) + [
                'business_id' => auth()->user()->business_id,
                'created_by' => auth()->id(),
            ]);

            $this->postOpeningBalance($supplier);
        });

        return back()->with('success', 'Supplier saved.');
    }

    public function create()
    {
        return redirect()->route('business.suppliers.index');
    }

    public function show(Supplier $supplier)
    {
        $supplier = $this->scoped($supplier);
        $lines = JournalEntryLine::with(['journalEntry', 'account'])
            ->where('supplier_id', $supplier->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('business_id', $supplier->business_id)->where('status', 'posted'))
            ->oldest('id')
            ->get();

        $totalPurchases = (float) $supplier->opening_balance + $lines->sum('credit');
        $totalPayments = $lines->sum('debit');

        return view('business.suppliers.show', [
            'supplier' => $supplier,
            'lines' => $lines,
            'totalPurchases' => $totalPurchases,
            'totalPayments' => $totalPayments,
            'remainingPayable' => $totalPurchases - $totalPayments,
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return view('business.suppliers.edit', ['supplier' => $this->scoped($supplier)]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier = $this->scoped($supplier);
        $supplier->update($this->validated($request));

        return redirect()->route('business.suppliers.show', $supplier)->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier = $this->scoped($supplier);

        if ($supplier->journalLines()->exists()) {
            $supplier->update(['status' => 'Inactive']);
            return back()->with('success', 'Supplier has ledger history, so it was marked inactive.');
        }

        $supplier->delete();
        return back()->with('success', 'Supplier deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function scoped(Supplier $supplier): Supplier
    {
        abort_unless($supplier->business_id === auth()->user()->business_id, 404);
        return $supplier;
    }

    private function postOpeningBalance(Supplier $supplier): void
    {
        if ((float) $supplier->opening_balance <= 0) {
            return;
        }

        if (JournalEntry::where('business_id', $supplier->business_id)->where('reference_type', 'supplier_opening')->where('reference_id', $supplier->id)->exists()) {
            return;
        }

        $this->accounting->ensureDefaultAccounts($supplier->business_id);
        $equity = Account::where('business_id', $supplier->business_id)->where('name', 'Owner Equity')->first();
        $payable = Account::where('business_id', $supplier->business_id)->where('name', 'Accounts Payable')->first();

        if (!$equity || !$payable) {
            return;
        }

        $this->accounting->post($supplier->business_id, [
            'voucher_number' => 'SUP-OPEN-'.$supplier->id.'-'.now()->format('His'),
            'entry_date' => now()->toDateString(),
            'reference_type' => 'supplier_opening',
            'reference_id' => $supplier->id,
            'description' => 'Opening payable for '.$supplier->supplier_name,
        ], [
            ['account_id' => $equity->id, 'supplier_id' => $supplier->id, 'debit' => $supplier->opening_balance, 'credit' => 0, 'description' => 'Opening balance'],
            ['account_id' => $payable->id, 'supplier_id' => $supplier->id, 'debit' => 0, 'credit' => $supplier->opening_balance, 'description' => 'Opening payable'],
        ]);
    }
}
