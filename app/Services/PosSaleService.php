<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\HeldPosSale;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosRegister;
use App\Models\PosRegisterCashMovement;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\KhataLedger;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosSaleService
{
    private const RESUMABLE_HOLD_STATUSES = ['Held', 'Resumed'];

    public function __construct(
        private FinanceCalculator $finance,
        private AccountingService $accounting,
        private BusinessActivityService $activity,
        private CompanyPermissionService $permissions,
        private DocumentNumberService $numbers,
        private SubscriptionLimitService $limits,
        private ProductBatchService $batches,
        private PosPaymentBreakdown $paymentBreakdown,
    ) {}

    public function openRegister(int $businessId, int $userId, array $data): PosRegister
    {
        return DB::transaction(function () use ($businessId, $userId, $data): PosRegister {
            $existing = PosRegister::where('business_id', $businessId)->where('user_id', $userId)->where('status', 'Open')->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $register = PosRegister::create([
                'business_id' => $businessId,
                'user_id' => $userId,
                'opening_cash' => (int) ($data['opening_cash'] ?? 0),
                'opening_note' => $data['opening_note'] ?? null,
                'status' => 'Open',
                'opened_at' => now(),
            ]);
            $this->activity->record($businessId, 'POS', 'POS register opened', $register->id, null, ['opening_cash' => $register->opening_cash]);

            return $register;
        });
    }

    public function closeRegister(PosRegister $register, int $businessId, int $userId, array $data): PosRegister
    {
        return DB::transaction(function () use ($register, $businessId, $userId, $data): PosRegister {
            $register = PosRegister::where('business_id', $businessId)->where('user_id', $userId)->lockForUpdate()->findOrFail($register->id);
            if ($register->status !== 'Open') {
                throw ValidationException::withMessages(['register' => 'This register is already closed.']);
            }

            $summary = $this->reconciliationFor($register, $businessId);
            $expected = $summary['expected_cash'];
            $actual = (int) ($data['closing_cash'] ?? 0);
            $register->update([
                'status' => 'Closed',
                'cash_sales' => $summary['cash_sales'],
                'cash_refunds' => $summary['cash_refunds'],
                'cash_in' => $summary['cash_in'],
                'cash_out' => $summary['cash_out'],
                'expected_cash' => $expected,
                'closing_cash' => $actual,
                'variance' => $actual - $expected,
                'closing_note' => $data['closing_note'] ?? null,
                'closed_at' => now(),
            ]);
            $this->activity->record($businessId, 'POS', 'POS register closed', $register->id, null, ['variance' => $register->variance]);

            return $register->fresh();
        });
    }

    /** @return array{opening_cash:int,cash_sales:int,cash_refunds:int,cash_in:int,cash_out:int,expected_cash:int} */
    public function reconciliation(PosRegister $register, int $businessId, int $userId): array
    {
        $register = PosRegister::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->findOrFail($register->id);

        return $this->reconciliationFor($register, $businessId);
    }

    public function recordCashMovement(PosRegister $register, int $businessId, int $userId, array $data): PosRegisterCashMovement
    {
        return DB::transaction(function () use ($register, $businessId, $userId, $data): PosRegisterCashMovement {
            $register = PosRegister::query()
                ->where('business_id', $businessId)
                ->where('user_id', $userId)
                ->where('status', 'Open')
                ->lockForUpdate()
                ->findOrFail($register->id);

            $movement = PosRegisterCashMovement::create([
                'business_id' => $businessId,
                'pos_register_id' => $register->id,
                'recorded_by' => $userId,
                'type' => $data['type'],
                'amount' => $data['amount'],
                'reason' => $data['reason'],
                'reference' => $data['reference'] ?? null,
                'occurred_at' => now(),
            ]);

            $this->activity->record($businessId, 'POS', 'POS register '.$data['type'].' recorded', $register->id, null, [
                'amount' => (float) $data['amount'],
                'reason' => $data['reason'],
                'reference' => $data['reference'] ?? null,
            ]);

            return $movement;
        });
    }

    public function hold(PosRegister $register, int $businessId, int $userId, array $cart, array $checkout, ?string $requestedHoldNumber = null, ?int $existingHeldSaleId = null): HeldPosSale
    {
        if ($cart === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one product before holding a sale.']);
        }

        $manualHoldNumber = $this->normalizeHoldNumber($requestedHoldNumber);

        try {
            return DB::transaction(function () use ($register, $businessId, $userId, $cart, $checkout, $manualHoldNumber, $existingHeldSaleId): HeldPosSale {
                $register = PosRegister::where('business_id', $businessId)->where('user_id', $userId)->where('status', 'Open')->lockForUpdate()->findOrFail($register->id);
                $existingHeldSale = $existingHeldSaleId
                    ? HeldPosSale::where('business_id', $businessId)->lockForUpdate()->findOrFail($existingHeldSaleId)
                    : null;

                if ($existingHeldSale && $existingHeldSale->status !== 'Resumed') {
                    throw ValidationException::withMessages(['held_sale_id' => 'This held sale is no longer available for update.']);
                }

                $holdNumber = $manualHoldNumber ?? $existingHeldSale?->hold_number ?? $this->numbers->next($businessId, 'pos_hold');

                $conflict = $this->holdsForBusiness($businessId)
                    ->where('hold_number', $holdNumber)
                    ->when($existingHeldSale, fn ($query) => $query->where((new HeldPosSale)->getQualifiedKeyName(), '!=', $existingHeldSale->id))
                    ->exists();
                if ($conflict) {
                    $message = $existingHeldSale
                        ? $holdNumber.' belongs to another held sale. Please use the current Hold ID or enter a new unique Hold ID.'
                        : $holdNumber.' is already in use. Please enter a different Hold ID.';
                    throw ValidationException::withMessages(['hold_number' => $message]);
                }

                $attributes = [
                    'business_id' => $businessId,
                    'pos_register_id' => $register->id,
                    'user_id' => $userId,
                    'hold_number' => $holdNumber,
                    'customer_id' => $checkout['customer_id'] ?? null,
                    'cart_payload' => $cart,
                    'checkout_payload' => $checkout,
                    'status' => 'Held',
                    'held_at' => now(),
                ];
                if ($existingHeldSale) {
                    $existingHeldSale->update($attributes);
                    $held = $existingHeldSale->fresh();
                    $this->activity->record($businessId, 'POS', 'POS sale held again', $held->id, null, ['hold_number' => $holdNumber]);
                } else {
                    $held = HeldPosSale::create($attributes);
                    $this->activity->record($businessId, 'POS', 'POS sale held', $held->id, null, ['hold_number' => $holdNumber]);
                }

                return $held;
            });
        } catch (QueryException $exception) {
            // The database unique index remains the final protection against a
            // concurrent duplicate request. Do not replace a cashier's choice.
            if ($manualHoldNumber && (string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages(['hold_number' => $manualHoldNumber.' is already in use. Please enter a different Hold ID.']);
            }

            throw $exception;
        }
    }

    /**
     * Convert every supported cashier entry to the one persisted hold format.
     * This is deliberately shared by hold creation and held-sale lookup.
     */
    public function normalizeHoldNumber(?string $value): ?string
    {
        $value = strtoupper(trim((string) $value));
        if ($value === '') return null;

        if (! preg_match('/^(?:HOLD-)?(\d{1,6})$/', $value, $matches)) {
            throw ValidationException::withMessages(['hold_number' => 'Enter a Hold ID like HOLD-000007.']);
        }

        return 'HOLD-'.str_pad($matches[1], 6, '0', STR_PAD_LEFT);
    }

    public function holdsForBusiness(int $businessId): Builder
    {
        return HeldPosSale::query()->where('business_id', $businessId);
    }

    public function resumableHoldsForBusiness(int $businessId): Builder
    {
        // "Resumed" is the in-progress state in the current POS lifecycle.
        // It remains recoverable after a refresh until it is re-held or
        // completed, so it must be searchable alongside a normal Held sale.
        return $this->holdsForBusiness($businessId)
            ->whereIn('status', self::RESUMABLE_HOLD_STATUSES);
    }

    public function resume(int $heldSaleId, int $businessId): HeldPosSale
    {
        return DB::transaction(function () use ($heldSaleId, $businessId): HeldPosSale {
            $held = $this->holdsForBusiness($businessId)
                ->lockForUpdate()
                ->find($heldSaleId);

            if (! $held) {
                throw ValidationException::withMessages(['held_sale' => 'Hold number not found.']);
            }
            if ($held->status === 'Completed') {
                throw ValidationException::withMessages(['held_sale' => 'This held sale has already been completed.']);
            }
            if (! in_array($held->status, self::RESUMABLE_HOLD_STATUSES, true)) {
                throw ValidationException::withMessages(['held_sale' => 'This held sale is no longer available.']);
            }

            if ($held->status === 'Held') {
                $held->update(['status' => 'Resumed', 'resumed_at' => now()]);
                $this->activity->record($businessId, 'POS', 'POS sale resumed', $held->id, null, ['hold_number' => $held->hold_number]);
            } else {
                $this->activity->record($businessId, 'POS', 'POS sale restored', $held->id);
            }

            return $held->fresh();
        });
    }

    public function complete(int $businessId, int $userId, array $data): Order
    {
        return DB::transaction(function () use ($businessId, $userId, $data): Order {
            $this->limits->assertCanCreateOrder($businessId);
            $register = PosRegister::where('business_id', $businessId)->where('user_id', $userId)->where('status', 'Open')->lockForUpdate()->first();
            if (! $register) {
                throw ValidationException::withMessages(['register' => 'Open a register before completing a sale.']);
            }
            $resumedHeldSale = ! empty($data['held_sale_id'])
                ? HeldPosSale::where('business_id', $businessId)->lockForUpdate()->findOrFail($data['held_sale_id'])
                : null;
            if ($resumedHeldSale && $resumedHeldSale->status !== 'Resumed') {
                throw ValidationException::withMessages(['held_sale_id' => 'This held sale is no longer available for completion.']);
            }

            $paymentType = (string) $data['payment_type'];
            if (($data['discount'] ?? 0) > 0 || collect($data['items'])->contains(fn (array $line) => (float) ($line['discount_value'] ?? $line['discount_rate'] ?? 0) > 0)) {
                $this->requirePermission('pos.apply_discount');
            }
            if ($paymentType === 'Credit') {
                $this->requirePermission('pos.credit_sale');
            }
            if ($paymentType === 'Split') {
                $this->requirePermission('pos.split_payment');
            }
            $customer = $this->resolveCustomer($businessId, $data['customer_id'] ?? null, $data['quick_customer'] ?? null, $userId);
            $deliveryRequired = filter_var($data['delivery_required'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $deliveryAddress = trim((string) ($data['delivery_address'] ?? ''));
            if ($deliveryRequired && ! $customer) {
                throw ValidationException::withMessages(['customer_id' => 'Select or create a customer before requesting delivery.']);
            }
            if ($deliveryRequired && $deliveryAddress === '') {
                throw ValidationException::withMessages(['delivery_address' => 'A delivery address is required when delivery is requested.']);
            }

            $requested = collect($data['items'])->keyBy('product_id');
            $products = Product::where('business_id', $businessId)->whereIn('id', $requested->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== $requested->count()) {
                throw ValidationException::withMessages(['items' => 'One or more products are unavailable.']);
            }
            foreach ($requested as $productId => $line) {
                $product = $products->get((int) $productId);
                if ((int) $product->stock_quantity < (int) $line['quantity']) {
                    throw ValidationException::withMessages(['items' => 'Insufficient stock for '.$product->name.'. Only '.$product->stock_quantity.' units are available.']);
                }
                $this->batches->assertSellable($product, (float) $line['quantity']);
            }

            $discount = (int) ($data['discount'] ?? 0);
            $tax = (int) ($data['tax_rate'] ?? 0);
            $preparedLines = collect();
            foreach ($requested as $productId => $line) {
                $product = $products->get((int) $productId);
                $defaultPrice = (int) ($product->retail_price ?: $product->wholesale_price ?: 0);
                $price = $defaultPrice;
                if ($defaultPrice <= 0 && (!isset($line['unit_price']) || (int) $line['unit_price'] <= 0)) {
                    throw ValidationException::withMessages(['items' => $product->name.' has no valid selling price. Set an approved unit price before completing the sale.']);
                }
                if ($defaultPrice > 0 && isset($line['unit_price']) && (int) $line['unit_price'] === 0) {
                    throw ValidationException::withMessages(['items' => 'A product with a selling price cannot be completed with a zero unit price.']);
                }
                $isOverride = isset($line['unit_price']) && (int) $line['unit_price'] !== $defaultPrice;
                $overrideReason = trim((string) ($line['price_override_reason'] ?? ''));
                if ($isOverride) {
                    if (! $this->permissions->allowsUser(auth()->user(), 'pos.override_price')) {
                        throw ValidationException::withMessages(['items' => 'You do not have permission to override a POS price.']);
                    }
                    $price = (int) $line['unit_price'];
                    $purchasePrice = (float) ($product->latest_purchase_price ?: $product->average_purchase_price ?: $product->purchase_cost ?: 0);
                    if ($price <= 0 || ($purchasePrice > 0 && $price <= $purchasePrice)) {
                        throw ValidationException::withMessages(['items' => 'An overridden sale price must be greater than the current purchase price.']);
                    }
                    if ($overrideReason === '') {
                        throw ValidationException::withMessages(['items' => 'Provide a reason for each overridden sale price.']);
                    }
                }
                $discountType = (string) ($line['discount_type'] ?? ((int) ($line['discount_rate'] ?? 0) > 0 ? 'percentage' : 'none'));
                $discountValue = (float) ($line['discount_value'] ?? $line['discount_rate'] ?? 0);
                $amounts = $this->finance->calculatePosLineAmounts((int) $line['quantity'], $price, $discountType, $discountValue, (int) ($line['tax_rate'] ?? 0));
                $preparedLines->push(compact('product', 'line', 'price', 'amounts', 'defaultPrice', 'isOverride', 'overrideReason'));
            }

            ['subtotal' => $subtotal, 'discountAmount' => $discountAmount, 'taxAmount' => $taxAmount, 'grandTotal' => $grandTotal] = $this->finance->salesAmountsFromLines($preparedLines->map(fn ($line) => ['line_total' => $line['amounts']['lineTotal']]), $discount, $tax);
            $grandTotal = (int) round($grandTotal, 0, PHP_ROUND_HALF_UP);
            $payment = $this->paymentBreakdown->calculate($data, $grandTotal);
            $paymentType = $payment['type'];
            $paid = $payment['paid'];
            if ($payment['balance'] > 0) {
                if ($paymentType !== 'Credit') {
                    $this->requirePermission('pos.credit_sale');
                }
                if (! $customer) {
                    throw ValidationException::withMessages(['customer_id' => 'A registered customer is required when a sale has an outstanding balance.']);
                }
                $availableCredit = max(0, (int) $customer->credit_limit - (int) $customer->current_balance);
                if ($payment['balance'] > $availableCredit) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'This customer does not have sufficient credit available for this sale.',
                    ]);
                }
            }

            $number = $this->numbers->next($businessId, 'sales');
            $order = Order::create([
                'order_number' => $number,
                'business_id' => $businessId,
                'customer_id' => $customer?->id,
                'created_by' => $userId,
                'order_date' => now(),
                'subtotal' => (int) round($subtotal, 0, PHP_ROUND_HALF_UP),
                'discount' => $discount,
                'discount_percentage' => $discount,
                'discount_amount' => (int) round($discountAmount, 0, PHP_ROUND_HALF_UP),
                'tax_rate' => $tax,
                'tax_amount' => (int) round($taxAmount, 0, PHP_ROUND_HALF_UP),
                'total' => $grandTotal,
                'grand_total' => $grandTotal,
                'paid_amount' => $paid,
                'cash_received' => $payment['cash_received'],
                'change_amount' => $payment['change'],
                'balance' => $payment['balance'],
                'payment_type' => $paymentType,
                'payment_status' => $paid >= $grandTotal ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending'),
                'sale_channel' => 'pos',
                'delivery_required' => $deliveryRequired,
                'delivery_address' => $deliveryRequired ? $deliveryAddress : null,
                'status' => 'Completed',
            ]);

            foreach ($preparedLines as $prepared) {
                $product = $prepared['product'];
                $line = $prepared['line'];
                $amounts = $prepared['amounts'];
                $before = (int) $product->stock_quantity;
                $orderItem = OrderItem::create([
                    'order_id' => $order->id, 'product_id' => $product->id, 'product_name_snapshot' => $product->name,
                    'quantity' => (int) $line['quantity'], 'unit' => $product->unit, 'unit_price' => $prepared['price'], 'standard_unit_price' => $prepared['defaultPrice'], 'is_price_overridden' => $prepared['isOverride'], 'price_override_reason' => $prepared['isOverride'] ? $prepared['overrideReason'] : null,
                    'purchase_cost_snapshot' => (int) ($product->latest_purchase_price ?: $product->purchase_cost ?: 0),
                    'line_subtotal' => (int) round($amounts['lineSubtotal']), 'discount_type' => $amounts['discountType'], 'discount_value' => $amounts['discountValue'], 'discount_rate' => $amounts['discountRate'],
                    'discount_amount' => (int) round($amounts['discountAmount']), 'tax_rate' => $amounts['taxRate'],
                    'tax_amount' => (int) round($amounts['taxAmount']), 'line_total' => (int) round($amounts['lineTotal']),
                    'price' => $prepared['price'], 'total' => (int) round($amounts['lineTotal']),
                ]);
                if ($prepared['isOverride']) {
                    $this->activity->record($businessId, 'POS', 'POS price overridden for '.$product->name, $orderItem->id, null, [
                        'sale' => $number, 'standard_price' => $prepared['defaultPrice'], 'override_price' => $prepared['price'], 'difference' => $prepared['price'] - $prepared['defaultPrice'], 'reason' => $prepared['overrideReason'],
                    ]);
                }
                $this->batches->allocateSale($product, $order, $orderItem, (float) $line['quantity'], $userId);
                $product->decrement('stock_quantity', (int) $line['quantity']);
                $after = $before - (int) $line['quantity'];
                $inventory = Inventory::firstOrCreate(['business_id' => $businessId, 'product_id' => $product->id], ['available_stock' => $before]);
                $inventory->increment('sold_stock', (int) $line['quantity']);
                $inventory->update(['available_stock' => $after, 'low_stock_alert' => $product->low_stock_alert_qty ?? 0]);
                StockMovement::create(['business_id' => $businessId, 'product_id' => $product->id, 'type' => 'sold', 'quantity' => (int) $line['quantity'], 'note' => 'POS sale '.$number, 'user_id' => $userId, 'created_by' => $userId]);
                InventoryMovement::create(['business_id' => $businessId, 'product_id' => $product->id, 'type' => 'SOLD', 'quantity' => (int) $line['quantity'], 'previous_stock' => $before, 'new_stock' => $after, 'note' => 'POS sale '.$number, 'created_by' => $userId, 'movement_date' => now()]);
            }

            foreach ($payment['lines'] as $line) {
                Payment::create([
                    'business_id' => $businessId,
                    'order_id' => $order->id,
                    'pos_register_id' => $line['method'] === 'Cash' ? $register->id : null,
                    'customer_id' => $customer?->id,
                    'method' => $line['method'],
                    'amount' => $line['amount'],
                    'transaction_reference' => $line['reference'],
                    'reference_number' => $this->numbers->next($businessId, 'payment'),
                    'payment_date' => now()->toDateString(),
                    'status' => $order->payment_status,
                ]);
            }
            if ($customer && $order->balance > 0) {
                $customer->increment('current_balance', $order->balance);
                KhataLedger::create(['business_id' => $businessId, 'customer_id' => $customer->id, 'order_id' => $order->id, 'entry_type' => 'sale', 'type' => 'credit', 'amount' => $order->balance, 'customer_debit' => 0, 'customer_credit' => $order->balance, 'business_debit' => $order->balance, 'business_credit' => 0, 'description' => 'POS sale '.$number, 'balance' => $customer->fresh()->current_balance, 'balance_after' => $customer->fresh()->current_balance, 'entry_date' => now()->toDateString()]);
            }

            $invoice = Invoice::create(['business_id' => $businessId, 'order_id' => $order->id, 'invoice_number' => $number, 'customer_id' => $customer?->id, 'invoice_date' => now()->toDateString(), 'subtotal' => $order->subtotal, 'discount_percentage' => $discount, 'discount_amount' => $order->discount_amount, 'grand_total' => $grandTotal, 'paid_amount' => $paid, 'balance' => $order->balance, 'payment_status' => $order->payment_status, 'status' => $order->payment_status === 'Paid' ? 'Paid' : 'Issued', 'issued_by' => $userId, 'issued_at' => now()]);
            foreach ($order->items as $item) {
                $invoice->items()->create(['product_id' => $item->product_id, 'product_name_snapshot' => $item->product_name_snapshot, 'quantity' => $item->quantity, 'unit' => $item->unit, 'unit_price' => $item->unit_price, 'line_total' => $item->line_total]);
            }

            if ($deliveryRequired) {
                Delivery::create([
                    'business_id' => $businessId,
                    'invoice_id' => $invoice->id,
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'address' => $deliveryAddress,
                    'amount' => $order->balance > 0 ? $order->balance : $order->grand_total,
                    'payment_status' => $order->payment_status,
                    'status' => 'Pending',
                    'created_by' => $userId,
                ]);
            }

            $this->postAccounting($order->fresh(['items']), $payment['lines'], $customer?->id);
            if ($resumedHeldSale) {
                $resumedHeldSale->update(['status' => 'Completed']);
                $this->activity->record($businessId, 'POS', 'Held sale completed', $resumedHeldSale->id, null, ['hold_number' => $resumedHeldSale->hold_number, 'order_id' => $order->id]);
            }
            $this->activity->record($businessId, 'POS', 'Completed POS sale '.$number, $order->id, null, [
                'grand_total' => $grandTotal,
                'paid_amount' => $paid,
                'payment_split' => collect($payment['lines'])->map(fn (array $line) => $line['method'].' Rs '.$line['amount'])->implode('; '),
                'delivery_required' => $deliveryRequired,
            ]);

            return $order->fresh(['customer', 'items.product', 'invoice', 'payments']);
        });
    }

    /** @return array{opening_cash:int,cash_sales:int,cash_refunds:int,cash_in:int,cash_out:int,expected_cash:int} */
    private function reconciliationFor(PosRegister $register, int $businessId): array
    {
        $cashSales = Payment::query()
            ->where('business_id', $businessId)
            ->where('method', 'Cash')
            ->where(function ($query) use ($register): void {
                $query->where('pos_register_id', $register->id)
                    // Retain a sensible result for an open register created
                    // before shift linkage was introduced. New POS sales are
                    // always linked directly to their register.
                    ->orWhere(function ($legacy) use ($register): void {
                        $legacy->whereNull('pos_register_id')->where('created_at', '>=', $register->opened_at);
                    });
            })
            ->sum('amount');
        $cashRefunds = SalesReturn::query()
            ->where('business_id', $businessId)
            ->where('refund_method', 'Cash')
            ->where(function ($query) use ($register): void {
                $query->where('pos_register_id', $register->id)
                    ->orWhere(function ($legacy) use ($register): void {
                        $legacy->whereNull('pos_register_id')->where('returned_at', '>=', $register->opened_at);
                    });
            })
            ->sum('refund_amount');
        $movementTotals = PosRegisterCashMovement::query()
            ->where('business_id', $businessId)
            ->where('pos_register_id', $register->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'Cash In' THEN amount ELSE 0 END), 0) as cash_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'Cash Out' THEN amount ELSE 0 END), 0) as cash_out")
            ->first();

        $opening = (int) round((float) $register->opening_cash, 0, PHP_ROUND_HALF_UP);
        $sales = (int) round((float) $cashSales, 0, PHP_ROUND_HALF_UP);
        $refunds = (int) round((float) $cashRefunds, 0, PHP_ROUND_HALF_UP);
        $cashIn = (int) round((float) ($movementTotals?->cash_in ?? 0), 0, PHP_ROUND_HALF_UP);
        $cashOut = (int) round((float) ($movementTotals?->cash_out ?? 0), 0, PHP_ROUND_HALF_UP);

        return [
            'opening_cash' => $opening,
            'cash_sales' => $sales,
            'cash_refunds' => $refunds,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $opening + $sales + $cashIn - $refunds - $cashOut,
        ];
    }

    private function resolveCustomer(int $businessId, mixed $customerId, ?array $quickCustomer = null, ?int $userId = null): ?Customer
    {
        if ($quickCustomer !== null) {
            $this->requirePermission('customers.create');
            $name = trim((string) ($quickCustomer['name'] ?? ''));
            $phone = trim((string) ($quickCustomer['phone'] ?? ''));
            if ($name === '' && $phone === '') {
                throw ValidationException::withMessages(['quick_customer' => 'Enter at least a customer name or phone number.']);
            }

            $existing = Customer::where('business_id', $businessId)
                ->where('status', 'Active')
                ->where(function ($query) use ($name, $phone) {
                    if ($phone !== '') {
                        $query->where('phone', $phone);
                    }
                    if ($name !== '') {
                        $query->{$phone !== '' ? 'orWhere' : 'where'}('name', $name);
                    }
                })
                ->first();
            if ($existing) {
                return $existing;
            }

            return Customer::create([
                'business_id' => $businessId,
                'created_by' => $userId,
                'name' => $name !== '' ? $name : 'Customer '.$phone,
                'phone' => $phone ?: null,
                'city' => trim((string) ($quickCustomer['city'] ?? '')) ?: null,
                'address' => trim((string) ($quickCustomer['address'] ?? '')) ?: null,
                'customer_type' => 'Retailer',
                'status' => 'Active',
                'credit_limit' => 0,
                'opening_balance' => 0,
                'current_balance' => 0,
            ]);
        }

        if (! $customerId) {
            return null;
        }

        // A crafted POS request must not turn the POS module into a customer
        // lookup bypass when the Super Admin has disabled Customers.
        $this->requirePermission('customers.view');

        return Customer::where('business_id', $businessId)->where('status', 'Active')->findOrFail($customerId);
    }

    private function roundCash(mixed $value): int
    {
        return (int) round((float) $value, 0, PHP_ROUND_HALF_UP);
    }

    private function requirePermission(string $permission): void
    {
        if (! $this->permissions->allowsUser(auth()->user(), $permission)) {
            throw ValidationException::withMessages(['permission' => 'You do not have permission for this POS action.']);
        }
    }

    /** @param array<int, array{method:string,amount:int,reference:?string}> $payments */
    private function postAccounting(Order $order, array $payments, ?int $customerId): void
    {
        $this->accounting->ensureDefaultAccounts($order->business_id);
        $cash = Account::where('business_id', $order->business_id)->where('name', 'Cash')->first();
        $bank = Account::where('business_id', $order->business_id)->where('name', 'Bank')->first();
        $receivable = Account::where('business_id', $order->business_id)->where('name', 'Accounts Receivable')->first();
        $sales = Account::where('business_id', $order->business_id)->where('name', 'Sales Revenue')->first();
        $cashPaid = collect($payments)->where('method', 'Cash')->sum('amount');
        $nonCashPaid = collect($payments)->where('method', '!=', 'Cash')->sum('amount');
        if (! $sales || (! $cash && $cashPaid > 0) || (! $bank && $nonCashPaid > 0) || (! $receivable && $order->balance > 0)) return;
        $lines = [];
        foreach ($payments as $payment) {
            $account = $payment['method'] === 'Cash' ? $cash : $bank;
            $lines[] = [
                'account_id' => $account->id,
                'customer_id' => $customerId,
                'debit' => $payment['amount'],
                'credit' => 0,
                'description' => $order->order_number.' · '.$payment['method'],
            ];
        }
        if ($order->balance > 0) $lines[] = ['account_id' => $receivable->id, 'customer_id' => $customerId, 'debit' => $order->balance, 'credit' => 0, 'description' => $order->order_number];
        $lines[] = ['account_id' => $sales->id, 'customer_id' => $customerId, 'debit' => 0, 'credit' => $order->grand_total, 'description' => $order->order_number];
        $this->accounting->post($order->business_id, ['voucher_number' => 'POS-JV-'.$order->id, 'entry_date' => now()->toDateString(), 'reference_type' => 'order', 'reference_id' => $order->id, 'description' => 'POS sale '.$order->order_number], $lines);
    }
}
