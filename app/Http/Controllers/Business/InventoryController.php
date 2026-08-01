<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\BusinessActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    public function __construct(private BusinessActivityService $activity) {}

    public function index()
    {
        $businessId = auth()->user()->business_id;
        return view('business.inventory.index', [
            'inventories' => Inventory::with('product')->where('business_id', $businessId)->whereHas('product')->latest()->paginate(10)->withQueryString(),
            'inventoryProducts' => Product::where('business_id', $businessId)->orderBy('name')->get(['id', 'name']),
            'lowStockProducts' => Product::where('business_id', $businessId)
                ->whereColumn('stock_quantity', '<=', 'low_stock_alert_qty')
                ->get(),
            'categories' => Category::where('business_id', $businessId)->where('type', 'Product')->where('status', 'Active')->orderBy('name')->get(),
            'units' => Unit::where('business_id', $businessId)->where('status', 'Active')->orderBy('unit_name')->get(),
        ]);
    }

    public function history(Request $request)
    {
        $businessId = (int) auth()->user()->business_id;
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'user_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $movementQuery = InventoryMovement::query()
            ->with(['product', 'creator'])
            ->where('business_id', $businessId);

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $movementQuery->whereHas('product', fn ($query) => $query->where('name', 'like', "%{$search}%"));
        }
        if ($type = $filters['type'] ?? null) {
            $movementQuery->where('type', $type);
        }
        if ($dateFrom = $filters['date_from'] ?? null) {
            $movementQuery->whereDate('movement_date', '>=', $dateFrom);
        }
        if ($dateTo = $filters['date_to'] ?? null) {
            $movementQuery->whereDate('movement_date', '<=', $dateTo);
        }
        if ($userId = $filters['user_id'] ?? null) {
            $movementQuery->where('created_by', $userId);
        }
        if ($month = $filters['month'] ?? null) {
            $movementQuery->whereMonth('movement_date', $month);
        }
        if ($year = $filters['year'] ?? null) {
            $movementQuery->whereYear('movement_date', $year);
        }

        return view('business.inventory.history', [
            'movements' => $movementQuery->latest('movement_date')->paginate(10)->withQueryString(),
            'movementTypes' => InventoryMovement::where('business_id', $businessId)->distinct()->orderBy('type')->pluck('type'),
            'users' => User::where('business_id', $businessId)->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function adjust(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where(fn ($query) => $query->where('business_id', auth()->user()->business_id))],
            'type' => ['required', 'in:added,reduced,returned,damaged,adjustment'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], ['product_id.required' => 'Please select a product.', 'product_id.exists' => 'Please select a valid product.']);

        $this->recordMovement($data);

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

    private function recordMovement(array $data): void
    {
        $businessId = (int) auth()->user()->business_id;

        DB::transaction(function () use ($data, $businessId): void {
            $product = Product::where('business_id', $businessId)
                ->lockForUpdate()
                ->findOrFail($data['product_id']);
            $type = $data['type'];
            $quantity = (int) $data['quantity'];
            $previousStock = (int) $product->stock_quantity;
            $decreasesStock = in_array($type, ['reduced', 'damaged'], true);

            if ($decreasesStock && $quantity > $previousStock) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient stock. Only '.$previousStock.' units are available.',
                ]);
            }

            $newStock = match ($type) {
                'added', 'returned' => $previousStock + $quantity,
                'reduced', 'damaged' => $previousStock - $quantity,
                'adjustment' => $quantity,
            };
            $movementQuantity = $type === 'adjustment' ? abs($newStock - $previousStock) : $quantity;
            $inventoryType = match ($type) {
                'added' => 'ADD_STOCK',
                'reduced' => 'REMOVE_STOCK',
                'returned' => 'RETURNED',
                'damaged' => 'DAMAGED',
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
                'reason' => 'Manual inventory movement',
                'note' => $note,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
            ]);
        });

        $this->activity->record($businessId, 'Inventory', 'Inventory adjustment recorded', null, null, [
            'product_id' => (int) $data['product_id'],
            'type' => $data['type'],
            'quantity' => (int) $data['quantity'],
        ]);
    }
}
