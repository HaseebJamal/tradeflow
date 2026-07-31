<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\GoodsReceiptItem;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\CompanyPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'suppliers' => $query->latest()->paginate(10)->withQueryString(),
            'creators' => User::where('business_id', $businessId)->orderBy('name')->get(),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function store(Request $request)
    {
        $businessId = (int) auth()->user()->business_id;
        abort_unless(app(CompanyPermissionService::class)->allowsUser($request->user(), 'suppliers.create'), 403);

        $supplier = DB::transaction(function () use ($request, $businessId) {
            Business::query()->lockForUpdate()->findOrFail($businessId);
            $validated = $this->normaliseSupplierFields($this->validated($request));
            $this->ensureUniqueSupplier($businessId, $validated);
            $supplier = Supplier::create(array_merge($validated, [
                'opening_balance' => $validated['opening_balance'] ?? 0,
                'business_id' => $businessId,
                'created_by' => auth()->id(),
            ]));

            $this->postOpeningBalance($supplier);

            return $supplier;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Supplier saved.',
                'supplier' => $supplier->only(['id', 'supplier_name', 'company_name', 'phone', 'email', 'address', 'city', 'opening_balance', 'status']),
            ], 201);
        }

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
        $filters = $request->validate(['date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from']]);
        $lines = JournalEntryLine::with(['journalEntry', 'account'])
            ->where('supplier_id', $supplier->id)
            ->whereHas('journalEntry', fn ($q) => $q->where('business_id', $supplier->business_id)->where('status', 'posted'))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereHas('journalEntry', fn ($journal) => $journal->whereDate('entry_date', '>=', $date)))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereHas('journalEntry', fn ($journal) => $journal->whereDate('entry_date', '<=', $date)))
            ->oldest('id')
            ->get();

        $payableLines = $lines->filter(fn (JournalEntryLine $line) => $line->account?->name === 'Accounts Payable');
        $totalPurchases = (float) $payableLines->sum('credit');
        $totalPayments = (float) $payableLines->sum('debit');
        $receivedValue = (float) GoodsReceiptItem::whereHas('goodsReceipt', fn ($receipt) => $receipt->where('business_id', $supplier->business_id)->where('supplier_id', $supplier->id))->sum('line_total');
        $returns = (float) PurchaseReturn::where('business_id', $supplier->business_id)->where('supplier_id', $supplier->id)->sum('total_amount');
        $availableAdvances = (float) $supplier->payments()->where('is_advance', true)->sum('remaining_amount');
        $openPurchases = Purchase::where('business_id', $supplier->business_id)->where('supplier_id', $supplier->id)->where('balance', '>', 0);
        $overduePayable = (float) (clone $openPurchases)->whereNotNull('due_date')->whereDate('due_date', '<', now(config('app.timezone'))->toDateString())->sum('balance');

        return view('business.suppliers.show', [
            'supplier' => $supplier,
            'lines' => $lines,
            'totalPurchases' => $totalPurchases,
            'totalPayments' => $totalPayments,
            'remainingPayable' => (float) $lines->sum('credit') - (float) $lines->sum('debit'),
            'receivedValue' => $receivedValue,
            'returnsValue' => $returns,
            'availableAdvances' => $availableAdvances,
            'overduePayable' => $overduePayable,
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return view('business.suppliers.edit', ['supplier' => $this->scoped($supplier)]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier = $this->scoped($supplier);
        $validated = $this->normaliseSupplierFields($this->validated($request));

        DB::transaction(function () use ($supplier, $validated) {
            Business::query()->lockForUpdate()->findOrFail($supplier->business_id);
            $this->ensureUniqueSupplier($supplier->business_id, $validated, $supplier->id);
            $supplier->update(array_merge($validated, ['opening_balance' => $validated['opening_balance'] ?? 0]));
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

    /** Locking the parent business serializes concurrent creates in the same tenant. */
    private function ensureUniqueSupplier(int $businessId, array $data, ?int $ignoreSupplierId = null): void
    {
        $identity = [
            'supplier_name' => $this->normaliseForComparison($data['supplier_name'] ?? ''),
            'company_name' => $this->normaliseForComparison($data['company_name'] ?? ''),
            'city' => $this->normaliseForComparison($data['city'] ?? ''),
            'phone' => $this->normalisePhoneForComparison($data['phone'] ?? ''),
        ];
        $email = $this->normaliseForComparison($data['email'] ?? '');
        $hasCompleteIdentity = collect($identity)->every(fn ($value) => $value !== '');

        $duplicate = Supplier::withTrashed()
            ->where('business_id', $businessId)
            ->when($ignoreSupplierId, fn ($query) => $query->whereKeyNot($ignoreSupplierId))
            ->get(['id', 'supplier_name', 'company_name', 'city', 'phone', 'email'])
            ->first(function (Supplier $candidate) use ($identity, $email, $hasCompleteIdentity) {
                $candidatePhone = $this->normalisePhoneForComparison($candidate->phone ?? '');
                $candidateEmail = $this->normaliseForComparison($candidate->email ?? '');

                if ($identity['phone'] !== '' && $identity['phone'] === $candidatePhone) {
                    return true;
                }

                if ($email !== '' && $email === $candidateEmail) {
                    return true;
                }

                if (! $hasCompleteIdentity || $candidatePhone === '') {
                    return false;
                }

                return $identity['supplier_name'] === $this->normaliseForComparison($candidate->supplier_name ?? '')
                    && $identity['company_name'] === $this->normaliseForComparison($candidate->company_name ?? '')
                    && $identity['city'] === $this->normaliseForComparison($candidate->city ?? '')
                    && $identity['phone'] === $candidatePhone;
            });

        if ($duplicate) {
            throw ValidationException::withMessages([
                'supplier_name' => 'A supplier with the same phone or complete identity already exists for this business.',
            ]);
        }
    }

    private function normaliseSupplierFields(array $data): array
    {
        foreach (['supplier_name', 'company_name', 'city'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = trim((string) preg_replace('/\s+/u', ' ', $data[$field]));
            }
        }

        if (array_key_exists('email', $data) && $data['email'] !== null) {
            $data['email'] = mb_strtolower(trim($data['email']));
        }

        if (array_key_exists('phone', $data) && $data['phone'] !== null) {
            $data['phone'] = $this->normalisePhoneForComparison($data['phone']);
        }

        $data['opening_balance'] = $data['opening_balance'] ?? 0;

        return $data;
    }

    private function normaliseForComparison(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    private function normalisePhoneForComparison(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $value);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (preg_match('/^0?3\d{9}$/', $digits)) {
            $digits = '92'.ltrim($digits, '0');
        }

        return '+'.$digits;
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
