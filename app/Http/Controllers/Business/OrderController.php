<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\KhataLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\BusinessActivityService;
use App\Services\FinanceCalculator;
use App\Services\CompanyPermissionService;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private FinanceCalculator $finance, private AccountingService $accounting, private BusinessActivityService $activity, private CompanyPermissionService $permissions, private DocumentNumberService $numbers) {}

    public function index(Request $request)
    {
        $businessId = auth()->user()->business_id;
        $canViewCustomers = $this->permissions->allowsUser($request->user(), 'customers.view');
        $filters = $request->validate([
            'order_number' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'payment_type' => ['nullable', 'string', 'max:50'],
            'created_by' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'clear' => ['nullable', 'boolean'],
        ]);

        $dateFrom = $request->boolean('clear') ? null : ($filters['date_from'] ?? now()->startOfMonth()->toDateString());
        $dateTo = $request->boolean('clear') ? null : ($filters['date_to'] ?? now()->toDateString());
        $query = Order::with(['customer', 'creator', 'invoice', 'items.product'])
            ->where('business_id', $businessId)
            ->when($filters['order_number'] ?? null, fn ($q, $value) => $q->where(function ($orders) use ($value) {
                $orders->where('order_number', 'like', "%{$value}%")
                    ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', "%{$value}%"));
            }))
            ->when($filters['customer_id'] ?? null, fn ($q, $value) => $q->where('customer_id', $value))
            ->when($filters['product_id'] ?? null, fn ($q, $value) => $q->whereHas('items', fn ($items) => $items->where('product_id', $value)))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['payment_status'] ?? null, fn ($q, $value) => $q->where('payment_status', $value))
            ->when($filters['payment_type'] ?? null, fn ($q, $value) => $q->where('payment_type', $value))
            ->when($filters['created_by'] ?? null, fn ($q, $value) => $q->where('created_by', $value))
            ->when($dateFrom, fn ($q, $value) => $q->whereDate('order_date', '>=', $value))
            ->when($dateTo, fn ($q, $value) => $q->whereDate('order_date', '<=', $value));

        return view('business.orders.index', [
            'orders' => $query->latest()->paginate(10)->withQueryString(),
            'customers' => $canViewCustomers
                ? Customer::where('business_id', $businessId)->orderBy('name')->get()
                : collect(),
            'canViewCustomers' => $canViewCustomers,
            'products' => Product::where('business_id', $businessId)->orderBy('name')->get(),
            'creators' => User::where('business_id', $businessId)->orderBy('name')->get(),
            'saleNumbers' => Order::where('business_id', $businessId)
                ->whereNotNull('order_number')
                ->latest('order_date')
                ->pluck('order_number'),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function create()
    {
        $businessId = auth()->user()->business_id;
        $canViewCustomers = $this->permissions->allowsUser(auth()->user(), 'customers.view');

        return view('business.orders.create', [
            'customers' => $canViewCustomers
                ? Customer::where('business_id', $businessId)->where('status', 'Active')->get()
                : collect(),
            'canViewCustomers' => $canViewCustomers,
            'products' => Product::where('business_id', $businessId)->where('status', 'Active')->get(),
        ]);
    }

    /** Resolve a scanned sale/order number inside the current business. */
    public function lookup(Request $request)
    {
        $code = trim((string) $request->validate(['code' => ['required', 'string', 'max:120']])['code']);
        $order = Order::where('business_id', $request->user()->business_id)
            ->where(fn ($query) => $query->where('order_number', $code)
                ->orWhereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', $code)))
            ->first();

        return response()->json([
            'found' => (bool) $order,
            'url' => $order ? route('business.sales.show', $order) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'string'],
            'new_customer_name' => ['nullable', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'new_customer_shop' => ['nullable', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'new_customer_phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'new_customer_city' => ['nullable', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'new_customer_address' => ['nullable', 'string'],
            'new_customer_type' => ['nullable', 'in:Retail Shop,Dealer,Distributor,Retailer,Wholesaler'],
            'new_customer_credit_limit' => ['nullable', 'regex:/^\d+$/', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'payment_type' => ['nullable', 'in:Cash,Credit,Partial'],
            'products' => ['required', 'array'],
            'products.*.id' => ['required', 'exists:products,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.discount_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'products.*.tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
        $data['products'] = collect($data['products'])->filter(fn ($line) => (int) $line['quantity'] > 0)->values()->all();
        if (empty($data['products'])) return back()->withErrors(['products' => 'Add at least one product quantity.']);
        $businessId = auth()->user()->business_id;
        $isWalkIn = ($data['customer_id'] ?? null) === 'walk_in';
        if (!empty($data['customer_id']) && is_numeric($data['customer_id'])) {
            abort_unless($this->permissions->allowsUser($request->user(), 'customers.view'), 403);
            $customer = Customer::where('business_id', $businessId)->findOrFail($data['customer_id']);
        } elseif ($isWalkIn) {
            $customer = null;
        } else {
            abort_unless($this->permissions->allowsUser($request->user(), 'customers.create'), 403);
            if (empty($data['new_customer_name']) && empty($data['new_customer_phone'])) {
                return back()->withErrors(['customer_id' => 'Select a customer, choose Walk-in Customer, or add customer name/phone.'])->withInput();
            }

            $customer = Customer::create([
                'business_id' => $businessId,
                'name' => $data['new_customer_name'] ?: $data['new_customer_phone'],
                'business_name' => $data['new_customer_shop'] ?? null,
                'phone' => $data['new_customer_phone'] ?? null,
                'city' => $data['new_customer_city'] ?? null,
                'address' => $data['new_customer_address'] ?? null,
                'customer_type' => $data['new_customer_type'] ?? 'Retailer',
                'credit_limit' => $data['new_customer_credit_limit'] ?? 0,
                'opening_balance' => 0,
                'current_balance' => 0,
                'status' => 'Active',
            ]);
        }

        DB::transaction(function () use ($data, $businessId, $customer, $request) {
            // Lock every requested product in a stable order and validate the
            // aggregate quantity before creating any sale records. This makes
            // duplicate lines and concurrent order requests safe.
            $requestedByProduct = collect($data['products'])
                ->groupBy('id')
                ->map(fn ($lines) => $lines->sum(fn ($line) => (int) $line['quantity']));
            $lockedProducts = Product::where('business_id', $businessId)
                ->whereIn('id', $requestedByProduct->keys()->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($requestedByProduct as $productId => $requestedQuantity) {
                $product = $lockedProducts->get((int) $productId);
                if (!$product) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'products' => 'One of the selected products is unavailable for this business.',
                    ]);
                }

                if ((int) $product->stock_quantity < (int) $requestedQuantity) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'products' => 'Insufficient stock. Only '.$product->stock_quantity.' units are available.',
                    ]);
                }
            }

            $discountPercentage = (int) ($data['discount'] ?? 0);
            $taxRate = (int) ($data['tax_rate'] ?? 0);
            $order = Order::create(['order_number' => $this->numbers->next('sales'), 'business_id' => $businessId, 'customer_id' => $customer?->id, 'created_by' => auth()->id(), 'order_date' => now(), 'discount' => $discountPercentage, 'discount_percentage' => $discountPercentage, 'discount_amount' => 0, 'tax_rate' => $taxRate, 'tax_amount' => 0, 'payment_type' => $data['payment_type'] ?? 'Credit', 'status' => 'New']);
            $descriptionLines = [];
            $calculationLines = collect();
            foreach ($data['products'] as $line) {
                $product = $lockedProducts->get((int) $line['id']);
                $amounts = $this->finance->calculateLineAmounts(
                    (int) $line['quantity'],
                    (float) $product->wholesale_price,
                    (int) ($line['discount_rate'] ?? 0),
                    (int) ($line['tax_rate'] ?? 0),
                );
                $calculationLines->push(['line_total' => $amounts['lineTotal']]);
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'quantity' => $line['quantity'],
                    'unit' => $product->unit,
                    'unit_price' => $product->wholesale_price,
                    'purchase_cost_snapshot' => $product->purchase_cost,
                    'line_subtotal' => $amounts['lineSubtotal'],
                    'discount_rate' => $amounts['discountRate'],
                    'discount_amount' => $amounts['discountAmount'],
                    'tax_rate' => $amounts['taxRate'],
                    'tax_amount' => $amounts['taxAmount'],
                    'line_total' => $amounts['lineTotal'],
                    'price' => $product->wholesale_price,
                    'total' => $amounts['lineTotal'],
                ]);
                $product->decrement('stock_quantity', $line['quantity']);
                $freshProduct = $product->fresh();
                $inventory = Inventory::firstOrCreate(
                    ['business_id' => $businessId, 'product_id' => $product->id],
                    ['available_stock' => $freshProduct?->stock_quantity ?? 0]
                );
                $inventory->increment('sold_stock', $line['quantity']);
                $inventory->update(['available_stock' => $freshProduct?->stock_quantity ?? 0, 'low_stock_alert' => $freshProduct?->low_stock_alert_qty ?? 10]);
                StockMovement::create(['business_id' => $businessId, 'product_id' => $product->id, 'type' => 'sold', 'quantity' => $line['quantity'], 'note' => 'Order '.$order->order_number, 'user_id' => auth()->id(), 'created_by' => auth()->id()]);
                InventoryMovement::create([
                    'business_id' => $businessId,
                    'product_id' => $product->id,
                    'type' => 'SOLD',
                    'quantity' => $line['quantity'],
                    'previous_stock' => (int) $freshProduct->stock_quantity + (int) $line['quantity'],
                    'new_stock' => (int) $freshProduct->stock_quantity,
                    'note' => 'Order '.$order->order_number,
                    'created_by' => auth()->id(),
                    'movement_date' => now(),
                ]);
                $descriptionLines[] = $product->name.' x '.$line['quantity'];
            }
            ['subtotal' => $subtotal, 'discountAmount' => $discountAmount, 'taxAmount' => $taxAmount, 'grandTotal' => $grandTotal] = $this->finance->salesAmountsFromLines($calculationLines, $discountPercentage, $taxRate);
            $order->update([
                'subtotal' => $subtotal,
                'discount' => $discountPercentage,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $grandTotal,
                'grand_total' => $grandTotal,
                'paid_amount' => 0,
                'balance' => $grandTotal,
                'payment_status' => 'Pending',
            ]);
            if ($customer) {
                $balance = $customer->current_balance + $grandTotal;
                $customer->update(['current_balance' => $balance]);
                KhataLedger::create([
                    'business_id' => $businessId,
                    'customer_id' => $customer->id,
                    'order_id' => $order->id,
                    'entry_type' => 'purchase',
                    'type' => 'credit',
                    'amount' => $grandTotal,
                    'customer_debit' => 0,
                    'customer_credit' => $grandTotal,
                    'business_debit' => $grandTotal,
                    'business_credit' => 0,
                    'description' => 'Order '.$order->order_number.' - '.implode(', ', $descriptionLines),
                    'balance' => $balance,
                    'balance_after' => $balance,
                    'entry_date' => now()->toDateString(),
                ]);
            }
            $this->postOrderAccounting($order->fresh(['customer']));
            $this->audit($request, 'Sale created '.$order->order_number, 'Sales', $order->id, null, ['status' => $order->status, 'grand_total' => $grandTotal]);
        });

        return redirect()->route('business.sales.index')->with('success', 'Sale created.');
    }

    public function show(Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id, 403);
        $order = $this->finance->syncOrderTotals($order);
        return view('business.orders.show', [
            'order' => $order->load(['customer', 'items.product', 'payments', 'delivery.staff']),
            'journalEntries' => JournalEntry::with('lines.account')->where('business_id', $order->business_id)->where('reference_type', 'order')->where('reference_id', $order->id)->latest()->get(),
            'deliveryStaff' => User::where('business_id', auth()->user()->business_id)
                ->where('role', 'custom_staff')
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereJsonContains('permissions', 'deliveries.view')
                        ->orWhereJsonContains('permissions', 'deliveries.update_status')
                        ->orWhereJsonContains('permissions', 'deliveries.upload_proof');
                })
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function edit(Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id, 403);

        if (!$this->canEditOrder($order)) {
            return redirect()->route('business.sales.show', $order)
                ->withErrors(['permission' => 'You do not have permission to edit this order. Please contact your business owner.']);
        }

        if (!$this->isEditableStatus($order)) {
            return redirect()->route('business.sales.show', $order)
                ->withErrors(['status' => 'This order can no longer be edited because it is already in delivery/completed stage.']);
        }

        $businessId = auth()->user()->business_id;
        return view('business.orders.edit', [
            'order' => $order->load(['customer', 'items.product']),
            'customers' => Customer::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get(),
            'products' => Product::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id, 403);

        if (!$this->canEditOrder($order)) {
            return redirect()->route('business.sales.show', $order)
                ->withErrors(['permission' => 'You do not have permission to edit this order. Please contact your business owner.']);
        }

        if (!$this->isEditableStatus($order)) {
            return redirect()->route('business.sales.show', $order)
                ->withErrors(['status' => 'This order can no longer be edited because it is already in delivery/completed stage.']);
        }

        $data = $request->validate([
            'customer_id' => ['nullable', 'string'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'payment_type' => ['nullable', 'in:Cash,Credit,Partial'],
            'items' => ['required', 'array'],
            'items.*.item_id' => ['nullable', 'integer', 'exists:order_items,id'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.discount_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'items.*.remove' => ['nullable', 'boolean'],
        ]);

        $businessId = auth()->user()->business_id;
        $customer = null;
        if (!empty($data['customer_id']) && $data['customer_id'] !== 'walk_in') {
            $customer = Customer::where('business_id', $businessId)->findOrFail($data['customer_id']);
        }
        $newCustomerId = $customer?->id;
        if ($order->payments()->exists() && (int) ($order->customer_id ?? 0) !== (int) ($newCustomerId ?? 0)) {
            return back()->withErrors(['customer_id' => 'Customer cannot be changed after payments are recorded for this order.'])->withInput();
        }

        DB::transaction(function () use ($order, $data, $request, $customer) {
            // Serialise edits to the same order as well as the product-row
            // locks in applyOrderItemEdits(), preventing a concurrent edit
            // from applying the same stock delta twice.
            $order = Order::where('business_id', $order->business_id)
                ->lockForUpdate()
                ->findOrFail($order->id);
            $order->load(['items.product', 'customer']);
            $oldTotal = (float) ($order->grand_total ?: $order->total);
            $oldCustomer = $order->customer;
            $discountPercentage = (int) ($data['discount'] ?? 0);
            $taxRate = (int) ($data['tax_rate'] ?? 0);
            $this->applyOrderItemEdits($order, $data['items']);
            $order->load(['items.product']);
            if ($order->items->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Order must keep at least one product.']);
            }

            ['subtotal' => $subtotal, 'discountAmount' => $discountAmount, 'taxAmount' => $taxAmount, 'grandTotal' => $grandTotal] = $this->finance->salesAmountsFromLines($order->items, $discountPercentage, $taxRate);
            $paidAmount = $this->finance->calculatePaidAmount($order);
            if ($paidAmount > $grandTotal) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Order total cannot be lower than already paid amount of Rs '.number_format($paidAmount).'.',
                ]);
            }
            $balance = $this->finance->calculateBalance($grandTotal, $paidAmount);
            $paymentType = $data['payment_type'] ?? $order->payment_type;

            $order->update([
                'customer_id' => $customer?->id,
                'subtotal' => $subtotal,
                'discount' => $discountPercentage,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $grandTotal,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'payment_status' => $this->finance->paymentStatus($grandTotal, $paidAmount),
                'payment_type' => $paymentType,
            ]);

            $this->syncOrderKhataAmount($order->fresh(['customer', 'items.product']), $oldTotal, $grandTotal, $oldCustomer);
            $order->invoice?->update([
                'subtotal' => $subtotal,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'payment_status' => $this->finance->paymentStatus($grandTotal, $paidAmount),
            ]);
            $this->reverseOrderAccounting($order, 'Order edit reversal '.$order->order_number);
            $this->postOrderAccounting($order->fresh(['customer']));
            $this->audit($request, 'Updated sale '.$order->order_number, 'Sales', $order->id, ['grand_total' => $oldTotal], ['grand_total' => $grandTotal]);
        });

        return redirect()->route('business.sales.show', $order)->with('success', 'Sale updated successfully.');
    }

    public function updateStatus(Request $request, Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id, 403);
        $data = $request->validate(['status' => ['required', 'in:New,Accepted,Packing,Ready,Out For Delivery,Delivered,Completed,Cancelled,Void']]);
        if ($data['status'] === 'Out For Delivery' && !$order->delivery()->exists()) {
            return back()->withErrors(['status' => 'Please assign delivery staff before moving order Out For Delivery.']);
        }
        $order->update($data);
        return back()->with('success', 'Order status updated.');
    }

    public function cancel(Request $request, Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id, 403);

        if (!$this->canManageOrder($order)) {
            return back()->withErrors(['permission' => 'You do not have permission to cancel this order. Please contact your business owner.']);
        }

        if ($order->status === 'Cancelled') {
            return back()->withErrors(['status' => 'This order is already cancelled.']);
        }

        DB::transaction(function () use ($order, $request) {
            $this->restoreOrderStock($order);
            $order->update(['status' => 'Cancelled', 'cancelled_at' => now()]);
            $this->reverseOrderAccounting($order, 'Cancelled order '.$order->order_number);
            Invoice::where('order_id', $order->id)->whereIn('status', ['Draft', 'Issued'])->update(['status' => 'Cancelled']);
            Delivery::where('order_id', $order->id)->whereNot('status', 'Delivered')->update(['status' => 'Cancelled', 'cancelled_at' => now()]);
            $this->audit($request, 'Cancelled sale '.$order->order_number, 'Sales', $order->id);
        });

        return back()->with('success', 'Order cancelled successfully.');
    }

    public function destroy(Request $request, Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id, 403);

        if (!$this->canManageOrder($order)) {
            return back()->withErrors(['permission' => 'You do not have permission to delete this order. Please contact your business owner.']);
        }

        $hasRelatedRecords = $order->payments()->exists()
            || $order->delivery()->exists()
            || $order->invoice()->exists()
            || KhataLedger::where('order_id', $order->id)->exists()
            || JournalEntry::where('business_id', $order->business_id)->where('reference_type', 'order')->where('reference_id', $order->id)->exists()
            || StockMovement::where('business_id', $order->business_id)->where('note', 'like', '%'.$order->order_number.'%')->exists();

        DB::transaction(function () use ($order, $hasRelatedRecords, $request) {
            $this->restoreOrderStock($order);

            if ($hasRelatedRecords) {
                $order->update(['status' => 'Void', 'voided_at' => now(), 'void_reason' => 'Deleted with related transactions']);
                $this->reverseOrderAccounting($order, 'Voided order '.$order->order_number);
                Invoice::where('order_id', $order->id)->whereNotIn('status', ['Paid'])->update(['status' => 'Void', 'voided_at' => now(), 'voided_by' => auth()->id(), 'void_reason' => 'Order voided']);
                Delivery::where('order_id', $order->id)->whereNot('status', 'Delivered')->update(['status' => 'Cancelled', 'cancelled_at' => now()]);
                $this->audit($request, 'Voided sale '.$order->order_number.' because related records exist', 'Sales', $order->id);
                return;
            }

            $orderNumber = $order->order_number;
            $order->items()->delete();
            $order->delete();
            $this->audit($request, 'Deleted sale '.$orderNumber, 'Sales', $order->id);
        });

        if ($hasRelatedRecords) {
            return back()->with('success', 'Order has related records, so it was marked Cancelled instead of deleted.');
        }

        return redirect()->route('business.sales.index')->with('success', 'Sale deleted successfully.');
    }

    public function void(Request $request, Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id, 403);
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:1000']]);

        DB::transaction(function () use ($order, $data, $request) {
            $this->restoreOrderStock($order);
            $order->update(['status' => 'Void', 'voided_at' => now(), 'void_reason' => $data['void_reason']]);
            $this->reverseOrderAccounting($order, 'Voided order '.$order->order_number);
            Invoice::where('order_id', $order->id)->update(['status' => 'Void', 'voided_by' => auth()->id(), 'voided_at' => now(), 'void_reason' => $data['void_reason']]);
            Delivery::where('order_id', $order->id)->whereNot('status', 'Delivered')->update(['status' => 'Cancelled', 'cancelled_at' => now()]);
            $this->audit($request, 'Voided sale '.$order->order_number, 'Sales', $order->id, null, ['reason' => $data['void_reason']]);
        });

        return back()->with('success', 'Order voided safely.');
    }

    private function canManageOrder(Order $order): bool
    {
        return $this->permissions->allowsUser(auth()->user(), 'orders.update_status');
    }

    private function canEditOrder(Order $order): bool
    {
        return $this->permissions->allowsUser(auth()->user(), 'orders.edit')
            && ($order->created_by === auth()->id() || $this->permissions->allowsUser(auth()->user(), 'orders.update_status'))
            && in_array($order->status, ['New', 'Accepted', 'Packing'], true);
    }

    private function isEditableStatus(Order $order): bool
    {
        return in_array($order->status, ['New', 'Accepted', 'Packing'], true);
    }

    private function userHasPermission(string $permission): bool
    {
        $permissions = collect(auth()->user()->permissions ?? [])->map(fn ($value) => strtolower($value));
        $permission = strtolower($permission);
        $module = str($permission)->before('.')->toString();

        return $permissions->contains($permission) || $permissions->contains($module);
    }

    private function applyOrderItemEdits(Order $order, array $rows): void
    {
        $businessId = $order->business_id;
        $existingItems = $order->items->keyBy('id');
        $keptOrAdded = 0;

        foreach ($rows as $row) {
            $remove = (bool) ($row['remove'] ?? false);
            $item = !empty($row['item_id']) ? $existingItems->get((int) $row['item_id']) : null;

            if (!empty($row['item_id']) && !$item) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'One of the order items is invalid.']);
            }

            if ($item) {
                $product = Product::where('business_id', $businessId)->lockForUpdate()->findOrFail($item->product_id);
                if ($remove) {
                    $this->adjustProductStock($order, $product, -1 * (int) $item->quantity, 'order_item_removed', 'Removed from '.$order->order_number);
                    $item->delete();
                    continue;
                }

                $newQuantity = (int) ($row['quantity'] ?? 0);
                if ($newQuantity < 1) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Product quantities must be at least 1.']);
                }

                $delta = $newQuantity - (int) $item->quantity;
                if ($delta > 0 && $product->stock_quantity < $delta) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => 'Insufficient stock. Only '.$product->stock_quantity.' units are available.',
                    ]);
                }

                if ($delta !== 0) {
                    $this->adjustProductStock($order, $product, $delta, $delta > 0 ? 'order_edit_increase' : 'order_edit_decrease', 'Edited '.$order->order_number);
                }

                $amounts = $this->finance->calculateLineAmounts(
                    $newQuantity,
                    (float) $item->price,
                    (int) ($row['discount_rate'] ?? $item->discount_rate ?? 0),
                    (int) ($row['tax_rate'] ?? $item->tax_rate ?? 0),
                );
                $item->update([
                    'quantity' => $newQuantity,
                    'price' => $item->price,
                    'total' => $amounts['lineTotal'],
                    'unit_price' => $item->unit_price ?: $item->price,
                    'line_subtotal' => $amounts['lineSubtotal'],
                    'discount_rate' => $amounts['discountRate'],
                    'discount_amount' => $amounts['discountAmount'],
                    'tax_rate' => $amounts['taxRate'],
                    'tax_amount' => $amounts['taxAmount'],
                    'line_total' => $amounts['lineTotal'],
                ]);
                $keptOrAdded++;
                continue;
            }

            if ($remove || empty($row['product_id']) || empty($row['quantity'])) {
                continue;
            }

            $product = Product::where('business_id', $businessId)->lockForUpdate()->findOrFail($row['product_id']);
            $quantity = (int) $row['quantity'];
            if ($quantity < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Product quantities must be at least 1.']);
            }
            if ($product->stock_quantity < $quantity) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Insufficient stock. Only '.$product->stock_quantity.' units are available.',
                ]);
            }

            $this->adjustProductStock($order, $product, $quantity, 'order_item_added', 'Added to '.$order->order_number);
            $amounts = $this->finance->calculateLineAmounts(
                $quantity,
                (float) $product->wholesale_price,
                (int) ($row['discount_rate'] ?? 0),
                (int) ($row['tax_rate'] ?? 0),
            );
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name_snapshot' => $product->name,
                'quantity' => $quantity,
                'unit' => $product->unit,
                'unit_price' => $product->wholesale_price,
                'purchase_cost_snapshot' => $product->purchase_cost,
                'line_subtotal' => $amounts['lineSubtotal'],
                'discount_rate' => $amounts['discountRate'],
                'discount_amount' => $amounts['discountAmount'],
                'tax_rate' => $amounts['taxRate'],
                'tax_amount' => $amounts['taxAmount'],
                'line_total' => $amounts['lineTotal'],
                'price' => $product->wholesale_price,
                'total' => $amounts['lineTotal'],
            ]);
            $keptOrAdded++;
        }

        if ($keptOrAdded < 1) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Order must keep at least one product.']);
        }
    }

    private function adjustProductStock(Order $order, Product $product, int $delta, string $type, string $note): void
    {
        if ($delta > 0) {
            $product->decrement('stock_quantity', $delta);
        } elseif ($delta < 0) {
            $product->increment('stock_quantity', abs($delta));
        } else {
            return;
        }

        $freshProduct = $product->fresh();
        $inventory = Inventory::firstOrCreate(
            ['business_id' => $order->business_id, 'product_id' => $product->id],
            ['available_stock' => $freshProduct?->stock_quantity ?? 0]
        );
        $inventory->update([
            'available_stock' => $freshProduct?->stock_quantity ?? 0,
            'sold_stock' => max(0, ((int) $inventory->sold_stock) + $delta),
            'low_stock_alert' => $freshProduct?->low_stock_alert_qty ?? $inventory->low_stock_alert,
        ]);

        StockMovement::create([
            'business_id' => $order->business_id,
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => abs($delta),
            'note' => $note,
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
        ]);
        InventoryMovement::create([
            'business_id' => $order->business_id,
            'product_id' => $product->id,
            'type' => $delta > 0 ? 'SOLD' : 'RETURNED',
            'quantity' => abs($delta),
            'previous_stock' => $delta > 0 ? (int) $freshProduct->stock_quantity + $delta : (int) $freshProduct->stock_quantity - abs($delta),
            'new_stock' => (int) $freshProduct->stock_quantity,
            'note' => $note,
            'created_by' => auth()->id(),
            'movement_date' => now(),
        ]);
    }

    private function restoreOrderStock(Order $order): void
    {
        if ($order->stock_restored_at) {
            return;
        }

        $order->loadMissing('items.product');
        foreach ($order->items as $item) {
            if (!$item->product) {
                continue;
            }

            $item->product->increment('stock_quantity', $item->quantity);
            $freshProduct = $item->product->fresh();
            $inventory = Inventory::firstOrCreate(
                ['business_id' => $order->business_id, 'product_id' => $item->product_id],
                ['available_stock' => $freshProduct?->stock_quantity ?? 0]
            );
            $inventory->update([
                'available_stock' => $freshProduct?->stock_quantity ?? 0,
                'sold_stock' => max(0, ((int) $inventory->sold_stock) - (int) $item->quantity),
                'low_stock_alert' => $freshProduct?->low_stock_alert_qty ?? $inventory->low_stock_alert,
            ]);
            StockMovement::create([
                'business_id' => $order->business_id,
                'product_id' => $item->product_id,
                'type' => 'returned',
                'quantity' => $item->quantity,
                'note' => 'Stock restored after '.$order->order_number.' cancellation',
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
            ]);
            InventoryMovement::create([
                'business_id' => $order->business_id,
                'product_id' => $item->product_id,
                'type' => 'RETURNED',
                'quantity' => (int) $item->quantity,
                'previous_stock' => (int) $freshProduct->stock_quantity - (int) $item->quantity,
                'new_stock' => (int) $freshProduct->stock_quantity,
                'note' => 'Stock restored after '.$order->order_number.' cancellation',
                'created_by' => auth()->id(),
                'movement_date' => now(),
            ]);
        }

        $order->forceFill(['stock_restored_at' => now()])->save();
    }

    private function syncOrderKhataAmount(Order $order, float $oldTotal, float $newTotal, ?Customer $oldCustomer = null): void
    {
        if ($oldCustomer && (!$order->customer || $oldCustomer->id !== $order->customer->id)) {
            $oldCustomer->decrement('current_balance', $oldTotal);
        }

        if (!$order->customer) {
            KhataLedger::where('order_id', $order->id)->where('entry_type', 'purchase')->delete();
            return;
        }

        $difference = $oldCustomer && $oldCustomer->id === $order->customer->id ? $newTotal - $oldTotal : $newTotal;
        if ($difference !== 0.0) {
            $order->customer->increment('current_balance', $difference);
        }

        $ledger = KhataLedger::where('order_id', $order->id)->where('entry_type', 'purchase')->first();
        $descriptionLines = $order->items->map(fn ($item) => ($item->product?->name ?? 'Product').' x '.$item->quantity)->implode(', ');

        $ledgerData = [
            'business_id' => $order->business_id,
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'entry_type' => 'purchase',
            'type' => 'credit',
            'amount' => $newTotal,
            'customer_debit' => 0,
            'customer_credit' => $newTotal,
            'business_debit' => $newTotal,
            'business_credit' => 0,
            'description' => 'Order '.$order->order_number.' - '.$descriptionLines,
            'balance' => (float) $order->customer->current_balance,
            'balance_after' => (float) $order->customer->current_balance,
            'entry_date' => now()->toDateString(),
        ];

        $ledger ? $ledger->update($ledgerData) : KhataLedger::create($ledgerData);
    }

    private function postOrderAccounting(Order $order): void
    {
        if (JournalEntry::where('business_id', $order->business_id)->where('reference_type', 'order')->where('reference_id', $order->id)->where('status', 'posted')->exists()) {
            return;
        }
        $this->accounting->ensureDefaultAccounts($order->business_id);
        $debitAccount = Account::where('business_id', $order->business_id)->where('name', $order->payment_type === 'Cash' ? 'Cash' : 'Accounts Receivable')->first();
        $salesAccount = Account::where('business_id', $order->business_id)->where('name', 'Sales Revenue')->first();
        if (!$debitAccount || !$salesAccount || (float) $order->grand_total <= 0) return;

        $costOfGoodsSold = (float) $order->items()->sum(DB::raw('quantity * purchase_cost_snapshot'));
        $cogsAccount = $costOfGoodsSold > 0 ? Account::where('business_id', $order->business_id)->where('name', 'Cost of Goods Sold')->first() : null;
        $inventoryAccount = $costOfGoodsSold > 0 ? Account::where('business_id', $order->business_id)->where('name', 'Inventory')->first() : null;
        $lines = [
            ['account_id' => $debitAccount->id, 'customer_id' => $order->customer_id, 'debit' => $order->grand_total, 'credit' => 0, 'description' => $order->order_number],
            ['account_id' => $salesAccount->id, 'customer_id' => $order->customer_id, 'debit' => 0, 'credit' => $order->grand_total, 'description' => $order->order_number],
        ];
        if ($cogsAccount && $inventoryAccount) {
            $lines[] = ['account_id' => $cogsAccount->id, 'debit' => $costOfGoodsSold, 'credit' => 0, 'description' => 'Cost of goods sold '.$order->order_number];
            $lines[] = ['account_id' => $inventoryAccount->id, 'debit' => 0, 'credit' => $costOfGoodsSold, 'description' => 'Inventory issued '.$order->order_number];
        }

        $this->accounting->post($order->business_id, [
            'voucher_number' => 'ORD-JV-'.$order->id.'-'.now()->format('His'),
            'entry_date' => $order->order_date?->format('Y-m-d') ?? now()->toDateString(),
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'description' => 'Credit sale '.$order->order_number,
        ], $lines);
    }

    private function reverseOrderAccounting(Order $order, string $description): void
    {
        $entries = JournalEntry::with('lines')->where('business_id', $order->business_id)->where('reference_type', 'order')->where('reference_id', $order->id)->where('status', 'posted')->get();
        foreach ($entries as $entry) {
            if (JournalEntry::where('business_id', $order->business_id)->where('reference_type', 'order_reversal')->where('reference_id', $entry->id)->exists()) continue;
            $this->accounting->post($order->business_id, [
                'voucher_number' => 'REV-'.$entry->id.'-'.now()->format('His'),
                'entry_date' => now()->toDateString(),
                'reference_type' => 'order_reversal',
                'reference_id' => $entry->id,
                'description' => $description,
            ], $entry->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'customer_id' => $line->customer_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'description' => 'Reversal '.$entry->voucher_number,
            ])->all());
        }
    }

    private function audit(Request $request, string $action, ?string $module = null, ?int $recordId = null, ?array $old = null, ?array $new = null): void
    {
        $this->activity->record((int) auth()->user()->business_id, $module ?? 'Sales', $action, $recordId, $old, $new);
    }
}
