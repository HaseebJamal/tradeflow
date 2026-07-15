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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function __construct(private AccountingService $accounting, private BusinessActivityService $activity) {}

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
                ->orWhere('supplier_invoice_number', 'like', '%'.$request->search.'%')
                ->orWhere('notes', 'like', '%'.$request->search.'%')))
            ->where('purchase_date', '>=', Carbon::parse($filters['date_from'], config('app.timezone'))->startOfDay())
            ->where('purchase_date', '<=', Carbon::parse($filters['date_to'], config('app.timezone'))->endOfDay())
            ->latest('purchase_date')->paginate(20)->withQueryString();

        return view('business.purchases.index', [
            'purchases' => $purchases,
            'suppliers' => Supplier::where('business_id', $businessId)->where('status', 'Active')->orderBy('supplier_name')->get(),
            'products' => $request->boolean('create') ? Product::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get() : collect(),
            'showPurchaseCreate' => $request->boolean('create'),
        ]);
    }

    public function create()
    {
        return redirect()->route('business.purchases.index', ['create' => 1]);
    }

    /** Resolve a PO, supplier invoice, or exact reference for scanner input. */
    public function lookup(Request $request)
    {
        $code = trim((string) $request->validate(['code' => ['required', 'string', 'max:120']])['code']);
        $purchase = Purchase::where('business_id', $this->businessId())
            ->where(fn ($query) => $query->where('purchase_number', $code)
                ->orWhere('supplier_invoice_number', $code)
                ->orWhere('notes', $code))
            ->first();

        return response()->json([
            'found' => (bool) $purchase,
            'url' => $purchase ? route('business.purchases.show', $purchase) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer'], 'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'purchase_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'], 'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'], 'tax_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        $businessId = $this->businessId();

        $purchase = DB::transaction(function () use ($data, $businessId) {
            $supplier = Supplier::where('business_id', $businessId)->where('status', 'Active')->findOrFail($data['supplier_id']);
            $lines = collect($data['items'])->groupBy('product_id')->map(fn ($items, $productId) => [
                'product_id' => (int) $productId, 'quantity' => $items->sum('quantity'), 'unit_cost' => (float) $items->last()['unit_cost'],
            ])->values();
            $subtotal = 0;
            $prepared = [];
            foreach ($lines as $line) {
                $product = Product::where('business_id', $businessId)->findOrFail($line['product_id']);
                $lineTotal = round($line['quantity'] * $line['unit_cost'], 2);
                $subtotal += $lineTotal;
                $prepared[] = compact('product', 'lineTotal') + $line;
            }
            $discount = min($subtotal, (float) ($data['discount_amount'] ?? 0));
            $tax = (float) ($data['tax_amount'] ?? 0);
            $total = round($subtotal - $discount + $tax, 2);
            $purchase = Purchase::create([
                'business_id' => $businessId, 'supplier_id' => $supplier->id, 'created_by' => auth()->id(),
                'purchase_number' => 'PO-'.now()->format('ymdHis').'-'.str_pad((string) (Purchase::where('business_id', $businessId)->count() + 1), 3, '0', STR_PAD_LEFT),
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null, 'status' => 'Ordered', 'purchase_date' => $data['purchase_date'],
                'subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax, 'grand_total' => $total, 'balance' => $total, 'notes' => $data['notes'] ?? null,
            ]);
            foreach ($prepared as $line) {
                $purchase->items()->create(['product_id' => $line['product_id'], 'product_name_snapshot' => $line['product']->name, 'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost'], 'line_total' => $line['lineTotal']]);
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

    public function receive(Purchase $purchase)
    {
        $purchase = $this->scoped($purchase);
        if (in_array($purchase->status, ['Received', 'Returned'], true)) return back()->withErrors(['purchase' => 'This purchase has already been fully received.']);

        DB::transaction(function () use ($purchase) {
            $receivedValue = 0;
            foreach ($purchase->items()->lockForUpdate()->get() as $item) {
                $quantity = $item->quantity - $item->received_quantity;
                if ($quantity <= 0) continue;
                $product = Product::where('business_id', $purchase->business_id)->lockForUpdate()->findOrFail($item->product_id);
                $previous = (int) $product->stock_quantity;
                $product->update(['stock_quantity' => $previous + $quantity, 'purchase_cost' => $item->unit_cost]);
                Inventory::updateOrCreate(['business_id' => $purchase->business_id, 'product_id' => $product->id], ['available_stock' => $previous + $quantity, 'low_stock_alert' => $product->low_stock_alert_qty ?? 10]);
                StockMovement::create(['business_id' => $purchase->business_id, 'product_id' => $product->id, 'type' => 'purchased', 'quantity' => $quantity, 'reason' => 'Purchase receipt '.$purchase->purchase_number, 'user_id' => auth()->id()]);
                InventoryMovement::create(['business_id' => $purchase->business_id, 'product_id' => $product->id, 'type' => 'PURCHASED', 'quantity' => $quantity, 'previous_stock' => $previous, 'new_stock' => $previous + $quantity, 'note' => 'Goods received for '.$purchase->purchase_number, 'created_by' => auth()->id(), 'movement_date' => now()]);
                $item->update(['received_quantity' => $item->received_quantity + $quantity]);
                $receivedValue += (float) $item->line_total;
            }
            if ($receivedValue <= 0) throw ValidationException::withMessages(['purchase' => 'There are no outstanding goods to receive.']);
            $purchase->update(['status' => 'Received', 'received_at' => now()]);
            PurchaseInvoice::updateOrCreate(['purchase_id' => $purchase->id], ['business_id' => $purchase->business_id, 'supplier_id' => $purchase->supplier_id, 'invoice_number' => $purchase->supplier_invoice_number ?: 'PINV-'.now()->format('ymdHis'), 'invoice_date' => now()->toDateString(), 'grand_total' => $purchase->grand_total, 'paid_amount' => $purchase->paid_amount, 'balance' => $purchase->balance, 'status' => 'Received']);
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
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01', 'max:'.$purchase->balance], 'method' => ['required', 'in:Cash,Bank Transfer,JazzCash Manual,Easypaisa Manual,Cheque'], 'reference_number' => ['nullable', 'string', 'max:255'], 'payment_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:1000']]);
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
        DB::transaction(function () use ($purchase, $data) {
            $return = PurchaseReturn::create(['business_id' => $purchase->business_id, 'purchase_id' => $purchase->id, 'supplier_id' => $purchase->supplier_id, 'created_by' => auth()->id(), 'return_number' => 'PR-'.now()->format('ymdHis').'-'.$purchase->id, 'return_date' => now()->toDateString(), 'reason' => $data['reason']]);
            $total = 0;
            foreach (collect($data['items'])->filter(fn ($line) => (int) $line['quantity'] > 0) as $line) {
                $item = $purchase->items()->lockForUpdate()->findOrFail($line['purchase_item_id']);
                $alreadyReturned = PurchaseReturnItem::where('purchase_item_id', $item->id)->sum('quantity');
                $quantity = (int) $line['quantity'];
                if ($quantity > ($item->received_quantity - $alreadyReturned)) throw ValidationException::withMessages(['items' => 'Return quantity exceeds received stock for '.$item->product_name_snapshot.'.']);
                $product = Product::where('business_id', $purchase->business_id)->lockForUpdate()->findOrFail($item->product_id);
                if ($product->stock_quantity < $quantity) throw ValidationException::withMessages(['items' => 'Insufficient stock. Only '.$product->stock_quantity.' units are available.']);
                $previous = (int) $product->stock_quantity; $lineTotal = round($quantity * $item->unit_cost, 2);
                $product->decrement('stock_quantity', $quantity);
                Inventory::updateOrCreate(['business_id' => $purchase->business_id, 'product_id' => $product->id], ['available_stock' => $previous - $quantity, 'low_stock_alert' => $product->low_stock_alert_qty ?? 10]);
                StockMovement::create(['business_id' => $purchase->business_id, 'product_id' => $product->id, 'type' => 'purchase_return', 'quantity' => -$quantity, 'reason' => 'Purchase return '.$return->return_number, 'user_id' => auth()->id()]);
                InventoryMovement::create(['business_id' => $purchase->business_id, 'product_id' => $product->id, 'type' => 'PURCHASE_RETURN', 'quantity' => -$quantity, 'previous_stock' => $previous, 'new_stock' => $previous - $quantity, 'note' => 'Purchase return '.$return->return_number, 'created_by' => auth()->id(), 'movement_date' => now()]);
                $return->items()->create(['purchase_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => $item->unit_cost, 'line_total' => $lineTotal]); $total += $lineTotal;
            }
            if ($total <= 0) throw ValidationException::withMessages(['items' => 'Select at least one item to return.']);
            $return->update(['total_amount' => $total]);
            $refund = max(0, $total - $purchase->balance); $newBalance = max(0, $purchase->balance - $total); $newPaid = max(0, $purchase->paid_amount - $refund);
            $purchase->update(['balance' => $newBalance, 'paid_amount' => $newPaid, 'payment_status' => $newBalance <= 0 ? 'Paid' : 'Partial', 'status' => 'Partially Returned']);
            $purchase->invoice?->update(['balance' => $newBalance, 'paid_amount' => $newPaid, 'status' => 'Partially Returned']);
            $this->postReturn($purchase, $return->id, $total, $refund);
        });
        $purchase->refresh();
        $this->activity->record($purchase->business_id, 'Purchases', 'Purchase return processed for '.$purchase->purchase_number, $purchase->id, null, [
            'balance' => $purchase->balance,
            'status' => $purchase->status,
        ]);
        return back()->with('success', 'Purchase return saved, stock reversed, and accounting updated.');
    }

    private function postAccountsPayable(Purchase $purchase, float $amount): void { $this->post($purchase, $amount, 'purchase_receipt', $purchase->id, [['Inventory', $amount, 0], ['Accounts Payable', 0, $amount]]); }
    private function postPayment(Purchase $purchase, float $amount, string $method): void { $cashAccount = $method === 'Bank Transfer' ? 'Bank' : 'Cash'; $this->post($purchase, $amount, 'supplier_payment', $purchase->id, [['Accounts Payable', $amount, 0], [$cashAccount, 0, $amount]]); }
    private function postReturn(Purchase $purchase, int $returnId, float $amount, float $refund): void { $lines = [['Accounts Payable', min($amount, $purchase->balance + $amount), 0], ['Inventory', 0, $amount]]; if ($refund > 0) $lines = [['Accounts Payable', $amount - $refund, 0], ['Cash', $refund, 0], ['Inventory', 0, $amount]]; $this->post($purchase, $amount, 'purchase_return', $returnId, $lines); }
    private function post(Purchase $purchase, float $amount, string $referenceType, int $referenceId, array $lines): void { $this->accounting->ensureDefaultAccounts($purchase->business_id); $accounts = Account::where('business_id', $purchase->business_id)->whereIn('name', collect($lines)->pluck(0))->pluck('id', 'name'); $this->accounting->post($purchase->business_id, ['voucher_number' => strtoupper(substr($referenceType, 0, 3)).'-'.$purchase->id.'-'.now()->format('His'), 'entry_date' => now()->toDateString(), 'reference_type' => $referenceType, 'reference_id' => $referenceId, 'description' => ucfirst(str_replace('_', ' ', $referenceType)).' '.$purchase->purchase_number], collect($lines)->map(fn ($line) => ['account_id' => $accounts[$line[0]], 'supplier_id' => $purchase->supplier_id, 'debit' => $line[1], 'credit' => $line[2]])->all()); }
    private function businessId(): int { return (int) auth()->user()->business_id; }
    private function scoped(Purchase $purchase): Purchase { abort_unless($purchase->business_id === $this->businessId(), 404); return $purchase; }
}
