<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\KhataLedger;
use App\Models\Order;
use App\Models\Payment;
use App\Services\AccountingService;
use App\Services\FinanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private FinanceCalculator $finance, private AccountingService $accounting) {}

    public function index()
    {
        $businessId = auth()->user()->business_id;
        $payments = Payment::with(['customer', 'order'])->where('business_id', $businessId);
        if (request('method')) $payments->where('method', request('method'));
        if (request('status')) $payments->where('status', request('status'));
        return view('business.payments.index', [
            'payments' => $payments->latest()->paginate(20)->withQueryString(),
            'customers' => Customer::where('business_id', $businessId)->get(),
            'orders' => Order::where('business_id', $businessId)->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['nullable', 'exists:orders,id'], 'customer_id' => ['required', 'exists:customers,id'],
            'method' => ['required', 'in:Cash,Bank Transfer,JazzCash manual,Easypaisa manual,Cheque,JazzCash,Easypaisa'], 'amount' => ['required', 'numeric', 'min:0'],
            'transaction_reference' => ['nullable', 'max:255'], 'reference_number' => ['nullable', 'max:255'], 'payment_date' => ['nullable', 'date'], 'proof_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', 'in:Paid,Partial,Pending'],
        ]);
        $businessId = auth()->user()->business_id;
        $customer = Customer::where('business_id', $businessId)->findOrFail($data['customer_id']);
        $order = null;
        if (!empty($data['order_id'])) {
            $order = Order::where('business_id', $businessId)->findOrFail($data['order_id']);
            if ($order->customer_id && (int) $order->customer_id !== (int) $customer->id) {
                return back()->withErrors(['order_id' => 'Selected order does not belong to this customer.'])->withInput();
            }

            $remaining = $this->finance->calculateBalance((float) ($order->grand_total ?: $order->total), $this->finance->calculatePaidAmount($order));
            if ((float) $data['amount'] > $remaining) {
                return back()->withErrors(['amount' => 'Payment cannot exceed remaining order balance of Rs '.number_format($remaining).'.'])->withInput();
            }
        } elseif ((float) $data['amount'] > (float) $customer->current_balance) {
            return back()->withErrors(['amount' => 'Payment cannot exceed customer balance of Rs '.number_format($customer->current_balance).'.'])->withInput();
        }

        if ($request->hasFile('proof_image')) $data['proof_image'] = $request->file('proof_image')->store('payments', 'public');
        $data['business_id'] = $businessId;
        $data['payment_date'] = $data['payment_date'] ?? now()->toDateString();
        $data['reference_number'] = $data['reference_number'] ?? $data['transaction_reference'] ?? null;
        $payment = null;
        DB::transaction(function () use ($data, $customer, $order, &$payment) {
            $payment = Payment::create($data);
            $balance = max(0, (float) $customer->current_balance - (float) $data['amount']);
            $customer->update(['current_balance' => $balance]);
            KhataLedger::create([
                'business_id' => auth()->user()->business_id,
                'customer_id' => $customer->id,
                'order_id' => $data['order_id'] ?? null,
                'payment_id' => $payment->id,
                'entry_type' => 'payment',
                'type' => 'debit',
                'amount' => $data['amount'],
                'customer_debit' => $data['amount'],
                'customer_credit' => 0,
                'business_debit' => $data['amount'],
                'business_credit' => 0,
                'payment_method' => $data['method'],
                'description' => 'Payment received via '.$data['method'],
                'balance' => $balance,
                'balance_after' => $balance,
                'entry_date' => now()->toDateString(),
            ]);

            if ($order) {
                $synced = $this->finance->syncOrderPaymentSummary($order);
                $payment->update(['status' => $synced->payment_status]);
            }
            $this->postPaymentAccounting($payment);
        });
        return back()->with('success', 'Manual payment recorded.');
    }

    private function postPaymentAccounting(Payment $payment): void
    {
        if (JournalEntry::where('business_id', $payment->business_id)->where('reference_type', 'payment')->where('reference_id', $payment->id)->exists()) {
            return;
        }

        $this->accounting->ensureDefaultAccounts($payment->business_id);
        $cashAccount = Account::where('business_id', $payment->business_id)
            ->where('name', str_contains((string) $payment->method, 'Bank') ? 'Bank' : 'Cash')
            ->first();
        $receivableAccount = Account::where('business_id', $payment->business_id)->where('name', 'Accounts Receivable')->first();

        if (!$cashAccount || !$receivableAccount || (float) $payment->amount <= 0) {
            return;
        }

        $this->accounting->post($payment->business_id, [
            'voucher_number' => 'PAY-JV-'.$payment->id.'-'.now()->format('His'),
            'entry_date' => $payment->payment_date ?? now()->toDateString(),
            'reference_type' => 'payment',
            'reference_id' => $payment->id,
            'description' => 'Payment received via '.$payment->method,
        ], [
            ['account_id' => $cashAccount->id, 'customer_id' => $payment->customer_id, 'debit' => $payment->amount, 'credit' => 0, 'description' => $payment->method],
            ['account_id' => $receivableAccount->id, 'customer_id' => $payment->customer_id, 'debit' => 0, 'credit' => $payment->amount, 'description' => $payment->method],
        ]);
    }
}
