<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PurchaseReturnController extends Controller
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

        $returns = PurchaseReturn::with(['items', 'purchase', 'supplier'])
            ->where('business_id', $businessId)
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->where(fn ($inner) => $inner
                ->where('return_number', 'like', "%{$value}%")
                ->orWhereHas('purchase', fn ($purchases) => $purchases->where('purchase_number', 'like', "%{$value}%"))))
            ->where('created_at', '>=', Carbon::parse($filters['date_from'], config('app.timezone'))->startOfDay())
            ->where('created_at', '<=', Carbon::parse($filters['date_to'], config('app.timezone'))->endOfDay())
            ->latest('created_at')->paginate(12)->withQueryString();

        $references = collect()
            ->merge(Purchase::where('business_id', $businessId)->whereNotNull('purchase_number')->latest('purchase_date')->pluck('purchase_number'))
            ->merge(PurchaseReturn::where('business_id', $businessId)->latest('created_at')->pluck('return_number'))
            ->filter()->unique()->values();

        return view('business.purchase-returns.index', compact('returns', 'references'));
    }

    public function create(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $request->validate(['purchase_id' => ['nullable', 'integer']]);

        $purchases = Purchase::with('supplier')->where('business_id', $businessId)
            ->whereNotIn('status', ['Ordered', 'Returned'])
            ->latest('purchase_date')->get();

        $purchase = null;
        if ($request->filled('purchase_id')) {
            $purchase = Purchase::with(['supplier', 'items.product', 'returns.items'])
                ->where('business_id', $businessId)
                ->whereNotIn('status', ['Ordered', 'Returned'])
                ->findOrFail($request->integer('purchase_id'));
        }

        return view('business.purchase-returns.create', compact('purchases', 'purchase'));
    }

    public function start(Request $request)
    {
        $data = $request->validate(['purchase_id' => ['required', 'integer']]);
        $purchase = Purchase::where('business_id', $request->user()->business_id)->findOrFail($data['purchase_id']);

        $purchase = Purchase::with(['supplier', 'items.product', 'returns.items'])
            ->where('business_id', $request->user()->business_id)
            ->whereNotIn('status', ['Ordered', 'Returned'])
            ->findOrFail($purchase->id);
        $purchases = Purchase::with('supplier')->where('business_id', $request->user()->business_id)
            ->whereNotIn('status', ['Ordered', 'Returned'])
            ->latest('purchase_date')->get();

        return view('business.purchase-returns.create', compact('purchases', 'purchase'));
    }

    public function show(Request $request, PurchaseReturn $purchaseReturn)
    {
        abort_unless($purchaseReturn->business_id === $request->user()->business_id, 404);
        return view('business.purchase-returns.show', ['return' => $purchaseReturn->load(['items.product', 'purchase.supplier', 'supplier'])]);
    }

    public function edit(Request $request, PurchaseReturn $purchaseReturn)
    {
        abort_unless($purchaseReturn->business_id === $request->user()->business_id, 404);
        return view('business.purchase-returns.edit', ['return' => $purchaseReturn->load(['items.product', 'purchase.supplier'])]);
    }
}
