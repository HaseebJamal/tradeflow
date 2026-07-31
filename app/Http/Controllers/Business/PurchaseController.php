<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Business;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\AccountingService;
use App\Services\BusinessActivityService;
use App\Services\ProductPurchaseCostService;
use App\Services\DocumentNumberService;
use App\Services\CompanyPermissionService;
use App\Services\PurchaseReceivingService;
use App\Services\ThermalDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function __construct(
        private AccountingService $accounting,
        private BusinessActivityService $activity,
        private ProductPurchaseCostService $productCosts,
        private DocumentNumberService $numbers,
        private PurchaseReceivingService $receiving,
    ) {}

    public function index(Request $request)
    {
        $businessId = $this->businessId();
        $filters = $request->validate([
            'supplier_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:60'],
            'search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'create' => ['nullable', 'boolean'],
        ]);
        $filters['date_from'] ??= now(config('app.timezone'))->toDateString();
        $filters['date_to'] ??= now(config('app.timezone'))->toDateString();
        $purchases = Purchase::with([
            'supplier',
            'invoice',
            'creator',
            'items:id,purchase_id,received_quantity',
            'returns.items:id,purchase_return_id,quantity',
        ])->withCount('payments')->withSum('items', 'quantity')->where('business_id', $businessId)
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('purchase_number', 'like', '%'.$request->search.'%')
                ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', '%'.$request->search.'%'))
                ->orWhereHas('supplier', fn ($supplier) => $supplier->where('supplier_name', 'like', '%'.$request->search.'%'))
                ->orWhere('notes', 'like', '%'.$request->search.'%')))
            ->where('purchase_date', '>=', Carbon::parse($filters['date_from'], config('app.timezone'))->startOfDay())
            ->where('purchase_date', '<=', Carbon::parse($filters['date_to'], config('app.timezone'))->endOfDay())
            ->latest('purchase_date')->paginate(10)->withQueryString();

        $suppliers = Supplier::where('business_id', $businessId)->where('status', 'Active')->orderBy('supplier_name')->get();

        return view('business.purchases.index', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'products' => $request->boolean('create') ? Product::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get() : collect(),
            'accounts' => Account::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get(),
            'showPurchaseCreate' => $request->boolean('create'),
        ]);
    }

    public function create()
    {
        return redirect()->route('business.purchases.index', ['create' => 1]);
    }

    /** Resolve an internal purchase or supplier record for scanner input. */
    public function lookup(Request $request)
    {
        $code = trim((string) $request->validate(['code' => ['required', 'string', 'max:120']])['code']);
        $purchase = Purchase::where('business_id', $this->businessId())
            ->where(fn ($query) => $query->where('purchase_number', $code)
                ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', $code))
                ->orWhereHas('supplier', fn ($supplier) => $supplier->where('supplier_name', $code))
                ->orWhere('notes', $code))
            ->first();

        return response()->json([
            'found' => (bool) $purchase,
            'url' => $purchase ? route('business.purchases.show', $purchase) : null,
        ]);
    }

    public function store(Request $request)
    {
        $businessId = $this->businessId();
        $data = $this->validatedPurchase($request);
        $this->ensureActionPermission($data['intent'] === 'confirm' ? 'purchases.confirm' : 'purchases.create');

        $purchase = DB::transaction(function () use ($data, $businessId): Purchase {
            Business::query()->lockForUpdate()->findOrFail($businessId);

            return Purchase::where('business_id', $businessId)
                ->where('submission_token', $data['submission_token'])
                ->first() ?? $this->persistPurchase($data, $businessId);
        });

        $this->activity->record($businessId, 'Purchases', 'Purchase order created: '.$purchase->purchase_number, $purchase->id, null, [
            'supplier_id' => $purchase->supplier_id,
            'grand_total' => $purchase->grand_total,
            'status' => $purchase->status,
        ]);

        return redirect()->route('business.purchases.show', $purchase)->with('success', $purchase->status === 'Draft' ? 'Purchase draft saved.' : 'Purchase confirmed and supplier payable recorded.');
    }

    public function edit(Purchase $purchase)
    {
        $purchase = $this->scoped($purchase);
        $this->ensureActionPermission('purchases.edit');
        $purchase->loadCount('payments');
        $this->assertPurchaseIsEditable($purchase);

        return view('business.purchases.edit', [
            'purchase' => $purchase->load('items'),
            'suppliers' => Supplier::where('business_id', $purchase->business_id)->where('status', 'Active')->orderBy('supplier_name')->get(),
            'products' => Product::where('business_id', $purchase->business_id)->where('status', 'Active')->orderBy('name')->get(),
            'accounts' => Account::where('business_id', $purchase->business_id)->where('status', 'Active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Purchase $purchase)
    {
        $purchase = $this->scoped($purchase);
        $data = $this->validatedPurchase($request);
        $this->ensureActionPermission($data['intent'] === 'confirm' ? 'purchases.confirm' : 'purchases.edit');

        $purchase = DB::transaction(function () use ($purchase, $data): Purchase {
            $locked = Purchase::where('business_id', $purchase->business_id)->lockForUpdate()->findOrFail($purchase->id);
            $locked->loadCount('payments');
            $this->assertPurchaseIsEditable($locked);

            return $this->persistPurchase($data, $locked->business_id, $locked);
        });
        $this->activity->record($purchase->business_id, 'Purchases', 'Purchase '.$purchase->status.' after edit: '.$purchase->purchase_number, $purchase->id);

        return redirect()->route('business.purchases.show', $purchase)->with('success', $purchase->status === 'Draft' ? 'Purchase draft updated.' : 'Purchase confirmed and supplier payable recorded.');
    }

    public function show(Request $request, Purchase $purchase)
    {
        $purchase = $this->scoped($purchase)->load(['supplier', 'items.product', 'invoice', 'payments.creator', 'payments.account', 'returns.items', 'goodsReceipts.items.product', 'goodsReceipts.creator', 'creator', 'updater', 'confirmer']);
        $document = $request->validate(['document' => ['nullable', Rule::in(['print', 'pdf'])]])['document'] ?? null;

        if (! $document) {
            return view('business.purchases.show', compact('purchase'));
        }

        $payload = $this->purchaseDocumentPayload($purchase);
        if ($document === 'pdf') {
            return app(ThermalDocumentService::class)
                ->loadPdf('business.purchases.document-pdf', $payload, 80)
                ->stream($purchase->purchase_number.'-purchase.pdf');
        }

        return view('business.purchases.document-print', $payload);
    }

    private function purchaseDocumentPayload(Purchase $purchase): array
    {
        $quantity = static fn (float|int|string|null $value): string => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
        $amount = static fn (float|int|string|null $value): string => 'Rs '.number_format((float) $value, 2);
        $business = Business::with('documentFooter')->findOrFail($purchase->business_id);

        return [
            'purchase' => $purchase,
            'business' => $business,
            'items' => $purchase->items->map(fn ($item) => [
                'name' => $item->product_name_snapshot ?: $item->product?->name,
                'quantity' => $quantity($item->quantity).($item->unit_snapshot ? ' '.$item->unit_snapshot : ''),
                'rate' => $amount($item->unit_cost),
                'amount' => $amount($item->line_total),
            ])->all(),
            'metadata' => [
                'Invoice' => $purchase->supplier_invoice_number,
                'Payment' => $purchase->payment_method,
                'Due date' => $purchase->due_date?->format('d M Y'),
            ],
            'totals' => [
                ['label' => 'Subtotal', 'amount' => $amount($purchase->subtotal)],
                ['label' => 'Discount', 'amount' => $amount($purchase->discount_amount), 'show' => (float) $purchase->discount_amount !== 0.0],
                ['label' => 'Tax', 'amount' => $amount($purchase->tax_amount), 'show' => (float) $purchase->tax_amount !== 0.0],
                ['label' => 'Other charges', 'amount' => $amount($purchase->other_charges), 'show' => (float) $purchase->other_charges !== 0.0],
                ['label' => 'Paid', 'amount' => $amount($purchase->paid_amount), 'show' => (float) $purchase->paid_amount > 0],
                ['label' => 'Payable', 'amount' => $amount($purchase->balance), 'show' => (float) $purchase->balance > 0],
                ['label' => 'Grand total', 'amount' => $amount($purchase->grand_total), 'emphasis' => true],
            ],
        ];
    }

    private function assertPurchaseIsEditable(Purchase $purchase): void
    {
        $paymentCount = $purchase->payments_count ?? $purchase->payments()->count();
        abort_unless(
            in_array($purchase->status, ['Draft', 'Confirmed'], true)
                && ! $purchase->received_at
                && (int) $paymentCount === 0,
            403,
            'Only an unreceived purchase without supplier payments can be edited.'
        );
    }

    public function cancel(Purchase $purchase)
    {
        $purchase = $this->scoped($purchase);
        $this->ensureActionPermission('purchases.cancel');
        abort_if(!in_array($purchase->status, ['Draft', 'Confirmed'], true) || $purchase->received_at, 422, 'Only an unreceived draft or confirmed purchase can be cancelled.');

        DB::transaction(function () use ($purchase): void {
            $purchase->refresh();
            if ($purchase->status === 'Confirmed') {
                $this->reversePostings($purchase, 'purchase_confirmation', $purchase->id);
                foreach ($purchase->payments as $payment) {
                    $this->reversePostings($purchase, 'supplier_payment', $payment->id);
                }
            }

            $purchase->update([
                'status' => 'Cancelled',
                'balance' => 0,
                'payment_status' => $purchase->paid_amount > 0 ? 'Refund Due' : 'Unpaid',
                'updated_by' => auth()->id(),
            ]);
        });

        $this->activity->record($purchase->business_id, 'Purchases', 'Purchase cancelled: '.$purchase->purchase_number, $purchase->id);

        return back()->with('success', 'Purchase cancelled safely.');
    }

    private function validatedPurchase(Request $request): array
    {
        $data = $request->validate([
            'intent' => ['nullable', Rule::in(['draft', 'confirm'])],
            'submission_token' => ['required', 'uuid'],
            'supplier_id' => ['required', 'integer'],
            'purchase_date' => ['required', 'date'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:255'],
            'supplier_invoice_date' => ['nullable', 'date'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'purchase_order_reference' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', Rule::in(['Cash', 'Due on Receipt', 'Net 7', 'Net 15', 'Net 30', 'Custom'])],
            'due_date' => ['nullable', 'date'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'items.*.tax_value' => ['nullable', 'numeric', 'min:0'],
            'payment_type' => ['nullable', Rule::in(['Full Credit', 'Partial Payment', 'Full Payment'])],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(['Cash', 'Bank Transfer', 'JazzCash', 'Easypaisa', 'Cheque', 'Other'])],
            'payment_date' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'cheque_number' => ['nullable', 'string', 'max:255'],
            'cheque_due_date' => ['nullable', 'date'],
            'payment_account_id' => ['nullable', 'integer'],
        ]);

        $data['intent'] ??= 'confirm';
        $data['payment_terms'] ??= 'Due on Receipt';
        $data['other_charges'] = round((float) ($data['other_charges'] ?? 0), 2);
        $data['supplier_invoice_date'] ??= Carbon::parse($data['purchase_date'] ?? now(), config('app.timezone'))->toDateString();
        $data['due_date'] = $this->resolveDueDate($data);

        if ($data['payment_terms'] === 'Custom' && !$request->filled('due_date')) {
            throw ValidationException::withMessages(['due_date' => 'Select a due date for custom payment terms.']);
        }

        return $data;
    }

    private function persistPurchase(array $data, int $businessId, ?Purchase $purchase = null): Purchase
    {
        $supplier = Supplier::where('business_id', $businessId)->where('status', 'Active')->lockForUpdate()->find($data['supplier_id']);
        if (!$supplier) {
            throw ValidationException::withMessages(['supplier_id' => 'Select an active supplier for this business.']);
        }
        if (!empty($data['payment_account_id']) && !Account::where('business_id', $businessId)->whereKey($data['payment_account_id'])->exists()) {
            throw ValidationException::withMessages(['payment_account_id' => 'Select a payment account from this business.']);
        }

        $prepared = $this->prepareLines($data['items'], $businessId);
        $subtotal = round(collect($prepared)->sum('lineSubtotal'), 2);
        $discount = round(collect($prepared)->sum('lineDiscount'), 2);
        $tax = round(collect($prepared)->sum('lineTax'), 2);
        $grandTotal = round($subtotal - $discount + $tax + $data['other_charges'], 2);
        $payment = $this->resolveInitialPayment($data, $grandTotal);
        $confirmed = $data['intent'] === 'confirm';

        $purchaseNumber = $purchase?->purchase_number ?? $this->numbers->next('purchase');
        $supplierInvoiceNumber = trim((string) ($data['supplier_invoice_number'] ?? ''))
            ?: ($purchase?->supplier_invoice_number ?: $purchaseNumber);

        $attributes = [
            'supplier_id' => $supplier->id,
            'supplier_invoice_number' => $supplierInvoiceNumber,
            'supplier_invoice_date' => $data['supplier_invoice_date'] ?? null,
            'supplier_reference' => $data['supplier_reference'] ?? null,
            'purchase_order_reference' => $data['purchase_order_reference'] ?? null,
            'purchase_date' => $data['purchase_date'],
            'payment_terms' => $data['payment_terms'],
            'due_date' => $data['due_date'],
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'other_charges' => $data['other_charges'],
            'grand_total' => $grandTotal,
            'paid_amount' => $confirmed ? $payment['paid_amount'] : 0,
            'balance' => $confirmed ? $payment['balance'] : $grandTotal,
            'payment_status' => $confirmed ? $payment['status'] : 'Unpaid',
            'payment_method' => $confirmed ? $payment['method'] : null,
            'payment_date' => $confirmed ? $payment['date'] : null,
            'payment_reference' => $confirmed ? $payment['reference'] : null,
            'cheque_number' => $confirmed ? $payment['cheque_number'] : null,
            'cheque_due_date' => $confirmed ? $payment['cheque_due_date'] : null,
            'payment_account_id' => $confirmed ? $payment['account_id'] : null,
            'updated_by' => auth()->id(),
        ];

        if (!$purchase) {
            $purchase = Purchase::create($attributes + [
                'business_id' => $businessId,
                'created_by' => auth()->id(),
                'purchase_number' => $purchaseNumber,
                'submission_token' => $data['submission_token'],
                'status' => $confirmed ? 'Confirmed' : 'Draft',
                'confirmed_by' => $confirmed ? auth()->id() : null,
                'confirmed_at' => $confirmed ? now() : null,
            ]);
        } else {
            $purchase->update($attributes + [
                'status' => $confirmed ? 'Confirmed' : 'Draft',
                'confirmed_by' => $confirmed ? auth()->id() : null,
                'confirmed_at' => $confirmed ? now() : null,
            ]);
            $purchase->items()->delete();
        }

        foreach ($prepared as $line) {
            $purchase->items()->create([
                'product_id' => $line['product_id'],
                'product_name_snapshot' => $line['product']->name,
                'unit_snapshot' => $line['product']->unit,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'discount_amount' => $line['lineDiscount'],
                'tax_type' => $line['tax_type'],
                'tax_value' => $line['tax_value'],
                'tax_amount' => $line['lineTax'],
                'line_total' => $line['lineTotal'],
            ]);
        }

        if ($confirmed) {
            $this->syncConfirmedPurchasePosting($purchase);
            if ($payment['paid_amount'] > 0) {
                $supplierPayment = SupplierPayment::create([
                    'business_id' => $businessId,
                    'supplier_id' => $supplier->id,
                    'purchase_id' => $purchase->id,
                    'account_id' => $payment['account_id'],
                    'created_by' => auth()->id(),
                    'amount' => $payment['paid_amount'],
                    'is_advance' => true,
                    'remaining_amount' => $payment['paid_amount'],
                    'method' => $payment['method'],
                    'reference_number' => $payment['reference'],
                    'cheque_number' => $payment['cheque_number'],
                    'cheque_due_date' => $payment['cheque_due_date'],
                    'payment_date' => $payment['date'],
                ]);
                $this->postPayment($purchase, $supplierPayment);
            }
        }

        return $purchase->fresh();
    }

    private function prepareLines(array $items, int $businessId): array
    {
        return collect($items)->groupBy('product_id')->map(function ($items, $productId) use ($businessId): array {
            $last = $items->last();
            $line = [
                'product_id' => (int) $productId,
                'quantity' => round((float) $items->sum('quantity'), 3),
                'unit_cost' => round((float) $last['unit_cost'], 2),
                'discount_type' => $last['discount_type'] ?? 'fixed',
                'discount_value' => round((float) ($last['discount_value'] ?? 0), 2),
                'tax_type' => $last['tax_type'] ?? 'fixed',
                'tax_value' => round((float) ($last['tax_value'] ?? 0), 2),
            ];
            $product = Product::where('business_id', $businessId)->find($line['product_id']);
            if (!$product) {
                throw ValidationException::withMessages(['items' => 'One or more selected products do not belong to this business.']);
            }
            ['subtotal' => $lineSubtotal, 'discount' => $lineDiscount, 'tax' => $lineTax, 'total' => $lineTotal] = $this->lineAmounts($line);

            return compact('product', 'lineSubtotal', 'lineDiscount', 'lineTax', 'lineTotal') + $line;
        })->values()->all();
    }

    private function resolveInitialPayment(array $data, float $grandTotal): array
    {
        $type = $data['payment_type'] ?? 'Full Credit';
        $method = $data['payment_method'] ?? null;
        $paid = $type === 'Full Payment' ? $grandTotal : round((float) ($data['paid_amount'] ?? 0), 2);
        if ($type === 'Full Credit') $paid = 0;
        if ($type === 'Partial Payment' && ($paid <= 0 || $paid >= $grandTotal)) {
            throw ValidationException::withMessages(['paid_amount' => 'A partial payment must be greater than zero and less than the grand total.']);
        }
        if ($paid < 0 || $paid > $grandTotal) {
            throw ValidationException::withMessages(['paid_amount' => 'Paid amount must be between zero and the grand total.']);
        }
        if ($paid > 0 && empty($method)) {
            throw ValidationException::withMessages(['payment_method' => 'Select a payment method for the amount paid now.']);
        }

        $balance = round($grandTotal - $paid, 2);
        return [
            'paid_amount' => $paid,
            'balance' => $balance,
            'status' => $paid <= 0 ? 'Unpaid' : ($balance > 0 ? 'Partial' : 'Paid'),
            'method' => $paid > 0 ? $method : null,
            'date' => $paid > 0 ? ($data['payment_date'] ?? now()->toDateString()) : null,
            'reference' => $data['payment_reference'] ?? null,
            'cheque_number' => $method === 'Cheque' ? ($data['cheque_number'] ?? null) : null,
            'cheque_due_date' => $method === 'Cheque' ? ($data['cheque_due_date'] ?? null) : null,
            'account_id' => $data['payment_account_id'] ?? null,
        ];
    }

    private function resolveDueDate(array $data): ?string
    {
        $base = Carbon::parse($data['supplier_invoice_date'] ?? $data['purchase_date'], config('app.timezone'));
        return match ($data['payment_terms']) {
            'Cash', 'Due on Receipt' => $base->toDateString(),
            'Net 7' => $base->copy()->addDays(7)->toDateString(),
            'Net 15' => $base->copy()->addDays(15)->toDateString(),
            'Net 30' => $base->copy()->addDays(30)->toDateString(),
            default => $data['due_date'] ?? null,
        };
    }

    private function lineAmounts(array $line): array
    {
        $subtotal = round((float) $line['quantity'] * (float) $line['unit_cost'], 2);
        $discountValue = round((float) ($line['discount_value'] ?? 0), 2);
        $taxValue = round((float) ($line['tax_value'] ?? 0), 2);

        if ($line['discount_type'] === 'percentage' && $discountValue > 100) {
            throw ValidationException::withMessages(['items' => 'Discount percentage cannot exceed 100.']);
        }
        if ($line['tax_type'] === 'percentage' && $taxValue > 100) {
            throw ValidationException::withMessages(['items' => 'Tax percentage cannot exceed 100.']);
        }

        $discount = $line['discount_type'] === 'percentage'
            ? round($subtotal * $discountValue / 100, 2)
            : (float) $discountValue;
        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['items' => 'Discount cannot exceed the item base amount.']);
        }

        $taxable = $subtotal - $discount;
        $tax = $line['tax_type'] === 'percentage'
            ? round($taxable * $taxValue / 100, 2)
            : (float) $taxValue;
        $total = round($taxable + $tax, 2);

        if ($total < 0) {
            throw ValidationException::withMessages(['items' => 'Item total cannot be negative.']);
        }

        return compact('subtotal', 'discount', 'tax', 'total');
    }

    public function receive(Purchase $purchase)
    {
        $purchase = $this->scoped($purchase);
        $this->ensureActionPermission('purchases.receive');
        // Keep the legacy endpoint alive, but direct it into the multi-GRN
        // workflow so it can no longer silently receive every item at once.
        return redirect()->route('business.purchases.receiving.create', $purchase);
    }

    public function pay(Request $request, Purchase $purchase)
    {
        $purchase = $this->scoped($purchase);
        $this->ensureActionPermission('purchases.pay');
        abort_if(in_array($purchase->status, ['Draft', 'Cancelled'], true), 422, 'A draft or cancelled purchase cannot be paid.');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$purchase->balance],
            'method' => ['required', Rule::in(['Cash', 'Bank Transfer', 'JazzCash', 'Easypaisa', 'Cheque', 'Other'])],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'cheque_number' => ['nullable', 'string', 'max:255'],
            'cheque_due_date' => ['nullable', 'date'],
            'account_id' => ['nullable', 'integer'],
        ]);
        DB::transaction(function () use ($purchase, $data) {
            $locked = Purchase::where('business_id', $purchase->business_id)->lockForUpdate()->findOrFail($purchase->id);
            if (!empty($data['account_id']) && !Account::where('business_id', $locked->business_id)->whereKey($data['account_id'])->exists()) {
                throw ValidationException::withMessages(['account_id' => 'Select a payment account from this business.']);
            }
            if ((float) $data['amount'] > (float) $locked->balance) {
                throw ValidationException::withMessages(['amount' => 'Payment amount cannot exceed the remaining payable.']);
            }
            $payment = SupplierPayment::create($data + [
                'business_id' => $locked->business_id,
                'supplier_id' => $locked->supplier_id,
                'purchase_id' => $locked->id,
                'created_by' => auth()->id(),
                'is_advance' => $locked->receiving_status === 'Not Received',
                'remaining_amount' => $locked->receiving_status === 'Not Received' ? $data['amount'] : 0,
                'reference_number' => $data['reference_number'] ?? null,
                'cheque_number' => $data['method'] === 'Cheque' ? ($data['cheque_number'] ?? null) : null,
                'cheque_due_date' => $data['method'] === 'Cheque' ? ($data['cheque_due_date'] ?? null) : null,
            ]);
            $paid = round((float) $locked->paid_amount + (float) $data['amount'], 2); $balance = round((float) $locked->grand_total - $paid, 2);
            $locked->update(['paid_amount' => $paid, 'balance' => $balance, 'payment_status' => $balance <= 0 ? 'Paid' : 'Partial', 'updated_by' => auth()->id()]);
            $locked->invoice?->update(['paid_amount' => $paid, 'balance' => $balance, 'status' => $balance <= 0 ? 'Paid' : 'Partial']);
            $this->postPayment($locked, $payment);
        });
        $purchase->refresh();
        $this->activity->record($purchase->business_id, 'Purchases', 'Supplier payment recorded for '.$purchase->purchase_number, $purchase->id, null, [
            'amount' => $data['amount'],
            'method' => $data['method'],
            'balance' => $purchase->balance,
        ]);
        return back()->with('success', 'Supplier payment recorded and posted to accounting.');
    }

    public function processReturn(Request $request, Purchase $purchase)
    {
        $purchase = $this->scoped($purchase);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'items' => ['required', 'array', 'min:1'], 'items.*.purchase_item_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'numeric', 'min:0.001']]);
        try {
            $purchaseReturn = DB::transaction(function () use ($purchase, $data) {
            $return = PurchaseReturn::create(['business_id' => $purchase->business_id, 'purchase_id' => $purchase->id, 'supplier_id' => $purchase->supplier_id, 'created_by' => auth()->id(), 'return_number' => $this->numbers->next('purchase_return'), 'return_date' => now()->toDateString(), 'reason' => $data['reason']]);
            $total = 0;
            $returnedProducts = collect();
            foreach (collect($data['items'])->filter(fn ($line) => (float) $line['quantity'] > 0) as $line) {
                $item = $purchase->items()->lockForUpdate()->findOrFail($line['purchase_item_id']);
                $alreadyReturned = PurchaseReturnItem::where('purchase_item_id', $item->id)->sum('quantity');
                $quantity = round((float) $line['quantity'], 3);
                if ($quantity > ($item->received_quantity - $alreadyReturned)) throw ValidationException::withMessages(['items' => 'Return quantity exceeds received stock for '.$item->product_name_snapshot.'.']);
                $product = Product::where('business_id', $purchase->business_id)->lockForUpdate()->findOrFail($item->product_id);
                if ($product->stock_quantity < $quantity) throw ValidationException::withMessages(['items' => 'Return quantity cannot exceed available stock. Only '.$product->stock_quantity.' units are available.']);
                // Return the same proportion of the saved line value so item
                // discount and tax are not lost when goods are sent back.
                $lineTotal = round(((float) $item->line_total / max(0.001, (float) $item->quantity)) * $quantity, 2);
                $previous = (float) $product->stock_quantity;
                $newStock = $previous - $quantity;
                $product->update(['stock_quantity' => $newStock, 'current_stock' => $newStock]);
                $inventory = Inventory::firstOrCreate(
                    ['business_id' => $purchase->business_id, 'product_id' => $product->id],
                    ['available_stock' => $previous, 'low_stock_alert' => $product->low_stock_alert_qty ?? 10]
                );
                $inventory->update([
                    'available_stock' => $newStock,
                    'purchase_returned_stock' => (float) $inventory->purchase_returned_stock + $quantity,
                    'low_stock_alert' => $product->low_stock_alert_qty ?? 10,
                ]);
                StockMovement::create(['business_id' => $purchase->business_id, 'product_id' => $product->id, 'type' => 'purchase_return', 'quantity' => -$quantity, 'reason' => 'Purchase return '.$return->return_number, 'user_id' => auth()->id()]);
                InventoryMovement::create(['business_id' => $purchase->business_id, 'product_id' => $product->id, 'type' => 'PURCHASE_RETURN', 'quantity' => $quantity, 'previous_stock' => $previous, 'new_stock' => $newStock, 'note' => 'Purchase return '.$return->return_number, 'created_by' => auth()->id(), 'movement_date' => now()]);
                $return->items()->create(['purchase_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => $item->unit_cost, 'line_total' => $lineTotal]); $total += $lineTotal;
                $returnedProducts->push($product);
            }
            if ($total <= 0) throw ValidationException::withMessages(['items' => 'Select at least one item to return.']);
            $return->update(['total_amount' => $total]);
            $refund = max(0, $total - $purchase->balance); $newBalance = max(0, $purchase->balance - $total); $newPaid = max(0, $purchase->paid_amount - $refund);
            $purchase->update(['balance' => $newBalance, 'paid_amount' => $newPaid, 'payment_status' => $newBalance <= 0 ? 'Paid' : 'Partial']);
            $returnedProducts->unique('id')->each(fn (Product $product) => $this->productCosts->refresh($product));
            $purchase->invoice?->update(['balance' => $newBalance, 'paid_amount' => $newPaid, 'status' => 'Partially Returned']);
            $this->postReturn($purchase, $return->id, $total, $refund);
            $this->receiving->refreshReceivingStatus($purchase);
                return $return;
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors([
                'return' => 'Unable to process the return. Please try again.',
            ]);
        }

        $purchase->refresh();
        $this->activity->record($purchase->business_id, 'Inventory', 'Purchase Return Stock Reduced', $purchaseReturn->id, null, [
            'return_number' => $purchaseReturn->return_number,
            'returned_quantity' => $purchaseReturn->items()->sum('quantity'),
            'balance' => $purchase->balance,
            'status' => $purchase->status,
            'inventory_return_history_updated' => true,
            'notification_title' => 'Purchase Return Completed',
            'notification_message' => 'Purchase return '.$purchaseReturn->return_number.' has been processed successfully.',
        ]);

        return redirect()->route('business.purchase-returns.show', $purchaseReturn)->with('tradeflow_return_alert', [
            'title' => 'Purchase Return Completed',
            'message' => 'Purchase return has been processed successfully. Stock, supplier balance, payable and related accounting entries have been updated. Return No: '.$purchaseReturn->return_number,
        ]);
    }

    private function postConfirmedPurchase(Purchase $purchase): void { $this->post($purchase, (float) $purchase->grand_total, 'purchase_confirmation', $purchase->id, [['Purchases', (float) $purchase->grand_total, 0], ['Accounts Payable', 0, (float) $purchase->grand_total]]); }
    private function syncConfirmedPurchasePosting(Purchase $purchase): void
    {
        $entry = JournalEntry::where('business_id', $purchase->business_id)
            ->where('reference_type', 'purchase_confirmation')
            ->where('reference_id', $purchase->id)
            ->lockForUpdate()
            ->first();

        if (! $entry) {
            $this->postConfirmedPurchase($purchase);
            return;
        }

        $this->accounting->ensureDefaultAccounts($purchase->business_id);
        $accounts = Account::where('business_id', $purchase->business_id)
            ->whereIn('name', ['Purchases', 'Accounts Payable'])
            ->pluck('id', 'name');
        if (! isset($accounts['Purchases'], $accounts['Accounts Payable'])) {
            throw ValidationException::withMessages(['payment_account_id' => 'A required accounting account is not configured for this business.']);
        }

        $amount = (float) $purchase->grand_total;
        $entry->lines()->delete();
        $entry->lines()->createMany([
            ['account_id' => $accounts['Purchases'], 'debit' => $amount, 'credit' => 0, 'description' => 'Purchase commitment '.$purchase->purchase_number],
            ['account_id' => $accounts['Accounts Payable'], 'supplier_id' => $purchase->supplier_id, 'debit' => 0, 'credit' => $amount, 'description' => 'Purchase commitment '.$purchase->purchase_number],
        ]);
        $entry->update([
            'description' => 'Purchase confirmation '.$purchase->purchase_number,
            'entry_date' => now()->toDateString(),
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);
    }
    private function postAccountsPayable(Purchase $purchase, float $amount): void { $this->post($purchase, $amount, 'purchase_receipt', $purchase->id, [['Inventory', $amount, 0], ['Accounts Payable', 0, $amount]]); }
    private function postReceiptClearing(Purchase $purchase, float $amount): void { $this->post($purchase, $amount, 'purchase_receipt_clearing', $purchase->id, [['Inventory', $amount, 0], ['Purchases', 0, $amount]]); }
    private function postPayment(Purchase $purchase, SupplierPayment $payment): void
    {
        $cashAccount = $payment->account_id
            ? Account::where('business_id', $purchase->business_id)->find($payment->account_id)?->name
            : ($payment->method === 'Bank Transfer' || $payment->method === 'Cheque' ? 'Bank' : 'Cash');
        $debitAccount = $payment->is_advance ? 'Supplier Advances' : 'Accounts Payable';
        $this->post($purchase, (float) $payment->amount, 'supplier_payment', $payment->id, [[$debitAccount, (float) $payment->amount, 0], [$cashAccount ?: 'Cash', 0, (float) $payment->amount]]);
    }
    private function postReturn(Purchase $purchase, int $returnId, float $amount, float $refund): void { $lines = [['Accounts Payable', min($amount, $purchase->balance + $amount), 0], ['Inventory', 0, $amount]]; if ($refund > 0) $lines = [['Accounts Payable', $amount - $refund, 0], ['Cash', $refund, 0], ['Inventory', 0, $amount]]; $this->post($purchase, $amount, 'purchase_return', $returnId, $lines); }
    private function post(Purchase $purchase, float $amount, string $referenceType, int $referenceId, array $lines): void
    {
        if ($amount <= 0 || JournalEntry::where('business_id', $purchase->business_id)->where('reference_type', $referenceType)->where('reference_id', $referenceId)->exists()) return;
        $this->accounting->ensureDefaultAccounts($purchase->business_id);
        $accounts = Account::where('business_id', $purchase->business_id)->whereIn('name', collect($lines)->pluck(0))->pluck('id', 'name');
        if (collect($lines)->contains(fn ($line) => empty($accounts[$line[0]]))) throw ValidationException::withMessages(['payment_account_id' => 'A required accounting account is not configured for this business.']);
        $this->accounting->post($purchase->business_id, ['purchase_id' => $purchase->id, 'voucher_number' => strtoupper(substr($referenceType, 0, 3)).'-'.$purchase->id.'-'.now()->format('His'), 'entry_date' => now()->toDateString(), 'reference_type' => $referenceType, 'reference_id' => $referenceId, 'description' => ucfirst(str_replace('_', ' ', $referenceType)).' '.$purchase->purchase_number], collect($lines)->map(fn ($line) => ['account_id' => $accounts[$line[0]], 'supplier_id' => in_array($line[0], ['Accounts Payable', 'Supplier Advances'], true) ? $purchase->supplier_id : null, 'debit' => $line[1], 'credit' => $line[2]])->all());
    }

    private function reversePostings(Purchase $purchase, string $referenceType, int $referenceId): void
    {
        JournalEntry::where('business_id', $purchase->business_id)->where('reference_type', $referenceType)->where('reference_id', $referenceId)->with('lines')->get()->each(function (JournalEntry $entry) use ($purchase): void {
            if (JournalEntry::where('business_id', $purchase->business_id)->where('reference_type', 'purchase_cancellation')->where('reference_id', $entry->id)->exists()) return;
            $this->accounting->post($purchase->business_id, [
                'voucher_number' => 'CAN-'.$entry->id.'-'.now()->format('His'),
                'entry_date' => now()->toDateString(),
                'reference_type' => 'purchase_cancellation',
                'reference_id' => $entry->id,
                'description' => 'Cancellation of '.$purchase->purchase_number,
            ], $entry->lines->map(fn ($line) => ['account_id' => $line->account_id, 'supplier_id' => $purchase->supplier_id, 'debit' => $line->credit, 'credit' => $line->debit])->all());
        });
    }

    private function ensureActionPermission(string $permission): void
    {
        abort_unless(app(CompanyPermissionService::class)->allowsUser(auth()->user(), $permission), 403);
    }
    private function businessId(): int { return (int) auth()->user()->business_id; }
    private function scoped(Purchase $purchase): Purchase { abort_unless($purchase->business_id === $this->businessId(), 404); return $purchase; }
}
