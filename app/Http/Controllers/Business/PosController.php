<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\KhataLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosPayment;
use App\Models\PosRegister;
use App\Models\PosReturn;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AccountingService;
use App\Services\BusinessActivityService;
use App\Services\CompanyPermissionService;
use App\Services\DocumentNumberService;
use App\Services\FinanceCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosController extends Controller
{
    public function __construct(private AccountingService $accounting, private CompanyPermissionService $permissions, private BusinessActivityService $activity, private DocumentNumberService $numbers, private FinanceCalculator $finance) {}

    public function index()
    {
        $businessId = $this->businessId();

        return view('business.pos.index', [
            'register' => $this->currentRegister(),
            'products' => Product::with('category')->where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get(),
            'customers' => Customer::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get(),
            'todaySales' => Order::where('business_id', $businessId)->where('sale_channel', 'pos')->whereDate('order_date', today())->whereNotIn('status', ['Cancelled', 'Void', 'Returned'])->sum('grand_total'),
        ]);
    }

    public function openRegister(Request $request)
    {
        $data = $request->validate([
            'opening_cash' => ['nullable', 'integer', 'min:0'],
            'opening_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($this->currentRegister()) {
            return back()->withErrors(['register' => 'You already have an open register. Close it before opening another one.']);
        }

        $register = PosRegister::create([
            'business_id' => $this->businessId(),
            'user_id' => auth()->id(),
            'opening_cash' => $data['opening_cash'] ?? 0,
            'expected_cash' => $data['opening_cash'] ?? 0,
            'status' => 'Open',
            'opened_at' => now(),
            'opening_note' => $data['opening_note'] ?? null,
        ]);
        $this->audit('POS register opened', $register->id, ['opening_cash' => $register->opening_cash]);

        return back()->with('success', 'POS register opened.');
    }

    public function closeRegister(Request $request, PosRegister $register)
    {
        $this->scopedRegister($register);
        $data = $request->validate([
            'closing_cash' => ['required', 'integer', 'min:0'],
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless($register->status === 'Open', 404);
        $cashSales = PosPayment::where('business_id', $register->business_id)->where('pos_register_id', $register->id)->where('method', 'Cash')->sum('amount');
        $expected = round((float) $register->opening_cash + (float) $cashSales, 2);
        $register->update([
            'expected_cash' => $expected,
            'closing_cash' => $data['closing_cash'],
            'variance' => round((float) $data['closing_cash'] - $expected, 2),
            'status' => 'Closed',
            'closed_at' => now(),
            'closing_note' => $data['closing_note'] ?? null,
        ]);
        $this->audit('POS register closed', $register->id, ['variance' => $register->variance]);

        return redirect()->route('business.pos.index')->with('success', 'POS register closed.');
    }

    public function store(Request $request)
    {
        $register = $this->currentRegister();
        if (!$register) {
            return back()->withErrors(['register' => 'Open a POS register before completing a sale.'])->withInput();
        }

        $data = $request->validate([
            'customer_id' => ['nullable', 'string'],
            'new_customer_name' => ['nullable', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'new_customer_phone' => ['nullable', 'regex:/^\d{11}$/'],
            'new_customer_city' => ['nullable', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'new_customer_address' => ['nullable', 'string', 'max:500'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['nullable', 'integer', 'min:0'],
            'tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'payment_mode' => ['required', 'in:cash,credit,split'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['nullable', 'integer', 'min:0'],
            'items.*.discount_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'items.*.tax_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', 'in:Cash,Bank Transfer,JazzCash Manual,Easypaisa Manual,Cheque'],
            'payments.*.amount' => ['required_with:payments', 'integer', 'min:1'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:255'],
        ]);

        $businessId = $this->businessId();

        $order = DB::transaction(function () use ($data, $businessId, $register) {
            // Keep an inline customer creation inside the sale transaction.
            $customer = $this->resolveCustomer($data, $businessId);
            if ($data['payment_mode'] === 'credit' && !$customer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Credit sales require a registered customer. Walk-in Customer is available for cash sales only.',
                ]);
            }
            if ($data['payment_mode'] === 'split' && !$customer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Select or create a customer before using split payment.',
                ]);
            }
            $lines = collect($data['items'])->groupBy('product_id')->map(function ($rows, $productId) {
                return [
                    'product_id' => (int) $productId,
                    'quantity' => $rows->sum('quantity'),
                    'price' => $rows->last()['price'] ?? null,
                    'discount_rate' => (int) ($rows->last()['discount_rate'] ?? 0),
                    'tax_rate' => (int) ($rows->last()['tax_rate'] ?? 0),
                ];
            })->sortBy('product_id')->values();
            $lockedProducts = Product::where('business_id', $businessId)
                ->whereIn('id', $lines->pluck('product_id')->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $subtotal = 0.0;
            $saleItems = [];
            $costTotal = 0.0;

            foreach ($lines as $line) {
                $product = $lockedProducts->get($line['product_id']);
                if (!$product) {
                    throw ValidationException::withMessages([
                        'items' => 'One or more selected products are not available for this business.',
                    ]);
                }
                if ($product->stock_quantity < $line['quantity']) {
                    throw ValidationException::withMessages(['items' => 'Insufficient stock. Only '.$product->stock_quantity.' units are available.']);
                }

                $price = $line['price'] === null ? (float) $product->retail_price : (float) $line['price'];
                if ($line['price'] !== null && round($price, 2) !== round((float) $product->retail_price, 2) && !$this->permissions->allowsUser(auth()->user(), 'pos.custom_price')) {
                    throw ValidationException::withMessages(['items' => 'You do not have permission to use a custom price.']);
                }
                $amounts = $this->finance->calculateLineAmounts(
                    (int) $line['quantity'],
                    $price,
                    (int) $line['discount_rate'],
                    (int) $line['tax_rate'],
                );
                $lineTotal = $amounts['lineTotal'];
                $subtotal += $lineTotal;
                $costTotal += round((float) ($product->purchase_cost ?: $product->wholesale_price) * $line['quantity'], 2);
                $saleItems[] = compact('product', 'price', 'lineTotal', 'amounts') + ['quantity' => $line['quantity']];
            }

            $discountValue = (float) ($data['discount_value'] ?? 0);
            if ($data['discount_type'] === 'percentage' && $discountValue > 100) {
                throw ValidationException::withMessages([
                    'discount_value' => 'Percentage discount cannot exceed 100%.',
                ]);
            }
            if ($discountValue > 0 && !$this->permissions->allowsUser(auth()->user(), 'pos.apply_discount')) {
                throw ValidationException::withMessages(['discount_value' => 'You do not have permission to apply a discount.']);
            }
            $discount = $data['discount_type'] === 'percentage'
                ? round($subtotal * min(100, $discountValue) / 100, 2)
                : min($subtotal, round($discountValue, 2));
            $taxRate = (float) ($data['tax_rate'] ?? 0);
            $taxAmount = round(($subtotal - $discount) * $taxRate / 100, 2);
            $grandTotal = round($subtotal - $discount + $taxAmount, 2);

            $payments = collect($data['payments'] ?? [])->filter(fn ($payment) => (float) ($payment['amount'] ?? 0) > 0)->values();
            if ($data['payment_mode'] === 'cash' && $payments->isEmpty()) {
                $payments = collect([['method' => 'Cash', 'amount' => $grandTotal, 'reference_number' => null]]);
            }
            if ($data['payment_mode'] === 'split' && !$this->permissions->allowsUser(auth()->user(), 'pos.split_payment')) {
                throw ValidationException::withMessages(['payment_mode' => 'You do not have permission to take split payments.']);
            }
            $paid = round((float) $payments->sum('amount'), 2);
            if ($paid > $grandTotal) {
                throw ValidationException::withMessages(['payments' => 'Collected payment cannot exceed the sale total.']);
            }
            if ($data['payment_mode'] === 'cash' && $paid < $grandTotal) {
                throw ValidationException::withMessages(['payments' => 'Cash sales must be paid in full. Use split or credit for a remaining balance.']);
            }
            if ($data['payment_mode'] === 'credit' && $paid > 0) {
                throw ValidationException::withMessages(['payments' => 'Credit sales cannot include a payment. Use split payment instead.']);
            }
            if (($data['payment_mode'] !== 'cash' || $paid < $grandTotal) && !$this->permissions->allowsUser(auth()->user(), 'pos.credit_sale')) {
                throw ValidationException::withMessages(['payment_mode' => 'You do not have permission to create credit sales.']);
            }
            $balance = round($grandTotal - $paid, 2);
            if ($customer && $balance > 0 && (float) $customer->credit_limit > 0 && ((float) $customer->current_balance + $balance) > (float) $customer->credit_limit) {
                throw ValidationException::withMessages(['payment_mode' => 'This sale exceeds the selected customer credit limit.']);
            }

            $receiptNumber = $this->numbers->next('pos');
            $order = Order::create([
                'order_number' => $receiptNumber, 'business_id' => $businessId, 'customer_id' => $customer?->id,
                'pos_register_id' => $register->id, 'created_by' => auth()->id(), 'order_date' => now(), 'sale_channel' => 'pos',
                'subtotal' => $subtotal, 'discount' => $data['discount_type'] === 'percentage' ? $discountValue : 0,
                'discount_percentage' => $data['discount_type'] === 'percentage' ? $discountValue : 0, 'discount_amount' => $discount,
                'tax_rate' => $taxRate, 'tax_amount' => $taxAmount, 'total' => $grandTotal, 'grand_total' => $grandTotal,
                'paid_amount' => $paid, 'balance' => $balance, 'payment_type' => $balance <= 0 ? 'Cash' : ($paid > 0 ? 'Partial' : 'Credit'),
                'payment_status' => $balance <= 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending'), 'status' => 'Completed',
            ]);

            foreach ($saleItems as $line) {
                $product = $line['product'];
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'quantity' => $line['quantity'],
                    'unit' => $product->unit,
                    'unit_price' => $line['price'],
                    'purchase_cost_snapshot' => $product->purchase_cost,
                    'line_subtotal' => $line['amounts']['lineSubtotal'],
                    'discount_rate' => $line['amounts']['discountRate'],
                    'discount_amount' => $line['amounts']['discountAmount'],
                    'tax_rate' => $line['amounts']['taxRate'],
                    'tax_amount' => $line['amounts']['taxAmount'],
                    'line_total' => $line['lineTotal'],
                    'price' => $line['price'],
                    'total' => $line['lineTotal'],
                ]);
                $product->decrement('stock_quantity', $line['quantity']);
                $fresh = $product->fresh();
                Inventory::updateOrCreate(['business_id' => $businessId, 'product_id' => $product->id], ['available_stock' => $fresh->stock_quantity, 'low_stock_alert' => $fresh->low_stock_alert_qty ?? 10]);
                Inventory::where('business_id', $businessId)->where('product_id', $product->id)->increment('sold_stock', $line['quantity']);
                StockMovement::create(['business_id' => $businessId, 'product_id' => $product->id, 'type' => 'sold', 'quantity' => $line['quantity'], 'reason' => 'POS sale', 'note' => 'POS '.$order->order_number, 'user_id' => auth()->id(), 'created_by' => auth()->id()]);
                InventoryMovement::create(['business_id' => $businessId, 'product_id' => $product->id, 'type' => 'SOLD', 'quantity' => $line['quantity'], 'previous_stock' => $fresh->stock_quantity + $line['quantity'], 'new_stock' => $fresh->stock_quantity, 'note' => 'POS '.$order->order_number, 'created_by' => auth()->id(), 'movement_date' => now()]);
            }

            foreach ($payments as $paymentLine) {
                $payment = Payment::create(['business_id' => $businessId, 'order_id' => $order->id, 'customer_id' => $customer?->id, 'method' => $paymentLine['method'], 'amount' => $paymentLine['amount'], 'transaction_reference' => $paymentLine['reference_number'] ?? null, 'reference_number' => $paymentLine['reference_number'] ?? null, 'payment_date' => now()->toDateString(), 'status' => 'Paid']);
                PosPayment::create(['business_id' => $businessId, 'order_id' => $order->id, 'pos_register_id' => $register->id, 'method' => $payment->method, 'amount' => $payment->amount, 'reference_number' => $payment->reference_number, 'created_by' => auth()->id()]);
            }

            if ($customer && $balance > 0) {
                $newBalance = round((float) $customer->current_balance + $balance, 2);
                $customer->update(['current_balance' => $newBalance]);
                KhataLedger::create(['business_id' => $businessId, 'customer_id' => $customer->id, 'order_id' => $order->id, 'entry_type' => 'purchase', 'type' => 'credit', 'amount' => $balance, 'customer_debit' => 0, 'customer_credit' => $balance, 'business_debit' => $balance, 'business_credit' => 0, 'description' => 'POS credit sale '.$order->order_number, 'balance' => $newBalance, 'balance_after' => $newBalance, 'entry_date' => now()->toDateString()]);
            }

            $invoice = Invoice::create(['business_id' => $businessId, 'order_id' => $order->id, 'invoice_number' => $receiptNumber, 'customer_id' => $customer?->id, 'invoice_date' => now()->toDateString(), 'subtotal' => $subtotal, 'discount_percentage' => $order->discount_percentage, 'discount_amount' => $discount, 'grand_total' => $grandTotal, 'paid_amount' => $paid, 'balance' => $balance, 'payment_status' => $order->payment_status, 'status' => $balance <= 0 ? 'Paid' : 'Issued', 'issued_by' => auth()->id(), 'issued_at' => now()]);
            foreach ($order->items as $item) {
                $invoice->items()->create(['product_id' => $item->product_id, 'product_name_snapshot' => $item->product_name_snapshot, 'quantity' => $item->quantity, 'unit' => $item->unit, 'unit_price' => $item->unit_price, 'line_total' => $item->line_total]);
            }

            $this->postSaleAccounting($order, $paid, $balance, $costTotal);
            $this->audit('POS sale completed '.$order->order_number, $order->id, ['grand_total' => $grandTotal, 'paid_amount' => $paid]);

            return $order;
        });

        return redirect()->route('business.pos.sales.completed', $order)->with('success', 'POS sale saved successfully.');
    }

    public function completed(Order $order)
    {
        $this->scopedPosOrder($order);

        return view('business.pos.completed', [
            'order' => $order->load(['business', 'customer', 'items.product', 'posPayments', 'invoice']),
        ]);
    }

    public function history(Request $request)
    {
        $businessId = $this->businessId();
        $orders = Order::with(['customer', 'payments', 'posReturns'])->where('business_id', $businessId)->where('sale_channel', 'pos')
            ->when($request->filled('search'), fn ($query) => $query->where('order_number', 'like', '%'.$request->search.'%'))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('order_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('order_date', '<=', $request->date_to))
            ->latest('order_date')->paginate(20)->withQueryString();

        return view('business.pos.history', compact('orders'));
    }

    public function receipt(Order $order)
    {
        $this->scopedPosOrder($order);

        return view('business.pos.receipt', ['order' => $order->load(['business', 'customer', 'items.product', 'posPayments', 'invoice'])]);
    }

    public function receiptPdf(Order $order)
    {
        $this->scopedPosOrder($order);
        $order->load(['business', 'customer', 'items.product', 'posPayments', 'invoice']);

        return Pdf::loadView('business.pos.receipt-pdf', compact('order'))
            ->setPaper('a4')
            ->stream($order->order_number.'-receipt.pdf');
    }

    public function downloadReceiptPdf(Order $order)
    {
        $this->scopedPosOrder($order);
        $order->load(['business', 'customer', 'items.product', 'posPayments', 'invoice']);

        return Pdf::loadView('business.pos.receipt-pdf', compact('order'))
            ->setPaper('a4')
            ->download($order->order_number.'-receipt.pdf');
    }

    public function void(Request $request, Order $order)
    {
        $this->scopedPosOrder($order);
        if (!in_array($order->status, ['New', 'Accepted'], true) || (float) $order->paid_amount > 0) {
            return back()->withErrors(['order' => 'Only unpaid POS drafts can be voided. Use a return for completed or paid sales.']);
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items()->lockForUpdate()->get() as $item) {
                $product = Product::withTrashed()->where('business_id', $order->business_id)->lockForUpdate()->find($item->product_id);
                if (!$product) continue;

                $previous = (int) $product->stock_quantity;
                $product->increment('stock_quantity', $item->quantity);
                $current = $previous + (int) $item->quantity;
                Inventory::updateOrCreate(['business_id' => $order->business_id, 'product_id' => $product->id], ['available_stock' => $current]);
                Inventory::where('business_id', $order->business_id)->where('product_id', $product->id)->decrement('sold_stock', $item->quantity);
                InventoryMovement::create(['business_id' => $order->business_id, 'product_id' => $product->id, 'type' => 'RETURNED', 'quantity' => $item->quantity, 'previous_stock' => $previous, 'new_stock' => $current, 'note' => 'Voided POS draft '.$order->order_number, 'created_by' => auth()->id(), 'movement_date' => now()]);
            }
            $order->update(['status' => 'Void', 'balance' => 0, 'payment_status' => 'Void']);
            $order->invoice?->update(['status' => 'Void', 'voided_by' => auth()->id(), 'voided_at' => now(), 'void_reason' => 'POS draft voided']);
            $this->audit('POS draft voided '.$order->order_number, $order->id);
        });

        return redirect()->route('business.pos.history')->with('success', 'POS draft voided and stock restored.');
    }

    public function returns(Order $order)
    {
        $this->scopedPosOrder($order);
        if (!$this->permissions->allowsUser(auth()->user(), 'sales_returns.view')) {
            return redirect()->route('business.pos.history')->withErrors([
                'permission' => 'You do not have permission to process POS returns.',
            ]);
        }

        return view('business.pos.return', ['order' => $order->load(['customer', 'items.product', 'posReturns.items'])]);
    }

    public function storeReturn(Request $request, Order $order)
    {
        $this->scopedPosOrder($order);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'refund_method' => ['required', 'in:Cash,Store Credit,Bank Transfer'],
            'items' => ['required', 'array'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        if (!$this->permissions->allowsUser(auth()->user(), 'sales_returns.process')) {
            return redirect()->route('business.pos.history')->withErrors([
                'permission' => 'You do not have permission to process POS returns.',
            ]);
        }

        try {
            $salesReturn = DB::transaction(function () use ($order, $data) {
            $return = PosReturn::create(['business_id' => $order->business_id, 'return_number' => $this->numbers->next('sales_return'), 'order_id' => $order->id, 'customer_id' => $order->customer_id, 'processed_by' => auth()->id(), 'refund_method' => $data['refund_method'], 'reason' => $data['reason'], 'returned_at' => now()]);
            $refund = 0.0;
            $cost = 0.0;
            $returnedCount = 0;
            foreach (collect($data['items'])->filter(fn ($item) => (int) $item['quantity'] > 0) as $line) {
                $item = OrderItem::where('order_id', $order->id)->lockForUpdate()->findOrFail($line['order_item_id']);
                $alreadyReturned = $item->id ? $item->posReturnItems()->sum('quantity') : 0;
                $remainingReturnable = max(0, (int) $item->quantity - (int) $alreadyReturned);
                if ((int) $line['quantity'] > $remainingReturnable) {
                    throw ValidationException::withMessages([
                        'items' => 'Return quantity cannot exceed available items. Only '.$remainingReturnable.' units are available.',
                    ]);
                }
                // Refund the stored sale value so item-level tax and discount are not lost.
                $unitRefund = (float) ($item->line_total ?? $item->total ?? 0) / max(1, (int) $item->quantity);
                $lineRefund = round($unitRefund * $line['quantity'], 2);
                $return->items()->create(['order_item_id' => $item->id, 'quantity' => $line['quantity'], 'refund_total' => $lineRefund]);
                $product = Product::withTrashed()->where('business_id', $order->business_id)->find($item->product_id);
                if ($product) {
                    $product->increment('stock_quantity', $line['quantity']);
                    $fresh = $product->fresh();
                    Inventory::updateOrCreate(['business_id' => $order->business_id, 'product_id' => $product->id], ['available_stock' => $fresh->stock_quantity]);
                    Inventory::where('business_id', $order->business_id)->where('product_id', $product->id)->decrement('sold_stock', $line['quantity']);
                    StockMovement::create(['business_id' => $order->business_id, 'product_id' => $product->id, 'type' => 'returned', 'quantity' => $line['quantity'], 'reason' => 'POS return', 'note' => 'POS return '.$order->order_number, 'user_id' => auth()->id(), 'created_by' => auth()->id()]);
                    InventoryMovement::create(['business_id' => $order->business_id, 'product_id' => $product->id, 'type' => 'RETURNED', 'quantity' => $line['quantity'], 'previous_stock' => $fresh->stock_quantity - $line['quantity'], 'new_stock' => $fresh->stock_quantity, 'note' => 'POS return '.$order->order_number, 'created_by' => auth()->id(), 'movement_date' => now()]);
                }
                $refund += $lineRefund;
                $cost += round((float) ($item->purchase_cost_snapshot ?? 0) * $line['quantity'], 2);
                $returnedCount += $line['quantity'];
            }
            if ($returnedCount === 0) {
                throw ValidationException::withMessages([
                    'items' => 'Select at least one item to return.',
                ]);
            }
            $return->update(['refund_amount' => $refund]);
            if ($data['refund_method'] === 'Store Credit' && $order->customer) {
                $order->customer->update(['current_balance' => max(0, (float) $order->customer->current_balance - $refund)]);
            }
            $this->postReturnAccounting($order, $return, $cost);
            if ($order->items->sum('quantity') === $order->items->sum(fn ($item) => $item->posReturnItems()->sum('quantity'))) {
                $order->update(['status' => 'Returned']);
            }
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

        $this->audit('Sales return completed '.$salesReturn->return_number, $salesReturn->id, [
            'return_number' => $salesReturn->return_number,
            'refund_amount' => $salesReturn->refund_amount,
            'notification_title' => 'Sales Return Completed',
            'notification_message' => 'Sales return '.$salesReturn->return_number.' has been processed successfully.',
        ]);

        $destination = $request->routeIs('business.sales.returns.*')
            ? route('business.sales.returns.show', $salesReturn)
            : route('business.pos.history');

        return redirect($destination)->with('tradeflow_return_alert', [
            'title' => 'Sales Return Completed',
            'message' => 'Sales return has been processed successfully. Stock, customer balance, payment and related accounting entries have been updated. Return No: '.$salesReturn->return_number,
        ]);
    }

    public function report()
    {
        $businessId = $this->businessId();
        $from = request('date_from', now()->startOfMonth()->toDateString());
        $to = request('date_to', now()->toDateString());
        $orders = Order::where('business_id', $businessId)->where('sale_channel', 'pos')->whereBetween('order_date', [$from, $to.' 23:59:59']);

        return view('business.pos.report', [
            'dateFrom' => $from, 'dateTo' => $to,
            'salesTotal' => (clone $orders)->whereNotIn('status', ['Cancelled', 'Void', 'Returned'])->sum('grand_total'),
            'salesCount' => (clone $orders)->count(),
            'returnsTotal' => PosReturn::where('business_id', $businessId)->whereBetween('returned_at', [$from, $to.' 23:59:59'])->sum('refund_amount'),
            'paymentsByMethod' => PosPayment::where('business_id', $businessId)->whereBetween('created_at', [$from, $to.' 23:59:59'])->selectRaw('method, SUM(amount) total')->groupBy('method')->get(),
        ]);
    }

    private function resolveCustomer(array $data, int $businessId): ?Customer
    {
        if (!empty($data['customer_id']) && $data['customer_id'] !== 'walk_in' && $data['customer_id'] !== 'new') {
            return Customer::where('business_id', $businessId)->findOrFail($data['customer_id']);
        }
        if (($data['customer_id'] ?? null) === 'new') {
            if (empty($data['new_customer_name']) && empty($data['new_customer_phone'])) {
                throw ValidationException::withMessages(['customer_id' => 'Enter a customer name or phone number.']);
            }
            return Customer::create(['business_id' => $businessId, 'name' => $data['new_customer_name'] ?: $data['new_customer_phone'], 'phone' => $data['new_customer_phone'] ?? null, 'city' => $data['new_customer_city'] ?? null, 'address' => $data['new_customer_address'] ?? null, 'customer_type' => 'Retailer', 'status' => 'Active', 'created_by' => auth()->id()]);
        }

        return null;
    }

    private function postSaleAccounting(Order $order, float $paid, float $balance, float $cost): void
    {
        $this->accounting->ensureDefaultAccounts($order->business_id);
        $accounts = Account::where('business_id', $order->business_id)->whereIn('name', ['Cash', 'Accounts Receivable', 'Sales Revenue', 'Cost of Goods Sold', 'Inventory'])->pluck('id', 'name');
        if ($accounts->count() < 3) return;
        $lines = [];
        if ($paid > 0) $lines[] = ['account_id' => $accounts['Cash'], 'customer_id' => $order->customer_id, 'debit' => $paid, 'credit' => 0, 'description' => 'POS payment '.$order->order_number];
        if ($balance > 0) $lines[] = ['account_id' => $accounts['Accounts Receivable'], 'customer_id' => $order->customer_id, 'debit' => $balance, 'credit' => 0, 'description' => 'POS receivable '.$order->order_number];
        $lines[] = ['account_id' => $accounts['Sales Revenue'], 'customer_id' => $order->customer_id, 'debit' => 0, 'credit' => $order->grand_total, 'description' => 'POS sale '.$order->order_number];
        if ($cost > 0 && isset($accounts['Cost of Goods Sold'], $accounts['Inventory'])) {
            $lines[] = ['account_id' => $accounts['Cost of Goods Sold'], 'debit' => $cost, 'credit' => 0, 'description' => 'POS cost '.$order->order_number];
            $lines[] = ['account_id' => $accounts['Inventory'], 'debit' => 0, 'credit' => $cost, 'description' => 'POS inventory '.$order->order_number];
        }
        $this->accounting->post($order->business_id, ['voucher_number' => 'POS-JV-'.$order->id.'-'.now()->format('His'), 'entry_date' => now()->toDateString(), 'reference_type' => 'pos_sale', 'reference_id' => $order->id, 'description' => 'POS sale '.$order->order_number], $lines);
    }

    private function postReturnAccounting(Order $order, PosReturn $return, float $cost): void
    {
        $this->accounting->ensureDefaultAccounts($order->business_id);
        $accounts = Account::where('business_id', $order->business_id)->whereIn('name', ['Cash', 'Accounts Receivable', 'Sales Revenue', 'Cost of Goods Sold', 'Inventory'])->pluck('id', 'name');
        if (!isset($accounts['Sales Revenue'])) return;
        $creditAccount = $return->refund_method === 'Store Credit' ? ($accounts['Accounts Receivable'] ?? null) : ($accounts['Cash'] ?? null);
        if (!$creditAccount) return;
        $lines = [
            ['account_id' => $accounts['Sales Revenue'], 'customer_id' => $order->customer_id, 'debit' => $return->refund_amount, 'credit' => 0, 'description' => 'POS return '.$order->order_number],
            ['account_id' => $creditAccount, 'customer_id' => $order->customer_id, 'debit' => 0, 'credit' => $return->refund_amount, 'description' => $return->refund_method],
        ];
        if ($cost > 0 && isset($accounts['Cost of Goods Sold'], $accounts['Inventory'])) {
            $lines[] = ['account_id' => $accounts['Inventory'], 'debit' => $cost, 'credit' => 0, 'description' => 'Returned stock'];
            $lines[] = ['account_id' => $accounts['Cost of Goods Sold'], 'debit' => 0, 'credit' => $cost, 'description' => 'Returned stock'];
        }
        $this->accounting->post($order->business_id, ['voucher_number' => 'POS-RET-'.$return->id.'-'.now()->format('His'), 'entry_date' => now()->toDateString(), 'reference_type' => 'pos_return', 'reference_id' => $return->id, 'description' => 'POS return '.$order->order_number], $lines);
    }

    private function currentRegister(): ?PosRegister
    {
        return PosRegister::where('business_id', $this->businessId())->where('user_id', auth()->id())->where('status', 'Open')->latest('opened_at')->first();
    }

    private function scopedRegister(PosRegister $register): void
    {
        abort_unless($register->business_id === $this->businessId() && ($register->user_id === auth()->id() || auth()->user()->role === 'business_owner'), 403);
    }

    private function scopedPosOrder(Order $order): void
    {
        abort_unless($order->business_id === $this->businessId() && $order->sale_channel === 'pos', 404);
    }

    private function businessId(): int { return (int) auth()->user()->business_id; }

    private function audit(string $action, int $recordId, array $newValues = []): void
    {
        $this->activity->record($this->businessId(), 'POS', $action, $recordId, null, $newValues);
    }
}
