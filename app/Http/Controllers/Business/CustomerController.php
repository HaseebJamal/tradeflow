<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index() { return view('business.customers.index', ['customers' => Customer::where('business_id', auth()->user()->business_id)->latest()->paginate(15)]); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'max:255'], 'shop_name' => ['nullable', 'max:255'], 'business_name' => ['nullable', 'max:255'], 'phone' => ['nullable', 'max:30'],
            'address' => ['nullable'], 'city' => ['nullable', 'max:100'], 'customer_type' => ['required', 'in:Retailer,Dealer,Wholesaler'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'], 'status' => ['required', 'in:Active,Blocked'],
        ]);
        $data['business_name'] = $data['shop_name'] ?? $data['business_name'] ?? null;
        unset($data['shop_name']);
        $data['business_id'] = auth()->user()->business_id;
        Customer::create($data);
        return back()->with('success', 'Customer saved.');
    }

    public function show(Customer $customer)
    {
        abort_unless($customer->business_id === auth()->user()->business_id, 403);
        return view('business.customers.show', ['customer' => $customer->load(['orders', 'ledgers', 'payments'])]);
    }

    public function update(Request $request, Customer $customer)
    {
        abort_unless($customer->business_id === auth()->user()->business_id, 403);
        $customer->update($request->validate(['status' => ['required', 'in:Active,Blocked']]));
        return back()->with('success', 'Customer updated.');
    }
}
