<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\KhataLedger;
use App\Models\Payment;
use App\Models\User;
use App\Services\FinanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    public function __construct(private FinanceCalculator $finance) {}

    public function index()
    {
        $query = $this->deliveryQuery()->with(['order.customer', 'order.payments', 'staff']);
        $deliveries = $query->latest()->paginate(20);
        $deliveries->getCollection()->transform(function (Delivery $delivery) {
            if ($delivery->order) {
                $delivery->setRelation('order', $this->finance->syncOrderPaymentSummary($delivery->order));
            }

            return $delivery;
        });
        $statsQuery = $this->deliveryQuery();
        $cashToCollect = $this->deliveryQuery()
            ->whereIn('status', ['Pending', 'Out For Delivery'])
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
            'staff' => User::where('business_id', auth()->user()->business_id)->where('role', 'delivery_staff')->where('status', 'active')->get(),
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
        ]);
    }

    public function update(Request $request, Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery);
        $data = $request->validate([
            'delivery_staff_id' => ['nullable', 'exists:users,id'],
            'address' => ['nullable'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:Pending,Out for Delivery,Out For Delivery,Delivered,Failed'],
            'proof_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'note' => ['nullable'],
        ]);

        if (!empty($data['delivery_staff_id'])) {
            $staff = User::where('business_id', auth()->user()->business_id)->where('role', 'delivery_staff')->where('status', 'active')->findOrFail($data['delivery_staff_id']);
            $data['delivery_staff_id'] = $staff->id;
        }
        if (($data['status'] ?? null) === 'Out for Delivery') {
            $data['status'] = 'Out For Delivery';
        }
        if (($data['status'] ?? null) === 'Delivered' && (!$delivery->proof_image || !$delivery->receiver_name) && !$request->hasFile('proof_image')) {
            return back()->withErrors(['proof_image' => 'Delivery proof and receiver name are required before marking delivered.']);
        }
        if ($request->hasFile('proof_image')) {
            $data['proof_image'] = $request->file('proof_image')->store('delivery_proofs', 'public');
        }

        $delivery->update($data);
        if ($delivery->status === 'Delivered') {
            $delivery->order?->update(['status' => 'Delivered']);
        }

        return back()->with('success', 'Delivery updated.');
    }

    public function start(Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery);
        abort_unless($delivery->status === 'Pending', 403);
        $delivery->update(['status' => 'Out For Delivery', 'started_at' => now()]);
        $delivery->order?->update(['status' => 'Out For Delivery']);

        return back()->with('success', 'Delivery started.');
    }

    public function deliver(Request $request, Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery);
        abort_unless($delivery->status === 'Out For Delivery', 403);
        $data = $request->validate([
            'proof_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'signature_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string'],
            'collected_amount' => ['nullable', 'numeric', 'min:0'],
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
                'delivered_at' => now(),
            ]);
            if ($delivery->order) {
                $delivery->order->update(['status' => 'Delivered']);
                $this->finance->syncOrderPaymentSummary($delivery->order->fresh());
            }
        });

        return redirect()->route('business.deliveries.show', $delivery)->with('success', 'Delivery marked delivered.');
    }

    public function fail(Request $request, Delivery $delivery)
    {
        $delivery = $this->scopedDelivery($delivery);
        abort_unless($delivery->status === 'Out For Delivery', 403);
        $data = $request->validate(['failure_reason' => ['required', 'string'], 'note' => ['nullable', 'string']]);
        $delivery->update(['status' => 'Failed', 'failure_reason' => $data['failure_reason'], 'note' => $data['note'] ?? $delivery->note]);
        if (in_array($delivery->order?->status, ['Out For Delivery', 'Failed'], true)) {
            $delivery->order?->update(['status' => 'Failed']);
        }

        return back()->with('success', 'Delivery marked failed.');
    }

    private function deliveryQuery()
    {
        $user = auth()->user();
        $query = Delivery::query()->where('business_id', $user->business_id);

        if ($user->role === 'delivery_staff') {
            $query->where('delivery_staff_id', $user->id);
        }

        return $query;
    }

    private function scopedDelivery(Delivery $delivery): Delivery
    {
        $user = auth()->user();
        abort_unless($delivery->business_id === $user->business_id, 403);
        if ($user->role === 'delivery_staff') {
            abort_unless($delivery->delivery_staff_id === $user->id, 403);
        }

        return $delivery;
    }
}
