<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Unit;
use App\Services\BusinessActivityService;
use App\Services\CompanyPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchExpiryController extends Controller
{
    public function __construct(private BusinessActivityService $activity) {}

    public function index(Request $request)
    {
        $businessId = (int) $request->user()->business_id;
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'product_id' => ['nullable', 'integer'], 'category_id' => ['nullable', 'integer'], 'unit_id' => ['nullable', 'integer'], 'status' => ['nullable', 'in:All,Valid,Expiring Soon,Expired,Depleted']]);
        $today = now(config('app.timezone'))->toDateString();
        $query = ProductBatch::query()->with(['product.category', 'product.unitRecord', 'purchase', 'goodsReceipt'])->where('business_id', $businessId)
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($inner) => $inner->where('batch_number', 'like', "%{$value}%")->orWhereHas('product', fn ($products) => $products->where('name', 'like', "%{$value}%"))))
            ->when($filters['product_id'] ?? null, fn ($q, $value) => $q->where('product_id', $value))
            ->when($filters['category_id'] ?? null, fn ($q, $value) => $q->whereHas('product', fn ($products) => $products->where('category_id', $value)))
            ->when($filters['unit_id'] ?? null, fn ($q, $value) => $q->whereHas('product', fn ($products) => $products->where('unit_id', $value)));
        if (($filters['status'] ?? 'All') === 'Expired') $query->where('remaining_quantity', '>', 0)->whereDate('expiry_date', '<', $today);
        if (($filters['status'] ?? 'All') === 'Depleted') $query->where('remaining_quantity', '<=', 0);
        $batches = $query->orderByRaw('expiry_date is null')->orderBy('expiry_date')->paginate(25)->withQueryString();
        if (in_array(($filters['status'] ?? 'All'), ['Valid', 'Expiring Soon'], true)) $batches->setCollection($batches->getCollection()->filter(fn (ProductBatch $batch) => $batch->expiry_status === $filters['status'])->values());
        $trackedProducts = Product::query()->where('business_id', $businessId)->where('has_batch_tracking', true)->orderBy('name')->get(['id', 'name', 'stock_quantity']);
        $unallocated = $trackedProducts->filter(fn (Product $product) => (float) $product->stock_quantity - (float) $product->batches()->sum('remaining_quantity') > 0.0001);
        return view('business.inventory.batches.index', compact('batches', 'filters', 'trackedProducts', 'unallocated') + [
            'categories' => Category::where('business_id', $businessId)->where('type', 'Product')->where('status', 'Active')->orderBy('name')->get(),
            'units' => Unit::where('business_id', $businessId)->where('status', 'Active')->orderBy('unit_name')->get(),
        ]);
    }

    public function allocateOpening(Request $request, Product $product)
    {
        $businessId = (int) $request->user()->business_id;
        abort_unless($product->business_id === $businessId && $product->has_batch_tracking, 404);
        abort_unless(app(CompanyPermissionService::class)->allowsUser($request->user(), 'inventory.adjust_stock'), 403);
        $data = $request->validate(['batch_number' => ['required', 'string', 'max:120'], 'quantity' => ['required', 'numeric', 'min:0.001'], 'manufacturing_date' => ['nullable', 'date'], 'expiry_date' => ['required', 'date', 'after_or_equal:today']]);
        if (! empty($data['manufacturing_date']) && $data['manufacturing_date'] > $data['expiry_date']) throw ValidationException::withMessages(['manufacturing_date' => 'Manufacturing date cannot be after expiry date.']);
        DB::transaction(function () use ($product, $businessId, $data): void {
            $locked = Product::where('business_id', $businessId)->lockForUpdate()->findOrFail($product->id);
            $allocated = (float) ProductBatch::where('business_id', $businessId)->where('product_id', $locked->id)->lockForUpdate()->sum('remaining_quantity');
            $unallocated = round((float) $locked->stock_quantity - $allocated, 3);
            if ($unallocated <= 0.0001 || abs($unallocated - (float) $data['quantity']) > 0.0001) throw ValidationException::withMessages(['quantity' => 'Opening batch quantity must equal the currently unallocated stock ('.rtrim(rtrim(number_format(max(0, $unallocated), 3, '.', ''), '0'), '.').').']);
            ProductBatch::create($data + ['business_id' => $businessId, 'product_id' => $locked->id, 'received_quantity' => $data['quantity'], 'remaining_quantity' => $data['quantity'], 'unit_cost' => $locked->currentPurchasePrice(), 'source' => 'Opening Allocation']);
        });
        $this->activity->record($businessId, 'Inventory', 'Opening stock allocated to batch for '.$product->name, $product->id);
        return back()->with('success', 'Existing stock is now allocated to the specified batch.');
    }
}
