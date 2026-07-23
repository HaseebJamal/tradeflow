<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\JournalEntryLine;
use App\Models\Order;
use App\Models\Payment;
use App\Services\CustomerFinancialFieldService;
use App\Services\CustomerOpeningBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with('creator')->where('business_id', auth()->user()->business_id);

        if ($request->input('status') === 'Archived' || $request->boolean('archived')) {
            $query->onlyTrashed();
        }
        $query
            ->when($request->search, fn ($q, $value) => $q->where(fn ($inner) => $inner->where('name', 'like', "%{$value}%")->orWhere('business_name', 'like', "%{$value}%")->orWhere('phone', 'like', "%{$value}%")->orWhere('email', 'like', "%{$value}%")))
            ->when($request->customer_type, fn ($q, $value) => $q->where('customer_type', $value))
            ->when($request->city, fn ($q, $value) => $q->where('city', 'like', "%{$value}%"))
            ->when(in_array($request->status, ['Active', 'Blocked', 'Inactive'], true), fn ($q, $value) => $q->where('status', $value))
            ->when($request->created_by, fn ($q, $value) => $q->where('created_by', $value))
            ->when($request->date_from, fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($request->date_to, fn ($q, $value) => $q->whereDate('created_at', '<=', $value));

        return view('business.customers.index', ['customers' => $query->latest()->paginate(12)->withQueryString()]);
    }

    public function store(Request $request, CustomerFinancialFieldService $financialFields, CustomerOpeningBalanceService $openingBalances)
    {
        $financialFields->normalizeRequest($request, true);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'], 'shop_name' => ['nullable', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'], 'business_name' => ['nullable', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'], 'phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable'], 'city' => ['nullable', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'], 'province' => ['nullable', 'max:100'], 'customer_type' => ['required', 'in:Retailer,Dealer,Distributor,Walk-in Customer,Other,Wholesaler'],
            'credit_limit' => $financialFields->wholeNumberRules(),
            'opening_balance' => $financialFields->wholeNumberRules(),
            'status' => ['required', 'in:Active,Blocked'],
        ]);
        $data['business_name'] = $data['shop_name'] ?? $data['business_name'] ?? null;
        unset($data['shop_name']);
        $data['business_id'] = auth()->user()->business_id;
        $data['created_by'] = auth()->id();
        $data['current_balance'] = (int) $data['opening_balance'];

        DB::transaction(function () use ($data, $openingBalances): void {
            $customer = Customer::create($data);
            $openingBalances->recordFor($customer);
        });

        return back()->with('success', 'Customer saved.');
    }

    public function show(Customer $customer)
    {
        abort_unless($customer->business_id === auth()->user()->business_id, 403);
        $customer->load(['orders.items', 'ledgers', 'payments']);
        $totalSales = $customer->orders
            ->reject(fn ($order) => in_array($order->status, ['Cancelled', 'Void'], true))
            ->sum(fn ($order) => $order->grand_total ?: $order->total);
        $paymentsReceived = $customer->payments->sum('amount');

        return view('business.customers.show', [
            'customer' => $customer,
            'totalSales' => $totalSales,
            'paymentsReceived' => $paymentsReceived,
            'outstanding' => max(0, (float) $customer->current_balance),
            'lastOrder' => $customer->orders->sortByDesc('created_at')->first(),
            'lastPayment' => $customer->payments->sortByDesc('payment_date')->first(),
            'journalLines' => JournalEntryLine::with(['journalEntry', 'account'])->where('customer_id', $customer->id)->latest()->limit(50)->get(),
        ]);
    }

    public function update(Request $request, Customer $customer, CustomerFinancialFieldService $financialFields)
    {
        abort_unless($customer->business_id === auth()->user()->business_id, 403);
        $financialFields->normalizeRequest($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'shop_name' => ['nullable', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'business_name' => ['nullable', 'string', 'max:255', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'phone' => ['nullable', 'regex:/^\\+[1-9]\\d{7,14}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable'],
            'city' => ['nullable', 'string', 'max:100', 'regex:/^[\pL]+(?:[ \t][\pL]+)*$/u'],
            'province' => ['nullable', 'max:100'],
            'customer_type' => ['nullable', 'in:Retailer,Dealer,Distributor,Walk-in Customer,Other,Wholesaler'],
            'credit_limit' => $request->has('credit_limit') ? $financialFields->wholeNumberRules() : ['sometimes'],
            'opening_balance' => $request->has('opening_balance') ? $financialFields->wholeNumberRules() : ['sometimes'],
            'current_balance' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:Active,Blocked,Inactive'],
        ]);
        if (!empty($data['shop_name'])) {
            $data['business_name'] = $data['shop_name'];
            unset($data['shop_name']);
        }
        $customer->update($data);
        return back()->with('success', 'Customer updated.');
    }

    public function updateStatus(Request $request, Customer $customer)
    {
        abort_unless($customer->business_id === auth()->user()->business_id, 404);
        $data = $request->validate(['status' => ['required', 'in:Active,Blocked,Inactive']]);
        $customer->update(['status' => $data['status']]);

        return back()->with('success', 'Customer status updated to '.$data['status'].'.');
    }

    public function archive(Customer $customer)
    {
        abort_unless($customer->business_id === auth()->user()->business_id, 403);
        $customer->update(['status' => 'Inactive']);
        $customer->delete();

        return back()->with('success', 'Record archived successfully.');
    }

    public function restore(int $customer)
    {
        $record = Customer::withTrashed()->where('business_id', auth()->user()->business_id)->findOrFail($customer);
        $record->restore();
        $record->update(['status' => 'Active']);

        return back()->with('success', 'Record restored successfully.');
    }

    public function destroy(Customer $customer)
    {
        abort_unless($customer->business_id === auth()->user()->business_id, 403);
        $hasHistory = Order::where('business_id', $customer->business_id)->where('customer_id', $customer->id)->exists()
            || Payment::where('business_id', $customer->business_id)->where('customer_id', $customer->id)->exists()
            || Delivery::where('business_id', $customer->business_id)->where('customer_id', $customer->id)->exists()
            || JournalEntryLine::where('customer_id', $customer->id)->exists();

        if ($hasHistory) {
            $customer->update(['status' => 'Inactive']);
            $customer->delete();
            return back()->with('success', 'This customer has historical transactions and has been archived instead.');
        }

        $customer->forceDelete();
        return redirect()->route('business.customers.index')->with('success', 'Customer deleted.');
    }

    public function statement(Customer $customer)
    {
        abort_unless($customer->business_id === auth()->user()->business_id, 403);
        $lines = JournalEntryLine::with(['journalEntry', 'account'])
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();
        $csv = "Date,Voucher,Account,Description,Debit,Credit\n";
        foreach ($lines as $line) {
            $csv .= implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', [
                $line->journalEntry?->entry_date?->format('Y-m-d'),
                $line->journalEntry?->voucher_number,
                $line->account?->name,
                $line->description,
                $line->debit,
                $line->credit,
            ]))."\n";
        }

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename=customer-statement.csv']);
    }
}
