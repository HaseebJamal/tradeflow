<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
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
            'phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'status' => ['nullable', 'in:Active,Inactive,Archived'],
            'city' => ['nullable', 'string', 'max:100'],
            'created_by' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'clear' => ['nullable', 'boolean'],
        ]);

        $showArchived = ($filters['status'] ?? null) === 'Archived';
        $dateFrom = $request->boolean('clear') ? null : ($filters['date_from'] ?? ($showArchived ? null : now()->startOfMonth()->toDateString()));
        $dateTo = $request->boolean('clear') ? null : ($filters['date_to'] ?? ($showArchived ? null : now()->toDateString()));

        $query = Supplier::where('business_id', $businessId)->with('creator');

        if ($showArchived) {
            $query->onlyTrashed();
        }

        $query
            ->when($filters['name'] ?? null, fn ($q, $value) => $q->where('supplier_name', 'like', "%{$value}%"))
            ->when($filters['company'] ?? null, fn ($q, $value) => $q->where('company_name', 'like', "%{$value}%"))
            ->when($filters['phone'] ?? null, fn ($q, $value) => $q->where('phone', 'like', "%{$value}%"))
            ->when(in_array($filters['status'] ?? null, ['Active', 'Inactive'], true), fn ($q, $value) => $q->where('status', $value))
            ->when($filters['city'] ?? null, fn ($q, $value) => $q->where('city', 'like', "%{$value}%"))
            ->when($filters['created_by'] ?? null, fn ($q, $value) => $q->where('created_by', $value))
            ->when($dateFrom, fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($dateTo, fn ($q, $value) => $q->whereDate('created_at', '<=', $value));

        return view('business.suppliers.index', [
            'suppliers' => $query->latest()->paginate(12)->withQueryString(),
            'creators' => User::where('business_id', $businessId)->orderBy('name')->get(),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            $validated = $this->validated($request);
            $supplier = Supplier::create($validated + [
                'opening_balance' => $validated['opening_balance'] ?? 0,
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

    public function show(Request $request, int $supplier)
    {
        $supplier = Supplier::withTrashed()
            ->where('business_id', $request->user()->business_id)
            ->findOrFail($supplier);
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
        $validated = $this->validated($request);

        DB::transaction(function () use ($supplier, $validated) {
            $supplier->update($validated + ['opening_balance' => $validated['opening_balance'] ?? 0]);
            $this->syncOpeningBalance($supplier->fresh());
        });

        return redirect()->route('business.suppliers.show', $supplier)->with('success', 'Supplier updated.');
    }

    public function archive(Supplier $supplier)
    {
        $supplier = $this->scoped($supplier);
        $supplier->update(['status' => 'Inactive']);
        $supplier->delete();
        $this->audit('Supplier Archived', $supplier);

        return back()->with('success', 'Record archived successfully.');
    }

    public function restore(Request $request, int $supplier)
    {
        $supplier = Supplier::withTrashed()
            ->where('business_id', $request->user()->business_id)
            ->findOrFail($supplier);
        $supplier->restore();
        $supplier->update(['status' => 'Active']);
        $this->audit('Supplier Restored', $supplier);

        return back()->with('success', 'Record restored successfully.');
    }

    public function destroy(Request $request, int $supplier)
    {
        $supplier = Supplier::withTrashed()
            ->where('business_id', $request->user()->business_id)
            ->findOrFail($supplier);
        $supplier = $this->scoped($supplier);

        if ($supplier->journalLines()->exists() || $supplier->purchases()->exists() || $supplier->payments()->exists()) {
            return back()->with('error', 'This supplier has related records and cannot be deleted. Archive it instead.');
        }

        $this->audit('Supplier Permanently Deleted', $supplier);
        $supplier->forceDelete();

        return back()->with('success', 'Record permanently deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'company_name' => ['nullable', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'opening_balance' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function scoped(Supplier $supplier): Supplier
    {
        abort_unless($supplier->business_id === auth()->user()->business_id, 404);
        return $supplier;
    }

    private function audit(string $action, Supplier $supplier): void
    {
        AuditLog::create([
            'business_id' => $supplier->business_id,
            'module' => 'Suppliers',
            'action' => $action,
            'description' => $supplier->supplier_name,
            'record_type' => Supplier::class,
            'record_id' => $supplier->id,
            'occurred_at' => now(),
        ]);
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

    /** Keep the opening ledger entry balanced when the opening figure is edited. */
    private function syncOpeningBalance(Supplier $supplier): void
    {
        $entry = JournalEntry::where('business_id', $supplier->business_id)
            ->where('reference_type', 'supplier_opening')
            ->where('reference_id', $supplier->id)
            ->with('lines.account')
            ->first();

        if (!$entry) {
            $this->postOpeningBalance($supplier);

            return;
        }

        foreach ($entry->lines as $line) {
            if ($line->account?->name === 'Owner Equity') {
                $line->update(['debit' => $supplier->opening_balance, 'credit' => 0]);
            }
            if ($line->account?->name === 'Accounts Payable') {
                $line->update(['debit' => 0, 'credit' => $supplier->opening_balance]);
            }
        }
    }
}
