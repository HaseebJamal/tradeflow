<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockCount;
use App\Services\BusinessActivityService;
use App\Services\StockCountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockCountController extends Controller
{
    public function __construct(private StockCountService $stockCounts, private BusinessActivityService $activity) {}

    public function index(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['Draft', 'In Progress', 'Completed', 'Cancelled'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $query = StockCount::query()->with('creator')->withCount([
            'items',
            'items as matched_count' => fn ($query) => $query->whereNotNull('physical_quantity')->where('variance', 0),
            'items as shortage_count' => fn ($query) => $query->where('variance', '<', 0),
            'items as excess_count' => fn ($query) => $query->where('variance', '>', 0),
        ])->where('business_id', $businessId);
        if ($filters['search'] ?? null) $query->where('reference', 'like', '%'.$filters['search'].'%');
        if ($filters['status'] ?? null) $query->where('status', $filters['status']);
        if ($filters['date_from'] ?? null) $query->whereDate('counted_at', '>=', $filters['date_from']);
        if ($filters['date_to'] ?? null) $query->whereDate('counted_at', '<=', $filters['date_to']);

        return view('business.inventory.stock-counts.index', [
            'counts' => $query->latest('counted_at')->paginate(10)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $data = $request->validate(['counted_at' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $count = $this->stockCounts->create((int) $request->user()->business_id, $request->user(), $data['counted_at'] ?? null, $data['notes'] ?? null);
        $this->activity->record($count->business_id, 'Inventory', 'Stock Count Created', $count->id, null, ['reference' => $count->reference]);

        return redirect()->route('business.inventory.stock-counts.edit', $count)->with('success', $count->reference.' created. Add products to begin counting.');
    }

    public function show(Request $request, StockCount $stockCount)
    {
        $this->belongsToBusiness($stockCount, (int) $request->user()->business_id);

        return view('business.inventory.stock-counts.show', ['stockCount' => $stockCount->load(['items.product.category', 'creator', 'completedBy', 'cancelledBy'])]);
    }

    public function edit(Request $request, StockCount $stockCount)
    {
        $this->belongsToBusiness($stockCount, (int) $request->user()->business_id);
        if (in_array($stockCount->status, ['Completed', 'Cancelled'], true)) return redirect()->route('business.inventory.stock-counts.show', $stockCount);
        $businessId = (int) $stockCount->business_id;

        return view('business.inventory.stock-counts.edit', [
            'stockCount' => $stockCount->load(['items.product.category', 'creator']),
            'products' => Product::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get(['id', 'name', 'barcode', 'sku', 'category_id', 'unit', 'stock_quantity']),
            'categories' => Category::where('business_id', $businessId)->where('type', 'Product')->orderBy('name')->get(['id', 'name']),
            'units' => Product::where('business_id', $businessId)->where('status', 'Active')->whereNotNull('unit')->distinct()->orderBy('unit')->pluck('unit'),
            'reasons' => StockCountService::REASONS,
        ]);
    }

    public function addProduct(Request $request, StockCount $stockCount)
    {
        $this->belongsToBusiness($stockCount, (int) $request->user()->business_id);
        $data = $request->validate(['product_id' => ['required', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('business_id', $stockCount->business_id)->whereNull('deleted_at'))]]);
        $this->stockCounts->addProduct($stockCount, (int) $data['product_id']);

        return back()->with('success', 'Product added with its current system-stock snapshot.');
    }

    public function update(Request $request, StockCount $stockCount)
    {
        $this->belongsToBusiness($stockCount, (int) $request->user()->business_id);
        $data = $request->validate([
            'counted_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.physical_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', Rule::in(StockCountService::REASONS)],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);
        $this->stockCounts->save($stockCount, $data['items'] ?? [], $data);
        $this->activity->record($stockCount->business_id, 'Inventory', 'Stock Count Updated', $stockCount->id, null, ['reference' => $stockCount->reference]);

        return back()->with('success', 'Stock count draft saved. Inventory has not changed.');
    }

    public function start(Request $request, StockCount $stockCount)
    {
        $this->belongsToBusiness($stockCount, (int) $request->user()->business_id);
        abort_unless($stockCount->status === 'Draft', 403);
        $stockCount->update(['status' => 'In Progress']);

        return back()->with('success', 'Stock count is now in progress.');
    }

    public function finalize(Request $request, StockCount $stockCount)
    {
        $this->belongsToBusiness($stockCount, (int) $request->user()->business_id);
        $result = $this->stockCounts->finalize($stockCount, $request->user(), $request->boolean('confirm_conflicts'));
        if ($result['conflicts'] !== []) {
            return back()->withErrors(['conflicts' => 'Stock changed during this count. Review the highlighted rows, then explicitly confirm reconciliation against the current stock.']);
        }

        $this->activity->record($stockCount->business_id, 'Inventory', 'Stock Count Finalized', $stockCount->id, null, ['reference' => $stockCount->reference, 'adjusted_products' => $result['adjusted']]);

        return redirect()->route('business.inventory.stock-counts.show', $stockCount)->with('success', $stockCount->reference.' finalized. '.$result['adjusted'].' stock adjustments were recorded.');
    }

    public function cancel(Request $request, StockCount $stockCount)
    {
        $this->belongsToBusiness($stockCount, (int) $request->user()->business_id);
        $this->stockCounts->cancel($stockCount, $request->user());
        $this->activity->record($stockCount->business_id, 'Inventory', 'Stock Count Cancelled', $stockCount->id, null, ['reference' => $stockCount->reference]);

        return redirect()->route('business.inventory.stock-counts.index')->with('success', $stockCount->reference.' was cancelled. No stock was changed.');
    }

    private function belongsToBusiness(StockCount $stockCount, int $businessId): void
    {
        abort_unless((int) $stockCount->business_id === $businessId, 403);
    }
}
