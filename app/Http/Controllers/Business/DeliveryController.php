<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\KhataLedger;
use App\Models\Payment;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\BusinessActivityService;
use App\Services\FinanceCalculator;
use App\Services\CompanyPermissionService;
use App\Services\PosDeliveryAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    public function __construct(private FinanceCalculator $finance, private AccountingService $accounting, private BusinessActivityService $activity, private CompanyPermissionService $permissions, private PosDeliveryAssignmentService $posDeliveryAssignments) {}

    public function assignFromPosInvoice(Request $request, Invoice $invoice)
    {
        if ((int) $invoice->business_id !== (int) $request->user()->business_id) {
            abort(403);
        }

        $data = $request->validate([
            'delivery_staff_id' => ['required', 'integer'],
            'address' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->posDeliveryAssignments->assign($request->user(), $invoice->id, $data);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        return redirect()->route('business.deliveries')->with('success', 'Delivery assigned successfully.');
    }

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

        $query = $this->deliveryQuery()->with(['invoice.order.customer', 'invoice.order.payments', 'order.customer', 'order.payments', 'staff']);
        $query
            ->when($filters['delivery_id'] ?? null, fn ($q, $value) => $q->where('id', $value))
            ->when($filters['order_number'] ?? null, fn ($q, $value) => $q->where(function ($delivery) use ($value) {
                $delivery->whereHas('invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', "%{$value}%"))
                    ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', "%{$value}%"));
            }))
            ->when($filters['customer_id'] ?? null, fn ($q, $value) => $q->where('customer_id', $value))
            ->when($filters['delivery_staff_id'] ?? null, fn ($q, $value) => $q->where('delivery_staff_id', $value))
            ->when($filters['payment_status'] ?? null, fn ($q, $value) => $q->where('payment_status', $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($dateFrom, fn ($q, $value) => $q->whereDate($dateColumn, '>=', $value))
            ->when($dateTo, fn ($q, $value) => $q->whereDate($dateColumn, '<=', $value))
            ->when(!$hasDateRange && ($filters['month'] ?? null), fn ($q, $value) => $q->whereMonth($dateColumn, $value))
            ->when(!$hasDateRange && ($filters['year'] ?? null), fn ($q, $value) => $q->whereYear($dateColumn, $value))
            ->when($filters['amount_from'] ?? null, fn ($q, $value) => $q->where('amount', '>=', $value))
            ->when($filters['amount_to'] ?? null, fn ($q, $value) => $q->where('amount', '<=', $value));
        $deliveries = $query->latest()->paginate(12);
        $deliveries->getCollection()->transform(function (Delivery $delivery) {
            if ($order = $delivery->sourceOrder()) {
                $synced = $this->finance->syncOrderPaymentSummary($order);
                if ($delivery->invoice) $delivery->invoice->setRelation('order', $synced);
                else $delivery->setRelation('order', $synced);
            }

            return $delivery;
        });
        $statsQuery = $this->deliveryQuery();
        $cashToCollect = $this->deliveryQuery()
            ->whereIn('status', ['Pending', 'Assigned', 'Out For Delivery'])
            ->with(['invoice.order', 'order'])
            ->get()
            ->sum(fn (Delivery $delivery) => $delivery->sourceOrder() ? (float) $delivery->sourceOrder()->balance : (float) $delivery->amount);

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
            'staff' => $this->posDeliveryAssignments->eligibleStaff($request->user()),
            'customers' => \App\Models\Customer::where('business_id', auth()->user()->business_id)->orderBy('name')->get(),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'dateType' => $dateColumn,
        ]);
    }

    public function show(Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery)->load(['invoice.order.items.product', 'invoice.order.payments', 'invoice.order.customer', 'order.items.product', 'order.payments', 'order.customer', 'staff', 'customer']);
        if ($order = $delivery->sourceOrder()) {
            $synced = $this->finance->syncOrderPaymentSummary($order);
            if ($delivery->invoice) $delivery->invoice->setRelation('order', $synced);
            else $delivery->setRelation('order', $synced);
        }

        return view('business.deliveries.show', [
            'delivery' => $delivery,
            'paidAmount' => $delivery->sourceOrder()?->paid_amount ?? 0,
            'deliveryStaff' => $this->posDeliveryAssignments->eligibleStaff(auth()->user()),
        ]);
    }

    public function update(Request $request, Delivery $delivery)
    {
        $this->requirePermission('deliveries.edit');
        $delivery = $this->scopedDelivery($delivery);
        $data = $request->validate([
            'delivery_staff_id' => ['nullable', 'exists:users,id'],
            'address' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (!empty($data['delivery_staff_id'])) {
            if ((int) $data['delivery_staff_id'] !== (int) $delivery->delivery_staff_id) {
                $this->requirePermission('deliveries.assign');
            }
            $staff = $this->posDeliveryAssignments->eligibleStaff(auth()->user())
                ->firstWhere('id', (int) $data['delivery_staff_id']);
            if (! $staff) {
                return back()->withErrors(['delivery_staff_id' => 'Select an active delivery staff member from this business.']);
            }
            $data['delivery_staff_id'] = $staff->id;
        }
        if (!empty($data['delivery_staff_id']) && (int) $data['delivery_staff_id'] !== (int) $delivery->delivery_staff_id && is_null($delivery->assigned_at)) $data['assigned_at'] = now();
        $delivery->update($data);
        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery details updated', $delivery->id, null, ['invoice_id' => $delivery->invoice_id]);

        return back()->with('success', 'Delivery details updated.');
    }

    public function start(Delivery $delivery)
    {
        $this->requirePermission('deliveries.update_status');
        $delivery = $this->scopedDelivery($delivery);
        if (!in_array($delivery->status, ['Pending', 'Assigned', 'Picked Up'], true)) {
            return back()->withErrors(['status' => 'Only assigned deliveries can be started.']);
        }
        $delivery->update(['status' => 'Out For Delivery', 'started_at' => $delivery->started_at ?? now()]);
        $delivery->sourceOrder()?->update(['status' => 'Out For Delivery']);
        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery started', $delivery->id, null, ['invoice_id' => $delivery->invoice_id]);

        return back()->with('success', 'Delivery marked as out for delivery.');
    }

    public function uploadProof(Request $request, Delivery $delivery)
    {
        $this->requirePermission('deliveries.upload_proof');
        $delivery = $this->scopedDelivery($delivery);
        $data = $request->validate([
            'proof_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'signature_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'receiver_name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'receiver_phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'note' => ['nullable', 'string'],
        ]);

        if (!in_array($delivery->status, ['Assigned', 'Out For Delivery'], true)) {
            return back()->withErrors(['proof_image' => 'Proof can only be uploaded for an active delivery.']);
        }

        $proofPath = $request->file('proof_image')->store('delivery_proofs', 'public');
        $signaturePath = $request->hasFile('signature_image') ? $request->file('signature_image')->store('delivery_proofs', 'public') : null;
        $delivery->update([
            'proof_image' => $proofPath,
            'signature_image' => $signaturePath,
            'receiver_name' => $data['receiver_name'],
            'receiver_phone' => $data['receiver_phone'] ?? null,
            'note' => $data['note'] ?? $delivery->note,
        ]);
        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery proof uploaded', $delivery->id, null, ['invoice_id' => $delivery->invoice_id]);

        return back()->with('success', 'Delivery proof uploaded successfully.');
    }

    public function deliver(Delivery $delivery)
    {
        $this->requirePermission('deliveries.update_status');
        $this->requirePermission('deliveries.upload_proof');
        $delivery = $this->scopedDelivery($delivery);
        if ($delivery->status !== 'Out For Delivery') {
            return back()->withErrors(['status' => 'Only out-for-delivery records can be marked delivered.']);
        }
        if (!$delivery->proof_image || !$delivery->receiver_name) {
            return back()->withErrors(['proof_image' => 'Upload delivery proof and receiver details before marking delivered.']);
        }

        DB::transaction(function () use ($delivery) {
            $locked = Delivery::where('business_id', $delivery->business_id)->lockForUpdate()->findOrFail($delivery->id);
            if ($locked->status !== 'Out For Delivery') {
                throw \Illuminate\Validation\ValidationException::withMessages(['status' => 'This delivery has already been updated.']);
            }
            $locked->update(['status' => 'Delivered', 'delivered_at' => $locked->delivered_at ?? now()]);
            $locked->load(['invoice.order', 'order']);
            $locked->sourceOrder()?->update(['status' => 'Delivered']);
        });
        $delivery->refresh();
        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery completed', $delivery->id, null, ['invoice_id' => $delivery->invoice_id]);

        return redirect()->route('business.deliveries.show', $delivery)->with('success', 'Delivery marked delivered.');
    }

    public function fail(Request $request, Delivery $delivery)
    {
        $this->requirePermission('deliveries.update_status');
        $delivery = $this->scopedDelivery($delivery);
        if ($delivery->status !== 'Out For Delivery') {
            return back()->withErrors(['status' => 'Only out-for-delivery records can be marked failed.']);
        }
        $data = $request->validate(['failure_reason' => ['required', 'string'], 'note' => ['nullable', 'string']]);
        $delivery->update(['status' => 'Failed', 'failed_at' => $delivery->failed_at ?? now(), 'failure_reason' => $data['failure_reason'], 'note' => $data['note'] ?? $delivery->note]);
        if (in_array($delivery->sourceOrder()?->status, ['Out For Delivery', 'Failed'], true)) {
            $delivery->sourceOrder()?->update(['status' => 'Failed']);
        }
        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery marked failed', $delivery->id, null, ['invoice_id' => $delivery->invoice_id, 'reason' => $data['failure_reason']]);

        return back()->with('success', 'Delivery marked failed.');
    }

    public function reopen(Delivery $delivery)
    {
        $this->requirePermission('deliveries.edit');
        $delivery = $this->scopedDelivery($delivery);
        abort_unless($delivery->status === 'Failed', 403);
        $delivery->update(['status' => 'Assigned', 'assigned_at' => $delivery->assigned_at ?? now(), 'failed_at' => null]);
        $this->activity->record($delivery->business_id, 'Deliveries', 'Failed delivery reopened', $delivery->id, null, ['invoice_id' => $delivery->invoice_id]);

        return back()->with('success', 'Failed delivery reopened.');
    }

    public function cancel(Delivery $delivery)
    {
        $this->requirePermission('deliveries.edit');
        $delivery = $this->scopedDelivery($delivery);
        abort_unless($delivery->status !== 'Delivered', 403);
        $delivery->update(['status' => 'Cancelled', 'cancelled_at' => $delivery->cancelled_at ?? now()]);
        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery cancelled', $delivery->id, null, ['invoice_id' => $delivery->invoice_id]);

        return back()->with('success', 'Delivery cancelled.');
    }

    public function recordCollection(Request $request, Delivery $delivery)
    {
        $this->requirePermission('deliveries.record_collection');
        $delivery = $this->scopedDelivery($delivery);
        $data = $request->validate([
            'collected_amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'in:Cash,Bank Transfer Manual,JazzCash Manual,Easypaisa Manual,Cheque'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_proof_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if (in_array($delivery->status, ['Cancelled', 'Failed', 'Returned'], true)) {
            return back()->withErrors(['collection' => 'Collection cannot be recorded for this delivery status.']);
        }

        $paymentProofPath = $request->hasFile('payment_proof_image')
            ? $request->file('payment_proof_image')->store('delivery_proofs', 'public')
            : null;

        try {
            DB::transaction(function () use ($delivery, $data, $paymentProofPath) {
                $locked = Delivery::query()
                    ->where('business_id', $delivery->business_id)
                    ->with(['invoice.order', 'order', 'customer'])
                    ->lockForUpdate()
                    ->findOrFail($delivery->id);

                if (in_array($locked->status, ['Cancelled', 'Failed', 'Returned'], true)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['collection' => 'Collection cannot be recorded for this delivery status.']);
                }

                $sourceOrder = $locked->sourceOrder();
                if ($sourceOrder) {
                    $sourceOrder = $this->finance->syncOrderPaymentSummary($sourceOrder);
                }
                $remaining = $sourceOrder
                    ? $this->finance->calculateBalance((float) ($sourceOrder->grand_total ?: $sourceOrder->total), $this->finance->calculatePaidAmount($sourceOrder))
                    : max(0, (float) $locked->amount - (float) ($locked->received_amount ?? 0));
                $collected = (int) $data['collected_amount'];

                if ($remaining <= 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['collection' => 'This delivery is already paid.']);
                }
                if ($collected > $remaining) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'collected_amount' => 'Collected amount cannot exceed remaining balance of Rs '.number_format($remaining).'.',
                    ]);
                }

                $payment = Payment::create([
                    'business_id' => $locked->business_id,
                    'order_id' => $sourceOrder?->id,
                    'customer_id' => $locked->customer_id,
                    'method' => $data['payment_method'],
                    'amount' => $collected,
                    'payment_date' => now()->toDateString(),
                    'reference_number' => $data['payment_reference'] ?? null,
                    'transaction_reference' => $data['payment_reference'] ?? null,
                    'proof_image' => $paymentProofPath,
                    'status' => 'Paid',
                ]);

                if ($locked->customer) {
                    $balance = max(0, (float) $locked->customer->current_balance - $collected);
                    $locked->customer->update(['current_balance' => $balance]);
                    KhataLedger::create([
                        'business_id' => $locked->business_id,
                        'customer_id' => $locked->customer_id,
                        'order_id' => $sourceOrder?->id,
                        'payment_id' => $payment->id,
                        'entry_type' => 'payment',
                        'type' => 'debit',
                        'amount' => $collected,
                        'customer_debit' => $collected,
                        'customer_credit' => 0,
                        'business_debit' => $collected,
                        'business_credit' => 0,
                        'payment_method' => $data['payment_method'],
                        'description' => 'Payment received via '.$data['payment_method'].' on delivery',
                        'balance' => $balance,
                        'balance_after' => $balance,
                        'entry_date' => now()->toDateString(),
                    ]);
                }

                $paymentStatus = 'Partial';
                if ($sourceOrder) {
                    $synced = $this->finance->syncOrderPaymentSummary($sourceOrder);
                    $paymentStatus = $synced->payment_status;
                    $payment->update(['status' => $paymentStatus]);
                    $this->postPaymentAccounting($payment, $locked);
                }

                $locked->update([
                    'collected_amount' => (float) ($locked->collected_amount ?? 0) + $collected,
                    'received_amount' => (float) ($locked->received_amount ?? 0) + $collected,
                    'payment_method' => $data['payment_method'],
                    'payment_reference' => $data['payment_reference'] ?? $locked->payment_reference,
                    'payment_proof_image' => $paymentProofPath ?? $locked->payment_proof_image,
                    'payment_proof' => $paymentProofPath ?? $locked->payment_proof,
                    'payment_status' => $paymentStatus,
                    'received_by' => auth()->id(),
                    'received_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            if ($paymentProofPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($paymentProofPath);
            }
            throw $exception;
        }

        $this->activity->record($delivery->business_id, 'Deliveries', 'Delivery collection recorded', $delivery->id, null, ['invoice_id' => $delivery->invoice_id, 'amount' => $data['collected_amount']]);

        return back()->with('success', 'Collection recorded successfully.');
    }

    public function sheet(Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery)->load(['business.documentFooter', 'business.owner:id,email', 'invoice.order.items.product', 'order.items.product', 'customer', 'staff']);
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

    private function requirePermission(string $permission): void
    {
        abort_unless($this->permissions->allowsUser(auth()->user(), 'deliveries.view')
            && $this->permissions->allowsUser(auth()->user(), $permission), 403);
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
