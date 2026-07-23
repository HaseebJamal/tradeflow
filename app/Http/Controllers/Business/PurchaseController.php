<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Inventory;
use App\Models\InventoryMovement;
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
        $purchases = Purchase::with(['supplier', 'invoice'])->where('business_id', $businessId)
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($inner) => $inner
                ->where('purchase_number', 'like', '%'.$request->search.'%')
                ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', '%'.$request->search.'%'))
                ->orWhereHas('supplier', fn ($supplier) => $supplier->where('supplier_name', 'like', '%'.$request->search.'%'))
                ->orWhere('notes', 'like', '%'.$request->search.'%')))
            ->where('purchase_date', '>=', Carbon::parse($filters['date_from'], config('app.timezone'))->startOfDay())
            ->where('purchase_date', '<=', Carbon::parse($filters['date_to'], config('app.timezone'))->endOfDay())
            ->latest('purchase_date')->paginate(12)->withQueryString();

        $suppliers = Supplier::where('business_id', $businessId)->where('status', 'Active')->orderBy('supplier_name')->get();
        $hasSuppliers = $suppliers->isNotEmpty();

        return view('business.purchases.index', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'products' => $request->boolean('create') && $hasSuppliers ? Product::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get() : collect(),
            'showPurchaseCreate' => $request->boolean('create'),
            'hasSuppliers' => $hasSuppliers,
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
        if (! Supplier::where('business_id', $businessId)->where('status', 'Active')->exists()) {
            throw ValidationException::withMessages([
                'supplier_id' => 'You must create at least one supplier before creating a purchase.',
            ]);
        }

        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'purchase_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'], 'items.*.unit_cost' => ['required', 'integer', 'min:0'],
            'items.*.selling_price' => ['required', 'integer', 'min:0'],
            'items.*.discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'items.*.discount_value' => ['nullable', 'integer', 'min:0'],
            'items.*.tax_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'items.*.tax_value' => ['nullable', 'integer', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'integer', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'integer', 'min:0'],
        ]);
        foreach ($data['items'] as $index => $item) {
            if ((int) $item['selling_price'] <= (int) $item['unit_cost']) {
                throw ValidationException::withMessages([
                    "items.{$index}.selling_price" => 'Selling Price must be greater than Purchase Price.',
                ]);
            }
        }

        $purchase = DB::transaction(function () use ($data, $businessId) {
            $supplier = Supplier::where('business_id', $businessId)
                ->where('status', 'Active')
                ->lockForUpdate()
                ->find($data['supplier_id']);
            if (! $supplier) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Select an active supplier before creating a purchase.',
                ]);
            }
            $lines = collect($data['items'])->groupBy('product_id')->map(fn ($items, $productId) => [
                'product_id' => (int) $productId,
                'quantity' => (int) $items->sum('quantity'),
                'unit_cost' => (float) $items->last()['unit_cost'],
                'selling_price' => (float) $items->last()['selling_price'],
                'discount_type' => $items->last()['discount_type'] ?? 'fixed',
                'discount_value' => (int) ($items->last()['discount_value'] ?? $items->last()['discount_amount'] ?? 0),
                'tax_type' => $items->last()['tax_type'] ?? 'fixed',
                'tax_value' => (int) ($items->last()['tax_value'] ?? $items->last()['tax_amount'] ?? 0),
            ])->values();
            $subtotal = 0;
            $discount = 0;
            $tax = 0;
            $prepared = [];
            foreach ($lines as $line) {
                $product = Product::where('business_id', $businessId)->findOrFail($line['product_id']);
                ['subtotal' => $lineSubtotal, 'discount' => $lineDiscount, 'tax' => $lineTax, 'total' => $lineTotal] = $this->lineAmounts($line);
                $subtotal += $lineSubtotal;
                $discount += $lineDiscount;
                $tax += $lineTax;
                $prepared[] = compact('product', 'lineTotal', 'lineDiscount', 'lineTax') + $line;
            }
            $total = round($subtotal - $discount + $tax, 2);
            $purchase = Purchase::create([
                'business_id' => $businessId, 'supplier_id' => $supplier->id, 'created_by' => auth()->id(),
                'purchase_number' => $this->numbers->next('purchase'),
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null, 'status' => 'Ordered', 'purchase_date' => $data['purchase_date'],
                'subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax, 'grand_total' => $total, 'balance' => $total, 'notes' => $data['notes'] ?? null,
            ]);
            foreach ($prepared as $line) {
                $purchase->items()->create([
                    'product_id' => $line['product_id'],
                    'product_name_snapshot' => $line['product']->name,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'selling_price' => $line['selling_price'],
                    'discount_type' => $line['discount_type'],
                    'discount_value' => $line['discount_value'],
                    'discount_amount' => $line['lineDiscount'],
                    'tax_type' => $line['tax_type'],
                    'tax_value' => $line['tax_value'],
                    'tax_amount' => $line['lineTax'],
                    'line_total' => $line['lineTotal'],
                ]);
            }
            return $purchase;
        });

        $this->activity->record($businessId, 'Purchases', 'Purchase order created: '.$purchase->purchase_number, $purchase->id, null, [
            'supplier_id' => $purchase->supplier_id,
            'grand_total' => $purchase->grand_total,
            'status' => $purchase->status,
        ]);

        return redirect()->route('business.purchases.show', $purchase)->with('success', 'Purchase order saved. Receive goods when they arrive.');
    }

    public function show(Purchase $purchase)
    {
        return view('business.purchases.show', ['purchase' => $this->scoped($purchase)->load(['supplier', 'items.product', 'invoice', 'payments', 'returns.items'])]);
    }

    private function lineAmounts(array $line): array
    {
        $subtotal = round((int) $line['quantity'] * (float) $line['unit_cost'], 2);
        $discountValue = (int) ($line['discount_value'] ?? 0);
        $taxValue = (int) ($line['tax_value'] ?? 0);

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
        if (in_array($purchase->status, ['Received', 'Returned'], true)) return back()->withErrors(['purchase' => 'This purchase has already been fully received.']);

        DB::transaction(function () use ($purchase) {
            $invoiceNumber = $purchase->invoice?->invoice_number ?? $this->numbers->next('supplier_invoice');

            $receivedValue = 0;
            $receivedProducts = collect();
            foreach ($purchase->items()->lockForUpdate()->get() as $item) {
                $quantity = $item->quantity - $item->received_quantity;
                if ($quantity <= 0) continue;
                $product = Product::where('business_id', $purchase->business_id)->lockForUpdate()->findOrFail($item->product_id);
                $previous = (int) $product->stock_quantity;
                $product->update([
                    'stock_quantity' => $previous + $quantity,
                    'retail_price' => $item->selling_price,
                    'wholesale_price' => $item->selling_price,
                ]);
                Inventory::updateOrCreate(['business_id' => $purchase->business_id, 'product_id' => $product->id], ['available_stock' => $previous + $quantity, 'low_stock_alert' => $product->low_stock_alert_qty ?? 10]);
                StockMovement::create(['business_id' => $purchase->business_id, 'product_id' => $product->id, 'type' => 'purchased', 'quantity' => $quantity, 'reason' => 'Purchase receipt '.$purchase->purchase_number, 'user_id' => auth()->id()]);
                InventoryMovement::create(['business_id' => $purchase->business_id, 'product_id' => $product->id, 'type' => 'PURCHASED', 'quantity' => $quantity, 'previous_stock' => $previous, 'new_stock' => $previous + $quantity, 'note' => 'Goods received for '.$purchase->purchase_number, 'created_by' => auth()->id(), 'movement_date' => now()]);
                $item->update(['received_quantity' => $item->received_quantity + $quantity]);
                $receivedProducts->push($product);
                $receivedValue += (float) $item->line_total;
            }
            if ($receivedValue <= 0) throw ValidationException::withMessages(['purchase' => 'There are no outstanding goods to receive.']);
            $purchase->update(['status' => 'Received', 'received_at' => now()]);
            $receivedProducts->unique('id')->each(fn (Product $product) => $this->productCosts->refresh($product));
            PurchaseInvoice::updateOrCreate(['purchase_id' => $purchase->id], ['business_id' => $purchase->business_id, 'supplier_id' => $purchase->supplier_id, 'invoice_number' => $invoiceNumber, 'invoice_date' => now()->toDateString(), 'grand_total' => $purchase->grand_total, 'paid_amount' => $purchase->paid_amount, 'balance' => $purchase->balance, 'status' => 'Received']);
            $this->postAccountsPayable($purchase, $purchase->grand_total);
        });

        $this->activity->record($purchase->business_id, 'Purchases', 'Goods received for '.$purchase->purchase_number, $purchase->id, null, [
            'grand_total' => $purchase->grand_total,
            'status' => 'Received',
        ]);

        return back()->with('success', 'Goods received, inventory updated, and supplier invoice posted.');
    }

    public function pay(Request $request, Purchase $purchase)
    {
        $purchase = $this->scoped($purchase);
        $data = $request->validate(['amount' => ['required', 'integer', 'min:1', 'max:'.$purchase->balance], 'method' => ['required', 'in:Cash,Bank Transfer,JazzCash Manual,Easypaisa Manual,Cheque'], 'reference_number' => ['nullable', 'string', 'max:255'], 'payment_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($purchase, $data) {
            SupplierPayment::create($data + ['business_id' => $purchase->business_id, 'supplier_id' => $purchase->supplier_id, 'purchase_id' => $purchase->id, 'created_by' => auth()->id()]);
            $paid = round($purchase->paid_amount + $data['amount'], 2); $balance = round($purchase->grand_total - $paid, 2);
            $purchase->update(['paid_amount' => $paid, 'balance' => $balance, 'payment_status' => $balance <= 0 ? 'Paid' : 'Partial']);
            $purchase->invoice?->update(['paid_amount' => $paid, 'balance' => $balance, 'status' => $balance <= 0 ? 'Paid' : 'Partial']);
            $this->postPayment($purchase, (float) $data['amount'], $data['method']);
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
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000'], 'items' => ['required', 'array', 'min:1'], 'items.*.purchase_item_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'integer', 'min:1']]);
        try {
            $purchaseReturn = DB::transaction(function () use ($purchase, $data) {
            $return = PurchaseReturn::create(['business_id' => $purchase->business_id, 'purchase_id' => $purchase->id, 'supplier_id' => $purchase->supplier_id, 'created_by' => auth()->id(), 'return_number' => $this->numbers->next('purchase_return'), 'return_date' => now()->toDateString(), 'reason' => $data['reason']]);
            $total = 0;
            $returnedProducts = collect();
            foreach (collect($data['items'])->filter(fn ($line) => (int) $line['quantity'] > 0) as $line) {
                $item = $purchase->items()->lockForUpdate()->findOrFail($line['purchase_item_id']);
                $alreadyReturned = PurchaseReturnItem::where('purchase_item_id', $item->id)->sum('quantity');
                $quantity = (int) $line['quantity'];
                if ($quantity > ($item->received_quantity - $alreadyReturned)) throw ValidationException::withMessages(['items' => 'Return quantity exceeds received stock for '.$item->product_name_snapshot.'.']);
                $product = Product::where('business_id', $purchase->business_id)->lockForUpdate()->findOrFail($item->product_id);
                if ($product->stock_quantity < $quantity) throw ValidationException::withMessages(['items' => 'Return quantity cannot exceed available stock. Only '.$product->stock_quantity.' units are available.']);
                // Return the same proportion of the saved line value so item
                // discount and tax are not lost when goods are sent back.
                $lineTotal = round(((float) $item->line_total / max(1, (int) $item->quantity)) * $quantity, 2);
                $previous = (int) $product->stock_quantity;
                $newStock = $previous - $quantity;
                $product->update(['stock_quantity' => $newStock, 'current_stock' => $newStock]);
                $inventory = Inventory::firstOrCreate(
                    ['business_id' => $purchase->business_id, 'product_id' => $product->id],
                    ['available_stock' => $previous, 'low_stock_alert' => $product->low_stock_alert_qty ?? 10]
                );
                $inventory->update([
                    'available_stock' => $newStock,
                    'purchase_returned_stock' => (int) $inventory->purchase_returned_stock + $quantity,
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
            $purchase->update(['balance' => $newBalance, 'paid_amount' => $newPaid, 'payment_status' => $newBalance <= 0 ? 'Paid' : 'Partial', 'status' => 'Partially Returned']);
            $returnedProducts->unique('id')->each(fn (Product $product) => $this->productCosts->refresh($product));
            $purchase->invoice?->update(['balance' => $newBalance, 'paid_amount' => $newPaid, 'status' => 'Partially Returned']);
            $this->postReturn($purchase, $return->id, $total, $refund);
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

    private function postAccountsPayable(Purchase $purchase, float $amount): void { $this->post($purchase, $amount, 'purchase_receipt', $purchase->id, [['Inventory', $amount, 0], ['Accounts Payable', 0, $amount]]); }
    private function postPayment(Purchase $purchase, float $amount, string $method): void { $cashAccount = $method === 'Bank Transfer' ? 'Bank' : 'Cash'; $this->post($purchase, $amount, 'supplier_payment', $purchase->id, [['Accounts Payable', $amount, 0], [$cashAccount, 0, $amount]]); }
    private function postReturn(Purchase $purchase, int $returnId, float $amount, float $refund): void { $lines = [['Accounts Payable', min($amount, $purchase->balance + $amount), 0], ['Inventory', 0, $amount]]; if ($refund > 0) $lines = [['Accounts Payable', $amount - $refund, 0], ['Cash', $refund, 0], ['Inventory', 0, $amount]]; $this->post($purchase, $amount, 'purchase_return', $returnId, $lines); }
    private function post(Purchase $purchase, float $amount, string $referenceType, int $referenceId, array $lines): void { $this->accounting->ensureDefaultAccounts($purchase->business_id); $accounts = Account::where('business_id', $purchase->business_id)->whereIn('name', collect($lines)->pluck(0))->pluck('id', 'name'); $this->accounting->post($purchase->business_id, ['voucher_number' => strtoupper(substr($referenceType, 0, 3)).'-'.$purchase->id.'-'.now()->format('His'), 'entry_date' => now()->toDateString(), 'reference_type' => $referenceType, 'reference_id' => $referenceId, 'description' => ucfirst(str_replace('_', ' ', $referenceType)).' '.$purchase->purchase_number], collect($lines)->map(fn ($line) => ['account_id' => $accounts[$line[0]], 'supplier_id' => $purchase->supplier_id, 'debit' => $line[1], 'credit' => $line[2]])->all()); }
    private function businessId(): int { return (int) auth()->user()->business_id; }
    private function scoped(Purchase $purchase): Purchase { abort_unless($purchase->business_id === $this->businessId(), 404); return $purchase; }
}
