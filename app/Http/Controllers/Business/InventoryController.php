<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        $businessId = auth()->user()->business_id;
        return view('business.inventory.index', [
            'inventories' => Inventory::with('product')->where('business_id', $businessId)->paginate(20),
            'lowStockProducts' => Product::where('business_id', $businessId)
                ->whereColumn('stock_quantity', '<=', 'low_stock_alert_qty')
                ->get(),
            'movements' => StockMovement::with('product', 'user')->where('business_id', $businessId)->latest()->take(30)->get(),
        ]);
    }

    public function adjust(Request $request)
    {
        $data = $request->validate(['product_id' => ['required', 'exists:products,id'], 'type' => ['required', 'in:added,reduced,sold,returned,damaged,adjustment,Adjustment,Damaged,Returned'], 'quantity' => ['required', 'integer'], 'note' => ['nullable', 'max:255'], 'reason' => ['nullable', 'max:255']]);
        $product = Product::where('business_id', auth()->user()->business_id)->findOrFail($data['product_id']);
        $type = strtolower($data['type']);
        $quantity = abs((int) $data['quantity']);
        in_array($type, ['reduced', 'sold', 'damaged'], true) ? $product->decrement('stock_quantity', $quantity) : $product->increment('stock_quantity', $quantity);
        $product->inventory()->updateOrCreate(['business_id' => auth()->user()->business_id], ['available_stock' => $product->stock_quantity, 'low_stock_alert' => $product->low_stock_alert_qty]);
        StockMovement::create(['business_id' => auth()->user()->business_id, 'product_id' => $product->id, 'type' => $type, 'quantity' => $quantity, 'reason' => $data['reason'] ?? null, 'note' => $data['note'] ?? $data['reason'] ?? null, 'user_id' => auth()->id(), 'created_by' => auth()->id()]);
        return back()->with('success', 'Stock adjusted.');
    }

    public function updateAlert(Request $request, Inventory $inventory)
    {
        abort_unless($inventory->business_id === auth()->user()->business_id, 403);
        $data = $request->validate(['low_stock_alert' => ['required', 'integer', 'min:0']]);
        $inventory->update($data);
        $inventory->product?->update(['low_stock_alert_qty' => $data['low_stock_alert']]);

        return back()->with('success', 'Low stock alert updated.');
    }
}
