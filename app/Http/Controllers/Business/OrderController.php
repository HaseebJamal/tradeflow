<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Inventory;
use App\Models\KhataLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\FinanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(private FinanceCalculator $finance) {}

    public function index() { return view('business.orders.index', ['orders' => Order::with('customer')->where('business_id', auth()->user()->business_id)->latest()->paginate(15)]); }

    public function create()
    {
        $businessId = auth()->user()->business_id;
        return view('business.orders.create', [
            'customers' => Customer::where('business_id', $businessId)->where('status', 'Active')->get(),
            'products' => Product::where('business_id', $businessId)->where('status', 'Active')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'string'],
            'new_customer_name' => ['nullable', 'string', 'max:255'],
            'new_customer_shop' => ['nullable', 'string', 'max:255'],
            'new_customer_phone' => ['nullable', 'string', 'max:30'],
            'new_customer_city' => ['nullable', 'string', 'max:100'],
            'new_customer_address' => ['nullable', 'string'],
            'new_customer_type' => ['nullable', 'in:Retail Shop,Dealer,Distributor,Retailer,Wholesaler'],
            'new_customer_credit_limit' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'], 'payment_type' => ['nullable', 'in:Cash,Credit,Partial'],
            'products' => ['required', 'array'], 'products.*.id' => ['required', 'exists:products,id'], 'products.*.quantity' => ['required', 'integer', 'min:0'],
        ]);
        $data['products'] = collect($data['products'])->filter(fn ($line) => (int) $line['quantity'] > 0)->values()->all();
        if (empty($data['products'])) return back()->withErrors(['products' => 'Add at least one product quantity.']);
        $businessId = auth()->user()->business_id;
        $isWalkIn = ($data['customer_id'] ?? null) === 'walk_in';
        if (!empty($data['customer_id']) && is_numeric($data['customer_id'])) {
            $customer = Customer::where('business_id', $businessId)->findOrFail($data['customer_id']);
        } elseif ($isWalkIn) {
            $customer = null;
        } else {
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
                'status' => 'Active',
            ]);
        }

        DB::transaction(function () use ($data, $businessId, $customer) {
            $discountPercentage = (float) ($data['discount'] ?? 0);
            $order = Order::create(['order_number' => 'ORD-'.now()->format('ymdHis'), 'business_id' => $businessId, 'customer_id' => $customer?->id, 'created_by' => auth()->id(), 'discount' => $discountPercentage, 'discount_percentage' => $discountPercentage, 'discount_amount' => 0, 'payment_type' => $data['payment_type'] ?? 'Credit', 'status' => 'New']);
            $subtotal = 0;
            $descriptionLines = [];
            $calculationLines = collect();
            foreach ($data['products'] as $line) {
                $product = Product::where('business_id', $businessId)->findOrFail($line['id']);
                if ($product->stock_quantity < (int) $line['quantity']) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'products' => $product->name.' has only '.$product->stock_quantity.' '.$product->unit.' available.',
                    ]);
                }
                $total = $product->wholesale_price * $line['quantity'];
                $subtotal += $total;
                $calculationLines->push(['quantity' => $line['quantity'], 'price' => $product->wholesale_price]);
                OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => $line['quantity'], 'price' => $product->wholesale_price, 'total' => $total]);
                $product->decrement('stock_quantity', $line['quantity']);
                $freshProduct = $product->fresh();
                $inventory = Inventory::firstOrCreate(
                    ['business_id' => $businessId, 'product_id' => $product->id],
                    ['available_stock' => $freshProduct?->stock_quantity ?? 0]
                );
                $inventory->increment('sold_stock', $line['quantity']);
                $inventory->update(['available_stock' => $freshProduct?->stock_quantity ?? 0, 'low_stock_alert' => $freshProduct?->low_stock_alert_qty ?? 10]);
                StockMovement::create(['business_id' => $businessId, 'product_id' => $product->id, 'type' => 'sold', 'quantity' => $line['quantity'], 'note' => 'Order '.$order->order_number, 'user_id' => auth()->id(), 'created_by' => auth()->id()]);
                $descriptionLines[] = $product->name.' x '.$line['quantity'];
            }
            ['subtotal' => $subtotal, 'discountAmount' => $discountAmount, 'grandTotal' => $grandTotal] = $this->finance->orderAmountsFromLines($calculationLines, $discountPercentage);
            $order->update([
                'subtotal' => $subtotal,
                'discount' => $discountPercentage,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
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
        });

        return redirect()->route('business.orders.index')->with('success', 'Order created.');
    }

    public function show(Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id, 403);
        $order = $this->finance->syncOrderTotals($order);
        return view('business.orders.show', [
            'order' => $order->load(['customer', 'items.product', 'payments', 'delivery.staff']),
            'deliveryStaff' => User::where('business_id', auth()->user()->business_id)
                ->where('role', 'delivery_staff')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function edit(Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id, 403);

        if (!$this->canEditOrder($order)) {
            return redirect()->route('business.orders.show', $order)
                ->withErrors(['permission' => 'You do not have permission to edit this order. Please contact your business owner.']);
        }

        if (!$this->isEditableStatus($order)) {
            return redirect()->route('business.orders.show', $order)
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
            return redirect()->route('business.orders.show', $order)
                ->withErrors(['permission' => 'You do not have permission to edit this order. Please contact your business owner.']);
        }

        if (!$this->isEditableStatus($order)) {
            return redirect()->route('business.orders.show', $order)
                ->withErrors(['status' => 'This order can no longer be edited because it is already in delivery/completed stage.']);
        }

        $data = $request->validate([
            'customer_id' => ['nullable', 'string'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_type' => ['nullable', 'in:Cash,Credit,Partial'],
            'items' => ['required', 'array'],
            'items.*.item_id' => ['nullable', 'integer', 'exists:order_items,id'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
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
            $order->load(['items.product', 'customer']);
            $oldTotal = (float) ($order->grand_total ?: $order->total);
            $oldCustomer = $order->customer;
            $discountPercentage = (float) ($data['discount'] ?? 0);
            $this->applyOrderItemEdits($order, $data['items']);
            $order->load(['items.product']);
            if ($order->items->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Order must keep at least one product.']);
            }

            ['subtotal' => $subtotal, 'discountAmount' => $discountAmount, 'grandTotal' => $grandTotal] = $this->finance->orderAmountsFromLines($order->items, $discountPercentage);
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
                'total' => $grandTotal,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'balance' => $balance,
                'payment_status' => $this->finance->paymentStatus($grandTotal, $paidAmount),
                'payment_type' => $paymentType,
            ]);

            $this->syncOrderKhataAmount($order->fresh(['customer', 'items.product']), $oldTotal, $grandTotal, $oldCustomer);
            $order->invoice?->update(['paid_amount' => $paidAmount, 'balance' => $balance]);
            $this->audit($request, 'Updated order '.$order->order_number);
        });

        return redirect()->route('business.orders.show', $order)->with('success', 'Order updated successfully.');
    }

    public function updateStatus(Request $request, Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id, 403);
        $data = $request->validate(['status' => ['required', 'in:New,Accepted,Packing,Ready,Out For Delivery,Delivered,Completed,Cancelled']]);
        if ($data['status'] === 'Out For Delivery' && !$order->delivery()->exists()) {
            return back()->withErrors(['status' => 'Please assign delivery staff before moving order Out For Delivery.']);
        }
        $order->update($data);
        return back()->with('success', 'Order status updated.');
    }

    public function assignDelivery(Request $request, Order $order)
    {
        $businessId = auth()->user()->business_id;
        abort_unless($order->business_id === $businessId, 403);

        $data = $request->validate([
            'delivery_staff_id' => ['required', 'exists:users,id'],
            'address' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string'],
        ]);

        $staff = User::where('business_id', $businessId)
            ->where('role', 'delivery_staff')
            ->where('status', 'active')
            ->findOrFail($data['delivery_staff_id']);

        if ($order->delivery()->exists()) {
            return back()->withErrors(['delivery' => 'Delivery has already been assigned for this order.']);
        }

        Delivery::create([
            'business_id' => $order->business_id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'delivery_staff_id' => $staff->id,
            'address' => $data['address'],
            'amount' => $order->balance ?? ($order->grand_total ?: $order->total),
            'status' => 'Pending',
            'note' => $data['note'] ?? null,
        ]);

        $order->update(['status' => 'Out For Delivery']);

        return redirect()->route('business.deliveries')->with('success', 'Delivery assigned successfully.');
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
            $this->audit($request, 'Cancelled order '.$order->order_number);
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
            || KhataLedger::where('order_id', $order->id)->exists();

        DB::transaction(function () use ($order, $hasRelatedRecords, $request) {
            $this->restoreOrderStock($order);

            if ($hasRelatedRecords) {
                $order->update(['status' => 'Cancelled', 'cancelled_at' => now()]);
                $this->audit($request, 'Voided order '.$order->order_number.' because related records exist');
                return;
            }

            $orderNumber = $order->order_number;
            $order->items()->delete();
            $order->delete();
            $this->audit($request, 'Deleted order '.$orderNumber);
        });

        if ($hasRelatedRecords) {
            return back()->with('success', 'Order has related records, so it was marked Cancelled instead of deleted.');
        }

        return redirect()->route('business.orders.index')->with('success', 'Order deleted successfully.');
    }

    private function canManageOrder(Order $order): bool
    {
        return in_array(auth()->user()->role, ['business_owner', 'manager'], true);
    }

    private function canEditOrder(Order $order): bool
    {
        $role = auth()->user()->role;
        if ($role === 'business_owner') {
            return true;
        }

        if ($role === 'manager') {
            return $this->userHasPermission('orders.edit');
        }

        return $role === 'sales_staff'
            && $order->created_by === auth()->id()
            && in_array($order->status, ['New', 'Accepted'], true)
            && $this->userHasPermission('orders.edit');
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
                $product = Product::where('business_id', $businessId)->findOrFail($item->product_id);
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
                        'items' => $product->name.' has only '.$product->stock_quantity.' available for this increase.',
                    ]);
                }

                if ($delta !== 0) {
                    $this->adjustProductStock($order, $product, $delta, $delta > 0 ? 'order_edit_increase' : 'order_edit_decrease', 'Edited '.$order->order_number);
                }

                $item->update([
                    'quantity' => $newQuantity,
                    'price' => $item->price,
                    'total' => round($newQuantity * (float) $item->price, 2),
                ]);
                $keptOrAdded++;
                continue;
            }

            if ($remove || empty($row['product_id']) || empty($row['quantity'])) {
                continue;
            }

            $product = Product::where('business_id', $businessId)->findOrFail($row['product_id']);
            $quantity = (int) $row['quantity'];
            if ($quantity < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Product quantities must be at least 1.']);
            }
            if ($product->stock_quantity < $quantity) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => $product->name.' has only '.$product->stock_quantity.' available.',
                ]);
            }

            $this->adjustProductStock($order, $product, $quantity, 'order_item_added', 'Added to '.$order->order_number);
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $product->wholesale_price,
                'total' => round($quantity * (float) $product->wholesale_price, 2),
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

    private function audit(Request $request, string $action): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'ip_address' => $request->ip(),
        ]);
    }
}
