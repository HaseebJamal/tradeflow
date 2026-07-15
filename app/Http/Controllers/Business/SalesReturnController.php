<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PosReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SalesReturnController extends Controller
{
    public function index(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $filters['date_from'] ??= now(config('app.timezone'))->toDateString();
        $filters['date_to'] ??= now(config('app.timezone'))->toDateString();
        $returns = PosReturn::with(['order', 'customer', 'items.orderItem'])
            ->where('business_id', $businessId)
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->whereHas('order', fn ($orders) => $orders->where('order_number', 'like', "%{$value}%")))
            ->where('returned_at', '>=', Carbon::parse($filters['date_from'], config('app.timezone'))->startOfDay())
            ->where('returned_at', '<=', Carbon::parse($filters['date_to'], config('app.timezone'))->endOfDay())
            ->latest('returned_at')->paginate(20)->withQueryString();

        return view('business.sales-returns.index', compact('returns'));
    }

    public function create(Request $request)
    {
        $orders = Order::where('business_id', $request->user()->business_id)->where('sale_channel', 'pos')
            ->whereNotIn('status', ['Cancelled', 'Void'])->latest('order_date')->get();
        return view('business.sales-returns.create', compact('orders'));
    }

    public function start(Request $request)
    {
        $data = $request->validate(['order_id' => ['required', 'integer']]);
        $order = Order::where('business_id', $request->user()->business_id)->where('sale_channel', 'pos')->findOrFail($data['order_id']);
        return redirect()->route('business.sales.returns.process', $order);
    }

    public function process(Request $request, Order $order, PosController $pos)
    {
        return $pos->returns($order);
    }

    public function store(Request $request, Order $order, PosController $pos)
    {
        return $pos->storeReturn($request, $order);
    }

    public function show(Request $request, PosReturn $salesReturn)
    {
        abort_unless($salesReturn->business_id === $request->user()->business_id, 404);
        return view('business.sales-returns.show', ['return' => $salesReturn->load(['order', 'customer', 'items.orderItem'])]);
    }

    public function edit(Request $request, PosReturn $salesReturn)
    {
        abort_unless($salesReturn->business_id === $request->user()->business_id, 404);
        return view('business.sales-returns.edit', ['return' => $salesReturn->load(['order', 'customer', 'items.orderItem'])]);
    }
}
