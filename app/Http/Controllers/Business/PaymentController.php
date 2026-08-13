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
use App\Services\BusinessActivityService;
use App\Services\CompanyPermissionService;
use App\Services\FinanceCalculator;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function __construct(private FinanceCalculator $finance, private AccountingService $accounting, private BusinessActivityService $activity, private CompanyPermissionService $permissions, private DocumentNumberService $numbers)
    {
    }

    public function index(Request $request)
    {
        $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'method' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:Paid,Partial,Pending'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $businessId = auth()->user()->business_id;
        $canViewCustomers = $this->permissions->allowsUser($request->user(), 'customers.view');
        abort_if($request->filled('customer_id') && ! $canViewCustomers, 403);
        $payments = Payment::with(['customer', 'order'])->where('business_id', $businessId);
        $dateFrom = $request->input('date_from') ?: now(config('app.timezone'))->toDateString();
        $dateTo = $request->input('date_to') ?: now(config('app.timezone'))->toDateString();
        if ($request->filled('method'))
            $payments->where('method', $request->string('method')->value());
        if ($request->filled('status'))
            $payments->where('status', $request->string('status')->value());
        if ($request->filled('customer_id'))
            $payments->where('customer_id', $request->integer('customer_id'));
        $payments->where('payment_date', '>=', \Illuminate\Support\Carbon::parse($dateFrom, config('app.timezone'))->startOfDay());
        $payments->where('payment_date', '<=', \Illuminate\Support\Carbon::parse($dateTo, config('app.timezone'))->endOfDay());
        return view('business.payments.index', [
            'payments' => $payments->latest()->paginate(12)->withQueryString(),
            'customers' => $canViewCustomers ? Customer::where('business_id', $businessId)->get() : collect(),
            'canViewCustomers' => $canViewCustomers,
            'orders' => Order::where('business_id', $businessId)->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['nullable', 'exists:orders,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'method' => ['required', 'in:Cash,Bank Transfer,Jazz Cash,Easypaisa,Cheque'],
            'amount' => ['required', 'integer', 'min:1'],
            'transaction_reference' => ['nullable', 'max:255'],
            'reference_number' => ['nullable', 'max:255'],
            'payment_date' => ['nullable', 'date'],
            'proof_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', 'in:Paid,Partial,Pending'],
        ]);
        $businessId = auth()->user()->business_id;
        abort_unless($this->permissions->allowsUser($request->user(), 'customers.view'), 403);
        $customer = Customer::where('business_id', $businessId)->findOrFail($data['customer_id']);
        $order = null;
        if (!empty($data['order_id'])) {
            $order = Order::where('business_id', $businessId)->findOrFail($data['order_id']);
            if ($order->customer_id && (int) $order->customer_id !== (int) $customer->id) {
                return back()->withErrors(['order_id' => 'Selected order does not belong to this customer.'])->withInput();
            }

            $remaining = $this->finance->calculateBalance((float) ($order->grand_total ?: $order->total), $this->finance->calculatePaidAmount($order));
            if ((float) $data['amount'] > $remaining) {
                return back()->withErrors(['amount' => 'Payment cannot exceed remaining order balance of Rs ' . number_format($remaining) . '.'])->withInput();
            }
        } elseif ((float) $data['amount'] > (float) $customer->current_balance) {
            return back()->withErrors(['amount' => 'Payment cannot exceed customer balance of Rs ' . number_format($customer->current_balance) . '.'])->withInput();
        }

        if ($request->hasFile('proof_image'))
            $data['proof_image'] = $request->file('proof_image')->store('payments', 'public');
        $data['business_id'] = $businessId;
        $data['payment_date'] = $data['payment_date'] ?? now()->toDateString();
        $data['transaction_reference'] = $data['transaction_reference'] ?? $data['reference_number'] ?? null;
        $payment = null;
        DB::transaction(function () use ($data, $businessId, $customer, $order, &$payment) {
            $data['reference_number'] = $this->numbers->next($businessId, 'payment');
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
                'description' => 'Payment received via ' . $data['method'],
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
        $this->activity->record($businessId, 'Sales', 'Customer payment recorded', $payment->id, null, [
            'customer_id' => $customer->id,
            'order_id' => $order?->id,
            'amount' => $payment->amount,
            'method' => $payment->method,
        ]);
        return redirect()->route('business.sales.payments.index')->with('success', 'Customer payment recorded successfully.');
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
            'voucher_number' => 'PAY-JV-' . $payment->id . '-' . now()->format('His'),
            'entry_date' => $payment->payment_date ?? now()->toDateString(),
            'reference_type' => 'payment',
            'reference_id' => $payment->id,
            'description' => 'Payment received via ' . $payment->method,
        ], [
            ['account_id' => $cashAccount->id, 'customer_id' => $payment->customer_id, 'debit' => $payment->amount, 'credit' => 0, 'description' => $payment->method],
            ['account_id' => $receivableAccount->id, 'customer_id' => $payment->customer_id, 'debit' => 0, 'credit' => $payment->amount, 'description' => $payment->method],
        ]);
    }
}
