<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\BusinessActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(private BusinessActivityService $activity) {}

    public function index()
    {
        $businessId = auth()->user()->business_id;
        return view('business.inventory.index', [
            'inventories' => Inventory::with('product')->where('business_id', $businessId)->whereHas('product')->paginate(12),
            'lowStockProducts' => Product::where('business_id', $businessId)
                ->whereColumn('stock_quantity', '<=', 'low_stock_alert_qty')
                ->get(),
            'movements' => InventoryMovement::with('product', 'creator')->where('business_id', $businessId)->latest('movement_date')->take(30)->get(),
        ]);
    }

    public function adjust(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'type' => ['required', 'in:added,reduced,returned,damaged,adjustment'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->recordMovement($data, false);

        return back()->with('success', 'Stock adjusted.');
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        $data['type'] = 'transfer';
        $this->recordMovement($data, true);

        return back()->with('success', 'Stock transfer recorded.');
    }

    public function updateAlert(Request $request, Inventory $inventory)
    {
        abort_unless($inventory->business_id === auth()->user()->business_id, 403);
        $data = $request->validate(['low_stock_alert' => ['required', 'integer', 'min:0']]);
        $inventory->update($data);
        $inventory->product?->update(['low_stock_alert_qty' => $data['low_stock_alert']]);

        return back()->with('success', 'Low stock alert updated.');
    }

    private function recordMovement(array $data, bool $isTransfer): void
    {
        $businessId = (int) auth()->user()->business_id;

        DB::transaction(function () use ($data, $businessId, $isTransfer): void {
            $product = Product::where('business_id', $businessId)
                ->lockForUpdate()
                ->findOrFail($data['product_id']);
            $type = $data['type'];
            $quantity = (int) $data['quantity'];
            $previousStock = (int) $product->stock_quantity;
            $decreasesStock = in_array($type, ['reduced', 'damaged', 'transfer'], true);

            if ($decreasesStock && $quantity > $previousStock) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient stock. Only '.$previousStock.' units are available.',
                ]);
            }

            $newStock = match ($type) {
                'added', 'returned' => $previousStock + $quantity,
                'reduced', 'damaged', 'transfer' => $previousStock - $quantity,
                'adjustment' => $quantity,
            };
            $movementQuantity = $type === 'adjustment' ? abs($newStock - $previousStock) : $quantity;
            $inventoryType = match ($type) {
                'added' => 'ADD_STOCK',
                'reduced' => 'REMOVE_STOCK',
                'returned' => 'RETURNED',
                'damaged' => 'DAMAGED',
                'transfer' => 'TRANSFER_OUT',
                default => 'ADJUSTMENT',
            };

            $product->update(['stock_quantity' => $newStock, 'current_stock' => $newStock]);
            $inventory = Inventory::firstOrCreate(
                ['business_id' => $businessId, 'product_id' => $product->id],
                ['available_stock' => $previousStock, 'low_stock_alert' => $product->low_stock_alert_qty ?? 10]
            );
            $inventory->update([
                'available_stock' => $newStock,
                'damaged_stock' => (int) $inventory->damaged_stock + ($type === 'damaged' ? $movementQuantity : 0),
                'returned_stock' => (int) $inventory->returned_stock + ($type === 'returned' ? $movementQuantity : 0),
                'low_stock_alert' => $product->low_stock_alert_qty ?? 10,
            ]);

            $note = $data['note'] ?? $data['reason'] ?? null;
            InventoryMovement::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'type' => $inventoryType,
                'quantity' => $movementQuantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'note' => $note,
                'created_by' => auth()->id(),
                'movement_date' => now(),
            ]);
            StockMovement::create([
                'business_id' => $businessId,
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $movementQuantity,
                'reason' => $isTransfer ? 'Stock transfer' : 'Manual inventory movement',
                'note' => $note,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
            ]);
        });

        $this->activity->record($businessId, 'Inventory', $isTransfer ? 'Stock transfer recorded' : 'Inventory adjustment recorded', null, null, [
            'product_id' => (int) $data['product_id'],
            'type' => $data['type'],
            'quantity' => (int) $data['quantity'],
        ]);
    }
}
