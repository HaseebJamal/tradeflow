<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosRegister;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Services\AccountingService;
use App\Services\BusinessActivityService;
use App\Services\CompanyPermissionService;
use App\Services\DocumentNumberService;
use App\Services\ProductBatchService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnController extends Controller
{
    public function __construct(
        private AccountingService $accounting,
        private BusinessActivityService $activity,
        private CompanyPermissionService $permissions,
        private DocumentNumberService $numbers,
        private ProductBatchService $batches,
    ) {}

    public function index(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'clear' => ['nullable', 'boolean'],
        ]);
        if (! $request->boolean('clear')) {
            $filters['date_from'] ??= now(config('app.timezone'))->toDateString();
            $filters['date_to'] ??= now(config('app.timezone'))->toDateString();
        }
        $returns = SalesReturn::with(['order.invoice', 'customer', 'items.orderItem.product', 'processor'])
            ->where('business_id', $businessId)
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->where(fn ($inner) => $inner
                ->where('return_number', 'like', "%{$value}%")
                ->orWhereHas('order', fn ($orders) => $orders->where('order_number', 'like', "%{$value}%"))
                ->orWhereHas('order.invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', "%{$value}%"))))
            ->when(($filters['date_from'] ?? null) && ($filters['date_to'] ?? null), fn ($query) => $query->whereBetween('returned_at', [
                Carbon::parse($filters['date_from'], config('app.timezone'))->startOfDay(),
                Carbon::parse($filters['date_to'], config('app.timezone'))->endOfDay(),
            ]))
            ->latest('returned_at')->paginate(10)->withQueryString();

        $references = collect()
            ->merge(Order::where('business_id', $businessId)->whereNotNull('order_number')->latest('order_date')->pluck('order_number'))
            ->merge(Order::where('business_id', $businessId)->whereHas('invoice')->with('invoice:id,order_id,invoice_number')->latest('order_date')->get()->pluck('invoice.invoice_number'))
            ->merge(SalesReturn::where('business_id', $businessId)->latest('returned_at')->pluck('return_number'))
            ->filter()->unique()->values();

        return view('business.sales-returns.index', compact('returns', 'references', 'filters'));
    }

    public function create(Request $request)
    {
        $orders = $this->eligibleOrders((int) $request->user()->business_id)
            ->get()
            ->filter(fn (Order $order) => $this->remainingReturnableQuantity($order) > 0)
            ->values();

        $order = null;
        if ($request->filled('order_id')) {
            $order = $this->eligibleOrders((int) $request->user()->business_id)
                ->whereKey($request->integer('order_id'))
                ->firstOrFail();
        }

        return view('business.sales-returns.create', compact('orders', 'order'));
    }

    public function start(Request $request)
    {
        $data = $request->validate(['order_id' => ['required', 'integer']]);
        $order = $this->eligibleOrders((int) $request->user()->business_id)
            ->whereKey($data['order_id'])
            ->firstOrFail();
        if ($this->remainingReturnableQuantity($order) < 1) {
            return back()->withErrors(['order_id' => 'This sale has no remaining items available for return.']);
        }

        $orders = $this->eligibleOrders((int) $request->user()->business_id)
            ->get()
            ->filter(fn (Order $candidate) => $this->remainingReturnableQuantity($candidate) > 0)
            ->values();

        return view('business.sales-returns.create', compact('orders', 'order'));
    }

    public function process(Request $request, Order $order)
    {
        $order = $this->eligibleOrderOrFail($request, $order);

        return view('business.sales-returns.return', compact('order'));
    }

    public function store(Request $request, Order $order)
    {
        $order = $this->eligibleOrderOrFail($request, $order);
        $data = $request->validate([
            'refund_method' => ['required', 'in:Cash,Store Credit,Bank Transfer'],
            'reason' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if (! $this->permissions->allowsUser($request->user(), 'sales_returns.process')) {
            abort(403);
        }

        // Cash returns are associated with the cashier's active shift. Other
        // refund methods retain the association for traceability but do not
        // change that shift's expected cash.
        $registerId = PosRegister::query()
            ->where('business_id', $order->business_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'Open')
            ->latest('opened_at')
            ->value('id');

        try {
            $salesReturn = DB::transaction(function () use ($order, $data, $registerId) {
                $return = SalesReturn::create([
                    'business_id' => $order->business_id,
                    'return_number' => $this->numbers->next((int) $order->business_id, 'sales_return'),
                    'order_id' => $order->id,
                    'pos_register_id' => $registerId,
                    'customer_id' => $order->customer_id,
                    'processed_by' => auth()->id(),
                    'refund_method' => $data['refund_method'],
                    'reason' => $data['reason'],
                    'returned_at' => now(),
                ]);
                $refund = 0.0;
                $cost = 0.0;
                $returnedCount = 0;

                foreach ($data['items'] as $line) {
                    $item = OrderItem::where('order_id', $order->id)->lockForUpdate()->findOrFail($line['order_item_id']);
                    $alreadyReturned = (int) $item->salesReturnItems()->sum('quantity');
                    $remainingReturnable = max(0, (int) $item->quantity - $alreadyReturned);
                    $quantity = (int) $line['quantity'];
                    if ($quantity > $remainingReturnable) {
                        throw ValidationException::withMessages([
                            'items' => 'Return quantity cannot exceed available items. Only '.$remainingReturnable.' units are available.',
                        ]);
                    }

                    $unitRefund = (float) ($item->line_total ?? $item->total ?? 0) / max(1, (int) $item->quantity);
                    $lineRefund = round($unitRefund * $quantity, 2);
                    $return->items()->create([
                        'order_item_id' => $item->id,
                        'quantity' => $quantity,
                        'refund_total' => $lineRefund,
                    ]);

                    $product = Product::withTrashed()
                        ->where('business_id', $order->business_id)
                        ->lockForUpdate()
                        ->find($item->product_id);
                    if ($product) {
                        $this->batches->restoreSaleReturn($item, (float) $quantity);
                        $previousStock = (int) $product->stock_quantity;
                        $newStock = $previousStock + $quantity;
                        $product->update(['stock_quantity' => $newStock, 'current_stock' => $newStock]);
                        $inventory = Inventory::firstOrCreate(
                            ['business_id' => $order->business_id, 'product_id' => $product->id],
                            ['available_stock' => $previousStock, 'low_stock_alert' => $product->low_stock_alert_qty ?? 10]
                        );
                        $inventory->update([
                            'available_stock' => $newStock,
                            'sold_stock' => max(0, (int) $inventory->sold_stock - $quantity),
                            'sales_returned_stock' => (int) $inventory->sales_returned_stock + $quantity,
                            'low_stock_alert' => $product->low_stock_alert_qty ?? 10,
                        ]);
                        StockMovement::create([
                            'business_id' => $order->business_id, 'product_id' => $product->id,
                            'type' => 'sales_return', 'quantity' => $quantity, 'reason' => 'Sales return',
                            'note' => 'Sales return '.$return->return_number, 'user_id' => auth()->id(), 'created_by' => auth()->id(),
                        ]);
                        InventoryMovement::create([
                            'business_id' => $order->business_id, 'product_id' => $product->id,
                            'type' => 'SALES_RETURN', 'quantity' => $quantity, 'previous_stock' => $previousStock,
                            'new_stock' => $newStock, 'note' => 'Sales return '.$return->return_number,
                            'created_by' => auth()->id(), 'movement_date' => now(),
                        ]);
                    }

                    $refund += $lineRefund;
                    $cost += round((float) ($item->purchase_cost_snapshot ?? 0) * $quantity, 2);
                    $returnedCount += $quantity;
                }

                if ($returnedCount === 0) {
                    throw ValidationException::withMessages(['items' => 'Select at least one item to return.']);
                }

                $return->update(['refund_amount' => $refund]);
                if ($data['refund_method'] === 'Store Credit' && $order->customer) {
                    $order->customer->update(['current_balance' => max(0, (float) $order->customer->current_balance - $refund)]);
                }
                $this->postReturnAccounting($order, $return, $cost);
                $fullyReturned = $order->items->sum('quantity') === $order->items->sum(fn ($item) => $item->salesReturnItems()->sum('quantity'));
                $order->update(['status' => $fullyReturned ? 'Returned' : 'Partially Returned']);

                return $return;
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput()->withErrors(['return' => 'Unable to process the return. Please try again.']);
        }

        $this->activity->record($salesReturn->business_id, 'Inventory', 'Sales Return Stock Restored', $salesReturn->id, null, [
            'return_number' => $salesReturn->return_number,
            'returned_quantity' => $salesReturn->items()->sum('quantity'),
            'refund_amount' => $salesReturn->refund_amount,
            'inventory_return_history_updated' => true,
            'notification_title' => 'Sales Return Completed',
            'notification_message' => 'Sales return '.$salesReturn->return_number.' has been processed successfully.',
        ]);

        return redirect()->route('business.sales.returns.create')->with('tradeflow_return_alert', [
            'title' => 'Sales Return Completed',
            'message' => 'Sales return '.$salesReturn->return_number.' has been processed successfully. You can process another return now.',
        ]);
    }

    public function show(Request $request, SalesReturn $salesReturn)
    {
        abort_unless($salesReturn->business_id === $request->user()->business_id, 404);

        return view('business.sales-returns.show', ['return' => $salesReturn->load(['order', 'customer', 'items.orderItem'])]);
    }

    public function edit(Request $request, SalesReturn $salesReturn)
    {
        abort_unless($salesReturn->business_id === $request->user()->business_id, 404);

        return view('business.sales-returns.edit', ['return' => $salesReturn->load(['order', 'customer', 'items.orderItem'])]);
    }

    private function eligibleOrders(int $businessId)
    {
        return Order::with(['customer', 'invoice', 'items.salesReturnItems'])
            ->where('business_id', $businessId)
            ->whereIn('status', ['Completed', 'Delivered', 'Paid', 'Partially Returned'])
            ->whereNotIn('status', ['Cancelled', 'Void', 'Returned'])
            ->latest('order_date');
    }

    private function eligibleOrderOrFail(Request $request, Order $order): Order
    {
        $order = $this->eligibleOrders((int) $request->user()->business_id)
            ->whereKey($order->id)
            ->firstOrFail();
        abort_if($this->remainingReturnableQuantity($order) < 1, 404);

        return $order;
    }

    private function remainingReturnableQuantity(Order $order): int
    {
        return (int) $order->items->sum(fn ($item) => max(0, (int) $item->quantity - (int) $item->salesReturnItems->sum('quantity')));
    }

    private function postReturnAccounting(Order $order, SalesReturn $return, float $cost): void
    {
        $this->accounting->ensureDefaultAccounts($order->business_id);
        $accounts = Account::where('business_id', $order->business_id)
            ->whereIn('name', ['Cash', 'Accounts Receivable', 'Sales Revenue', 'Cost of Goods Sold', 'Inventory'])
            ->pluck('id', 'name');
        if (! isset($accounts['Sales Revenue'])) {
            return;
        }

        $creditAccount = $return->refund_method === 'Store Credit'
            ? ($accounts['Accounts Receivable'] ?? null)
            : ($accounts['Cash'] ?? null);
        if (! $creditAccount) {
            return;
        }

        $lines = [
            ['account_id' => $accounts['Sales Revenue'], 'customer_id' => $order->customer_id, 'debit' => $return->refund_amount, 'credit' => 0, 'description' => 'Sales return '.$order->order_number],
            ['account_id' => $creditAccount, 'customer_id' => $order->customer_id, 'debit' => 0, 'credit' => $return->refund_amount, 'description' => $return->refund_method],
        ];
        if ($cost > 0 && isset($accounts['Cost of Goods Sold'], $accounts['Inventory'])) {
            $lines[] = ['account_id' => $accounts['Inventory'], 'debit' => $cost, 'credit' => 0, 'description' => 'Returned stock'];
            $lines[] = ['account_id' => $accounts['Cost of Goods Sold'], 'debit' => 0, 'credit' => $cost, 'description' => 'Returned stock'];
        }

        $this->accounting->post($order->business_id, [
            'voucher_number' => 'SRN-JV-'.$return->id.'-'.now()->format('His'),
            'entry_date' => now()->toDateString(),
            'reference_type' => 'sales_return',
            'reference_id' => $return->id,
            'description' => 'Sales return '.$order->order_number,
        ], $lines);
    }
}
