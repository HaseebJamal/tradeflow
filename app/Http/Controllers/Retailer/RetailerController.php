<?php

namespace App\Http\Controllers\Retailer;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use App\Models\KhataLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\FinanceCalculator;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RetailerController extends Controller
{
    public function __construct(private FinanceCalculator $finance, private DocumentNumberService $numbers) {}

    public function dashboard()
    {
        $orders = Order::where('retailer_id', auth()->id());
        return view('retailer.dashboard', ['ordersCount' => $orders->count(), 'openOrders' => (clone $orders)->whereNotIn('status', ['Delivered', 'Cancelled'])->count(), 'orders' => $orders->with('business')->latest()->take(8)->get()]);
    }

    public function products()
    {
        return view('retailer.products', ['products' => Product::with('business')->where('status', 'Active')->latest()->paginate(12), 'businesses' => Business::whereIn('status', ['Approved', 'approved'])->get()]);
    }

    public function cart()
    {
        $businessId = Product::where('status', 'Active')->value('business_id');

        return view('retailer.cart', ['products' => Product::with('business')->where('status', 'Active')->when($businessId, fn ($q) => $q->where('business_id', $businessId))->take(5)->get()]);
    }

    public function placeOrder(Request $request)
    {
        $data = $request->validate(['business_id' => ['required', 'exists:businesses,id'], 'products' => ['required', 'array'], 'products.*.id' => ['required', 'exists:products,id'], 'products.*.quantity' => ['required', 'integer', 'min:0']]);
        $data['products'] = collect($data['products'])->filter(fn ($line) => (int) $line['quantity'] > 0)->values()->all();
        if (empty($data['products'])) {
            return back()->withErrors(['products' => 'Select at least one product quantity.']);
        }
        DB::transaction(function () use ($data) {
            $customer = Customer::firstOrCreate(
                ['business_id' => $data['business_id'], 'phone' => auth()->user()->phone],
                ['name' => auth()->user()->name, 'business_name' => auth()->user()->name, 'customer_type' => 'Retailer', 'status' => 'Active']
            );
            $order = Order::create(['order_number' => $this->numbers->next('sales'), 'business_id' => $data['business_id'], 'customer_id' => $customer->id, 'retailer_id' => auth()->id()]);
            $subtotal = 0;
            foreach ($data['products'] as $line) {
                $product = Product::where('business_id', $data['business_id'])->findOrFail($line['id']);
                $total = $product->wholesale_price * $line['quantity'];
                $subtotal += $total;
                OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => $line['quantity'], 'price' => $product->wholesale_price, 'total' => $total]);
            }
            $discountAmount = $this->finance->calculateDiscountAmount($subtotal, 0);
            $grandTotal = $this->finance->calculateGrandTotal($subtotal, $discountAmount);
            $order->update([
                'subtotal' => $subtotal,
                'discount' => 0,
                'discount_percentage' => 0,
                'discount_amount' => $discountAmount,
                'total' => $grandTotal,
                'grand_total' => $grandTotal,
                'paid_amount' => 0,
                'balance' => $grandTotal,
                'payment_status' => 'Pending',
            ]);
            $balance = $customer->current_balance + $grandTotal;
            $customer->update(['current_balance' => $balance]);
            KhataLedger::create([
                'business_id' => $data['business_id'],
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'entry_type' => 'purchase',
                'type' => 'credit',
                'amount' => $grandTotal,
                'customer_debit' => 0,
                'customer_credit' => $grandTotal,
                'business_debit' => $grandTotal,
                'business_credit' => 0,
                'description' => 'Retailer order '.$order->order_number,
                'balance' => $balance,
                'balance_after' => $balance,
                'entry_date' => now()->toDateString(),
            ]);
        });

        return redirect()->route('retailer.orders')->with('success', 'Order submitted.');
    }

    public function orders()
    {
        return view('retailer.orders', ['orders' => Order::with('business')->where('retailer_id', auth()->id())->latest()->paginate(12)]);
    }

    public function creditBalance()
    {
        $customers = Customer::where('phone', auth()->user()->phone)->pluck('id');
        return view('retailer.credit-balance', ['balance' => Customer::whereIn('id', $customers)->sum('current_balance'), 'ledgers' => KhataLedger::with('customer.business')->whereIn('customer_id', $customers)->latest()->paginate(12)]);
    }
}
