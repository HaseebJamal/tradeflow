<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Delivery;
use App\Models\JournalEntry;
use App\Models\KhataLedger;
use App\Models\Payment;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\BusinessActivityService;
use App\Services\FinanceCalculator;
use App\Services\CompanyPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    public function __construct(private FinanceCalculator $finance, private AccountingService $accounting, private BusinessActivityService $activity, private CompanyPermissionService $permissions) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'delivery_id' => ['nullable', 'integer'],
            'order_number' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer'],
            'delivery_staff_id' => ['nullable', 'integer'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'date_type' => ['nullable', 'in:created_at,assigned_at,started_at,delivered_at,failed_at'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'amount_from' => ['nullable', 'integer', 'min:0'],
            'amount_to' => ['nullable', 'integer', 'min:0', 'gte:amount_from'],
            'clear' => ['nullable', 'boolean'],
        ]);
        $dateColumn = $filters['date_type'] ?? 'created_at';
        $dateFrom = $request->boolean('clear') ? null : ($filters['date_from'] ?? now()->startOfMonth()->toDateString());
        $dateTo = $request->boolean('clear') ? null : ($filters['date_to'] ?? now()->toDateString());
        $hasDateRange = $dateFrom || $dateTo;

        $query = $this->deliveryQuery()->with(['order.customer', 'order.payments', 'staff']);
        $query
            ->when($filters['delivery_id'] ?? null, fn ($q, $value) => $q->where('id', $value))
            ->when($filters['order_number'] ?? null, fn ($q, $value) => $q->whereHas('order', fn ($order) => $order->where('order_number', 'like', "%{$value}%")))
            ->when($filters['customer_id'] ?? null, fn ($q, $value) => $q->where('customer_id', $value))
            ->when($filters['delivery_staff_id'] ?? null, fn ($q, $value) => $q->where('delivery_staff_id', $value))
            ->when($filters['payment_status'] ?? null, fn ($q, $value) => $q->whereHas('order', fn ($order) => $order->where('payment_status', $value)))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($dateFrom, fn ($q, $value) => $q->whereDate($dateColumn, '>=', $value))
            ->when($dateTo, fn ($q, $value) => $q->whereDate($dateColumn, '<=', $value))
            ->when(!$hasDateRange && ($filters['month'] ?? null), fn ($q, $value) => $q->whereMonth($dateColumn, $value))
            ->when(!$hasDateRange && ($filters['year'] ?? null), fn ($q, $value) => $q->whereYear($dateColumn, $value))
            ->when($filters['amount_from'] ?? null, fn ($q, $value) => $q->where('amount', '>=', $value))
            ->when($filters['amount_to'] ?? null, fn ($q, $value) => $q->where('amount', '<=', $value));
        $deliveries = $query->latest()->paginate(20);
        $deliveries->getCollection()->transform(function (Delivery $delivery) {
            if ($delivery->order) {
                $delivery->setRelation('order', $this->finance->syncOrderPaymentSummary($delivery->order));
            }

            return $delivery;
        });
        $statsQuery = $this->deliveryQuery();
        $cashToCollect = $this->deliveryQuery()
            ->whereIn('status', ['Pending', 'Assigned', 'Out For Delivery'])
            ->with('order')
            ->get()
            ->sum(fn (Delivery $delivery) => $delivery->order ? (float) $delivery->order->balance : (float) $delivery->amount);

        return view('business.deliveries.index', [
            'deliveries' => $deliveries,
            'stats' => [
                'today' => (clone $statsQuery)->whereDate('created_at', today())->count(),
                'pending' => (clone $statsQuery)->where('status', 'Pending')->count(),
                'out' => (clone $statsQuery)->where('status', 'Out For Delivery')->count(),
                'delivered' => (clone $statsQuery)->where('status', 'Delivered')->count(),
                'failed' => (clone $statsQuery)->where('status', 'Failed')->count(),
                'cash_to_collect' => $cashToCollect,
            ],
            'staff' => $this->deliveryStaffQuery()->get(),
            'customers' => \App\Models\Customer::where('business_id', auth()->user()->business_id)->orderBy('name')->get(),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'dateType' => $dateColumn,
        ]);
    }

    public function show(Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery)->load(['order.items.product', 'order.payments', 'order.customer', 'staff', 'customer']);
        if ($delivery->order) {
            $delivery->setRelation('order', $this->finance->syncOrderPaymentSummary($delivery->order));
        }

        return view('business.deliveries.show', [
            'delivery' => $delivery,
            'paidAmount' => $delivery->order?->paid_amount ?? 0,
            'deliveryStaff' => $this->deliveryStaffQuery()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery);
        $data = $request->validate([
            'delivery_staff_id' => ['nullable', 'exists:users,id'],
            'address' => ['nullable'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:Pending,Assigned,Picked Up,Out for Delivery,Out For Delivery,Delivered,Failed,Returned,Cancelled'],
            'proof_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'note' => ['nullable'],
        ]);

        if (!empty($data['delivery_staff_id'])) {
            if (!$this->canManageAllDeliveries(auth()->user()) && (int) $data['delivery_staff_id'] !== (int) $delivery->delivery_staff_id) {
                return back()->withErrors(['delivery_staff_id' => 'You can only update deliveries assigned to you.']);
            }
            $staff = $this->deliveryStaffQuery()->findOrFail($data['delivery_staff_id']);
            $data['delivery_staff_id'] = $staff->id;
        }
        if (($data['status'] ?? null) === 'Out for Delivery') {
            $data['status'] = 'Out For Delivery';
        }
        if (!$this->canTransition($delivery->status, $data['status'])) {
            return back()->withErrors(['status' => 'This delivery cannot move from '.$delivery->status.' to '.$data['status'].'.']);
        }
        if (($data['status'] ?? null) === 'Delivered' && (!$delivery->proof_image || !$delivery->receiver_name) && !$request->hasFile('proof_image')) {
            return back()->withErrors(['proof_image' => 'Delivery proof and receiver name are required before marking delivered.']);
        }
        if ($request->hasFile('proof_image')) {
            $data['proof_image'] = $request->file('proof_image')->store('delivery_proofs', 'public');
        }

        if ($delivery->status === 'Delivered') {
            return back()->withErrors(['status' => 'Delivered delivery cannot be directly edited.']);
        }
        if (($data['status'] ?? null) === 'Assigned' && is_null($delivery->assigned_at)) $data['assigned_at'] = now();
        if (!empty($data['delivery_staff_id']) && (int) $data['delivery_staff_id'] !== (int) $delivery->delivery_staff_id && is_null($delivery->assigned_at)) $data['assigned_at'] = now();
        if (in_array($data['status'] ?? null, ['Picked Up', 'Out For Delivery'], true) && is_null($delivery->started_at)) $data['started_at'] = now();
        if (($data['status'] ?? null) === 'Delivered' && is_null($delivery->delivered_at)) $data['delivered_at'] = now();
        if (($data['status'] ?? null) === 'Failed' && is_null($delivery->failed_at)) $data['failed_at'] = now();
        if (($data['status'] ?? null) === 'Returned' && is_null($delivery->returned_at)) $data['returned_at'] = now();
        if (($data['status'] ?? null) === 'Cancelled' && is_null($delivery->cancelled_at)) $data['cancelled_at'] = now();
        $delivery->update($data);
        if ($delivery->status === 'Out For Delivery') {
            $delivery->order?->update(['status' => 'Out For Delivery']);
        } elseif ($delivery->status === 'Delivered') {
            $delivery->order?->update(['status' => 'Delivered']);
        } elseif (in_array($delivery->status, ['Failed', 'Returned'], true)) {
            $delivery->order?->update(['status' => $delivery->status]);
        }
        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery updated to '.$delivery->status, $delivery->id, null, ['order_id' => $delivery->order_id, 'status' => $delivery->status]);

        return back()->with('success', 'Delivery updated.');
    }

    public function start(Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery);
        if (!in_array($delivery->status, ['Pending', 'Assigned'], true)) {
            return back()->withErrors(['status' => 'Only assigned deliveries can be picked up.']);
        }
        $delivery->update(['status' => 'Picked Up', 'started_at' => $delivery->started_at ?? now()]);
        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery picked up', $delivery->id, null, ['order_id' => $delivery->order_id]);

        return back()->with('success', 'Delivery marked as picked up.');
    }

    public function deliver(Request $request, Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery);
        abort_unless($delivery->status === 'Out For Delivery', 403);
        $data = $request->validate([
            'proof_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'signature_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'receiver_name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'receiver_phone' => ['nullable', 'regex:/^\d{11}$/'],
            'note' => ['nullable', 'string'],
            'collected_amount' => ['nullable', 'integer', 'min:0'],
            'payment_method' => ['nullable', 'in:Cash,Bank Transfer Manual,JazzCash Manual,Easypaisa Manual,Cheque'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_proof_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        DB::transaction(function () use ($request, $delivery, $data) {
            $proofPath = $request->file('proof_image')->store('delivery_proofs', 'public');
            $signaturePath = $request->hasFile('signature_image') ? $request->file('signature_image')->store('delivery_proofs', 'public') : null;
            $paymentProofPath = $request->hasFile('payment_proof_image') ? $request->file('payment_proof_image')->store('delivery_proofs', 'public') : null;
            if ($delivery->order) {
                $delivery->setRelation('order', $this->finance->syncOrderPaymentSummary($delivery->order));
            }
            $collected = (float) ($data['collected_amount'] ?? 0);

            if ($collected > 0) {
                $remaining = $delivery->order
                    ? $this->finance->calculateBalance((float) ($delivery->order->grand_total ?: $delivery->order->total), $this->finance->calculatePaidAmount($delivery->order))
                    : (float) $delivery->amount;

                if ($collected > $remaining) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'collected_amount' => 'Collected amount cannot exceed remaining balance of Rs '.number_format($remaining).'.',
                    ]);
                }

                $method = $data['payment_method'] ?? 'Cash';
                $payment = Payment::create([
                    'business_id' => $delivery->business_id,
                    'order_id' => $delivery->order_id,
                    'customer_id' => $delivery->customer_id,
                    'method' => $method,
                    'amount' => $collected,
                    'payment_date' => now()->toDateString(),
                    'reference_number' => $data['payment_reference'] ?? null,
                    'transaction_reference' => $data['payment_reference'] ?? null,
                    'proof_image' => $paymentProofPath,
                    'status' => 'Paid',
                ]);

                if ($delivery->customer) {
                    $balance = max(0, $delivery->customer->current_balance - $collected);
                    $delivery->customer->update(['current_balance' => $balance]);
                    KhataLedger::create([
                        'business_id' => $delivery->business_id,
                        'customer_id' => $delivery->customer_id,
                        'order_id' => $delivery->order_id,
                        'payment_id' => $payment->id,
                        'entry_type' => 'payment',
                        'type' => 'debit',
                        'amount' => $collected,
                        'customer_debit' => $collected,
                        'customer_credit' => 0,
                        'business_debit' => $collected,
                        'business_credit' => 0,
                        'payment_method' => $method,
                        'description' => 'Payment received via '.$method.' on delivery',
                        'balance' => $balance,
                        'balance_after' => $balance,
                        'entry_date' => now()->toDateString(),
                    ]);
                }

                if ($delivery->order) {
                    $synced = $this->finance->syncOrderPaymentSummary($delivery->order);
                    $payment->update(['status' => $synced->payment_status]);
                    $this->postPaymentAccounting($payment, $delivery);
                }
            }

            $delivery->update([
                'status' => 'Delivered',
                'proof_image' => $proofPath,
                'signature_image' => $signaturePath,
                'receiver_name' => $data['receiver_name'],
                'receiver_phone' => $data['receiver_phone'] ?? null,
                'note' => $data['note'] ?? null,
                'collected_amount' => $collected ?: null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_proof_image' => $paymentProofPath,
                'payment_proof' => $paymentProofPath,
                'received_amount' => $collected ?: null,
                'received_by' => $collected > 0 ? auth()->id() : null,
                'received_at' => $collected > 0 ? now() : null,
                'payment_status' => $collected > 0 ? 'Partial' : null,
                'delivered_at' => $delivery->delivered_at ?? now(),
            ]);
            if ($delivery->order) {
                $delivery->order->update(['status' => 'Delivered']);
                $syncedOrder = $this->finance->syncOrderPaymentSummary($delivery->order->fresh());
                $delivery->update(['payment_status' => $syncedOrder->payment_status]);
            }
        });
        $delivery->refresh();
        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery completed', $delivery->id, null, ['order_id' => $delivery->order_id, 'collected_amount' => $delivery->collected_amount]);

        return redirect()->route('business.deliveries.show', $delivery)->with('success', 'Delivery marked delivered.');
    }

    public function fail(Request $request, Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery);
        abort_unless($delivery->status === 'Out For Delivery', 403);
        $data = $request->validate(['failure_reason' => ['required', 'string'], 'note' => ['nullable', 'string']]);
        $delivery->update(['status' => 'Failed', 'failed_at' => $delivery->failed_at ?? now(), 'failure_reason' => $data['failure_reason'], 'note' => $data['note'] ?? $delivery->note]);
        if (in_array($delivery->order?->status, ['Out For Delivery', 'Failed'], true)) {
            $delivery->order?->update(['status' => 'Failed']);
        }
        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery marked failed', $delivery->id, null, ['order_id' => $delivery->order_id, 'reason' => $data['failure_reason']]);

        return back()->with('success', 'Delivery marked failed.');
    }

    public function reopen(Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery);
        abort_unless($delivery->status === 'Failed', 403);
        $delivery->update(['status' => 'Assigned', 'assigned_at' => $delivery->assigned_at ?? now(), 'failed_at' => null]);
        $this->activity->record($delivery->business_id, 'Deliveries', 'Failed delivery reopened', $delivery->id, null, ['order_id' => $delivery->order_id]);

        return back()->with('success', 'Failed delivery reopened.');
    }

    public function cancel(Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery);
        abort_unless($delivery->status !== 'Delivered', 403);
        $delivery->update(['status' => 'Cancelled', 'cancelled_at' => $delivery->cancelled_at ?? now()]);
        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery cancelled', $delivery->id, null, ['order_id' => $delivery->order_id]);

        return back()->with('success', 'Delivery cancelled.');
    }

    public function sheet(Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery)->load(['order.items.product', 'customer', 'staff']);
        return view('business.deliveries.sheet', compact('delivery'));
    }

    private function deliveryQuery()
    {
        $user = auth()->user();
        $query = Delivery::query()->where('business_id', $user->business_id);

        if (!$this->canManageAllDeliveries($user)) {
            $query->where('delivery_staff_id', $user->id);
        }

        return $query;
    }

    private function scopedDelivery(Delivery $delivery): Delivery
    {
        $user = auth()->user();
        abort_unless($delivery->business_id === $user->business_id, 403);
        if (!$this->canManageAllDeliveries($user)) {
            abort_unless($delivery->delivery_staff_id === $user->id, 403);
        }

        return $delivery;
    }

    private function canManageAllDeliveries(User $user): bool
    {
        return $this->permissions->allowsUser($user, 'deliveries.assign')
            || $this->permissions->allowsUser($user, 'deliveries.edit');
    }

    private function deliveryStaffQuery()
    {
        return User::query()
            ->where('business_id', auth()->user()->business_id)
            ->where('role', 'custom_staff')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereJsonContains('permissions', 'deliveries.view')
                    ->orWhereJsonContains('permissions', 'deliveries.update_status')
                    ->orWhereJsonContains('permissions', 'deliveries.upload_proof');
            });
    }

    private function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, match ($from) {
            'Pending' => ['Assigned', 'Picked Up', 'Cancelled'],
            'Assigned' => ['Picked Up', 'Cancelled'],
            'Picked Up' => ['Out For Delivery', 'Failed', 'Returned'],
            'Out For Delivery' => ['Delivered', 'Failed', 'Returned'],
            'Failed' => ['Assigned', 'Returned', 'Cancelled'],
            default => [],
        }, true);
    }

    private function postPaymentAccounting(Payment $payment, Delivery $delivery): void
    {
        if (JournalEntry::where('business_id', $payment->business_id)->where('reference_type', 'payment')->where('reference_id', $payment->id)->exists()) {
            return;
        }
        $this->accounting->ensureDefaultAccounts($payment->business_id);
        $cashAccount = Account::where('business_id', $payment->business_id)->where('name', str_contains((string) $payment->method, 'Bank') ? 'Bank' : 'Cash')->first();
        $arAccount = Account::where('business_id', $payment->business_id)->where('name', 'Accounts Receivable')->first();
        if (!$cashAccount || !$arAccount) return;
        $this->accounting->post($payment->business_id, [
            'voucher_number' => 'PAY-JV-'.$payment->id.'-'.now()->format('His'),
            'entry_date' => now()->toDateString(),
            'reference_type' => 'payment',
            'reference_id' => $payment->id,
            'description' => 'Payment received on delivery #'.$delivery->id,
        ], [
            ['account_id' => $cashAccount->id, 'customer_id' => $payment->customer_id, 'debit' => $payment->amount, 'credit' => 0, 'description' => $payment->method],
            ['account_id' => $arAccount->id, 'customer_id' => $payment->customer_id, 'debit' => 0, 'credit' => $payment->amount, 'description' => $payment->method],
        ]);
    }
}
