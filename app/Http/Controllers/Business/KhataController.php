<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\KhataLedger;
use Illuminate\Http\Request;

class KhataController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'order_number' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'entry_type' => ['nullable', 'in:purchase,payment'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        if (!empty($filters['date_from']) && !empty($filters['date_to']) && $filters['date_to'] < $filters['date_from']) {
            return back()->withErrors(['date_to' => 'Date To must be after or equal to Date From.'])->withInput();
        }

        $businessId = auth()->user()->business_id;
        $query = KhataLedger::with(['customer', 'order.items.product'])
            ->where('business_id', $businessId)
            ->when($filters['customer_id'] ?? null, fn ($q, $value) => $q->where('customer_id', $value))
            ->when($filters['entry_type'] ?? null, fn ($q, $value) => $q->where('entry_type', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('entry_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('entry_date', '<=', $value))
            ->when($filters['month'] ?? null, fn ($q, $value) => $q->whereMonth('entry_date', $value))
            ->when($filters['year'] ?? null, fn ($q, $value) => $q->whereYear('entry_date', $value))
            ->when($filters['order_number'] ?? null, fn ($q, $value) => $q->whereHas('order', fn ($order) => $order->where('order_number', 'like', "%{$value}%")))
            ->when($filters['description'] ?? null, fn ($q, $value) => $q->where('description', 'like', "%{$value}%"));

        $summaryQuery = clone $query;
        $customerCredit = (clone $summaryQuery)->sum('customer_credit');
        $customerDebit = (clone $summaryQuery)->sum('customer_debit');
        $businessDebit = (clone $summaryQuery)->sum('business_debit');
        $businessCredit = (clone $summaryQuery)->sum('business_credit');

        $customers = Customer::where('business_id', $businessId)->orderBy('name')->get();

        return view('business.khata.index', [
            'customers' => $customers,
            'ledgers' => $query->orderByDesc('entry_date')->latest()->paginate(20)->withQueryString(),
            'totalReceivable' => $customerCredit - $customerDebit,
            'customerCredit' => $customerCredit,
            'customerDebit' => $customerDebit,
            'businessDebit' => $businessDebit,
            'businessCredit' => $businessCredit,
            'paymentsReceived' => $customerDebit,
            'remainingBalance' => $customerCredit - $customerDebit,
            'customerSummaries' => $customers->map(function (Customer $customer) use ($businessId) {
                $base = KhataLedger::where('business_id', $businessId)->where('customer_id', $customer->id);
                $purchases = (clone $base)->sum('customer_credit');
                $payments = (clone $base)->sum('customer_debit');

                return [
                    'customer' => $customer,
                    'purchases' => $purchases,
                    'payments' => $payments,
                    'balance' => $purchases - $payments,
                    'last_transaction' => (clone $base)->max('entry_date'),
                ];
            })->filter(fn ($row) => $row['purchases'] > 0 || $row['payments'] > 0 || $row['balance'] > 0),
        ]);
    }
}
