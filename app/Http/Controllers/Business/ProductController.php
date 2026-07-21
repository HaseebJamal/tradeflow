<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\BulkStoreProductRequest;
use App\Http\Requests\Business\StoreProductsRequest;
use App\Http\Requests\Business\StoreOrUpdateProductRequest;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\BarcodeService;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductController extends Controller
{
    public function __construct(private BarcodeService $barcodes) {}

    public function index(Request $request)
    {
        $query = Product::with(['category', 'creator'])->where('business_id', auth()->user()->business_id);
        if (request('status') === 'Archived' || request('archived')) {
            $query->onlyTrashed();
        } elseif (in_array(request('status'), ['Active', 'Inactive'], true)) {
            $query->where('status', request('status'));
        }
        if (request('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.request('search').'%')->orWhere('barcode', 'like', '%'.request('search').'%'));
        }
        if (request('category_id')) {
            $query->where('category_id', request('category_id'));
        }
        if (request('batch_number')) {
            $query->where('batch_number', 'like', '%'.request('batch_number').'%');
        }
        if (request('created_by')) {
            $query->where('created_by', request('created_by'));
        }
        if (request('date_from')) {
            $query->whereDate('created_at', '>=', request('date_from'));
        }
        if (request('date_to')) {
            $query->whereDate('created_at', '<=', request('date_to'));
        }
        if (request('expiry') === 'expired') {
            $query->whereDate('expiry_date', '<', now()->toDateString());
        }
        if (request('expiry') === 'soon') {
            $query->whereDate('expiry_date', '>=', now()->toDateString())->whereDate('expiry_date', '<=', now()->addDays(30)->toDateString());
        }

        return view('business.products.index', [
            'products' => $query->latest()->paginate(15)->withQueryString(),
            'categories' => Category::where('business_id', auth()->user()->business_id)->where('type', 'Product')->orderBy('name')->get(),
        ]);
    }

    /** Resolve an exact scanner lookup within the active business only. */
    public function lookup(Request $request)
    {
        $code = trim((string) $request->validate(['code' => ['required', 'string', 'max:100']])['code']);
        $product = Product::where('business_id', $request->user()->business_id)
            ->where('barcode', $code)
            ->first();

        return response()->json([
            'found' => (bool) $product,
            'url' => $product ? route('business.products.show', $product) : null,
        ]);
    }

    public function create()
    {
        return view('business.products.create', [
            'categories' => Category::where('business_id', auth()->user()->business_id)->where('type', 'Product')->where('status', 'Active')->orderBy('name')->get(),
            'units' => Unit::where('business_id', auth()->user()->business_id)->where('status', 'Active')->orderBy('unit_name')->get(),
        ]);
    }

    public function store(StoreProductsRequest $request)
    {
        $businessId = (int) $request->user()->business_id;
        app(SubscriptionLimitService::class)->assertCanCreateProducts($businessId, count($request->validated('products')));
        $storedImages = [];

        try {
            $products = DB::transaction(function () use ($request, $businessId, &$storedImages) {
                return collect($request->validated('products'))
                    ->map(function (array $data, $index) use ($request, $businessId, &$storedImages) {
                    $unit = Unit::where('business_id', $businessId)->findOrFail($data['unit_id']);
                    $image = $request->file("products.{$index}.product_image");
                    $imagePath = $image?->store('products', 'public');
                    if ($imagePath) {
                        $storedImages[] = $imagePath;
                    }
                    $product = Product::create([
                        'business_id' => $businessId,
                        'category_id' => $data['category_id'],
                        'unit_id' => $unit->id,
                        'name' => $data['product_name'],
                        'unit' => $unit->short_code,
                        'image' => $imagePath,
                        'batch_number' => $data['batch_number'] ?? null,
                        'manufacturing_date' => $data['manufacturing_date'] ?? null,
                        'expiry_date' => $data['expiry_date'] ?? null,
                        'expiry_alert_days' => $data['expiry_alert_days'] ?? null,
                        'has_batch_tracking' => (bool) ($data['has_batch_tracking'] ?? false),
                        'brand' => $data['brand'] ?? null,
                        'manufacturer' => $data['manufacturer'] ?? null,
                        'warehouse_location' => $data['warehouse_location'] ?? null,
                        'description' => $data['description'] ?? null,
                        'status' => $data['status'],
                        // Commercial values are managed by purchase, inventory,
                        // and pricing workflows after master-data creation.
                        'purchase_cost' => 0,
                        'wholesale_price' => 0,
                        'retail_price' => 0,
                        'opening_stock' => 0,
                        'current_stock' => 0,
                        'stock_quantity' => 0,
                        'minimum_order_quantity' => 1,
                        'low_stock_alert_qty' => 10,
                        'created_by' => $request->user()->id,
                        'added_date' => now(),
                    ]);

                    $product = $this->barcodes->assign($product);
                    Inventory::create([
                        'business_id' => $businessId,
                        'product_id' => $product->id,
                        'available_stock' => 0,
                        'low_stock_alert' => $product->low_stock_alert_qty,
                    ]);
                    AuditLog::create([
                        'business_id' => $businessId,
                        'module' => 'Products',
                        'action' => 'product_created',
                        'description' => 'Created product '.$product->name,
                        'record_type' => 'Product',
                        'record_id' => $product->id,
                        'new_values' => $product->only(['name', 'category_id', 'unit_id', 'status']),
                    ]);

                    return $product;
                    })
                    ->values();
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedImages);

            throw $exception;
        }

        return redirect()->route('business.products.index')
            ->with('success', $products->count() === 1 ? 'Product created.' : $products->count().' products created.');
    }

    public function show(int $product)
    {
        $product = Product::withTrashed()
            ->where('business_id', auth()->user()->business_id)
            ->findOrFail($product);
        $this->authorizeBusiness($product->business_id);
        return view('business.products.show', [
            'product' => $product->load(['category', 'inventory', 'movements']),
            'latestInventoryMovement' => $product->inventoryMovements()->latest('movement_date')->first(),
        ]);
    }

    public function edit(Product $product)
    {
        $this->authorizeBusiness($product->business_id);
        return view('business.products.create', [
            'product' => $product,
            'categories' => Category::where('business_id', auth()->user()->business_id)->where('type', 'Product')
                ->where(fn ($query) => $query->where('status', 'Active')->orWhere('id', $product->category_id))->orderBy('name')->get(),
            'units' => Unit::where('business_id', auth()->user()->business_id)
                ->where(fn ($query) => $query->where('status', 'Active')->orWhere('id', $product->unit_id))
                ->orderBy('unit_name')
                ->get(),
        ]);
    }

    public function update(StoreOrUpdateProductRequest $request, Product $product)
    {
        $this->authorizeBusiness($product->business_id);
        $data = $request->validated();
        $data['name'] = $data['product_name'] ?? $data['name'];
        $data['has_batch_tracking'] = $request->boolean('has_batch_tracking');
        $data['unit'] = Unit::where('business_id', auth()->user()->business_id)->findOrFail($data['unit_id'])->short_code;
        unset($data['product_name'], $data['category'], $data['product_image'], $data['image']);
        $image = $request->file('product_image') ?: $request->file('image');
        if ($image) {
            $data['image'] = $image->store('products', 'public');
        }
        $product->update($data);
        return redirect()->route('business.products.show', $product)->with('success', 'Product updated.');
    }

    public function destroy(int $product)
    {
        $product = Product::withTrashed()
            ->where('business_id', auth()->user()->business_id)
            ->findOrFail($product);
        $this->authorizeBusiness($product->business_id);
        $hasHistory = OrderItem::where('product_id', $product->id)->exists()
            || StockMovement::where('product_id', $product->id)->where('reason', '!=', 'Opening Stock')->exists();

        if ($hasHistory) {
            if (! $product->trashed()) {
                $product->update(['status' => 'Inactive']);
                $product->delete();

                return redirect()->route('business.products.index')->with('success', 'This product has historical transactions and cannot be permanently deleted. It has been archived instead.');
            }

            return back()->with('error', 'This product has historical transactions and cannot be deleted.');
        }

        $product->forceDelete();

        return redirect()->route('business.products.index')->with('success', 'Product deleted.');
    }

    private function authorizeBusiness(int $businessId): void
    {
        abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->business_id === $businessId, 403);
    }

    public function updateLowStockAlert(Request $request, Product $product)
    {
        $this->authorizeBusiness($product->business_id);
        $data = $request->validate(['low_stock_alert_qty' => ['required', 'integer', 'min:0']]);
        $product->update($data);
        $product->inventory()->updateOrCreate(
            ['business_id' => $product->business_id],
            ['available_stock' => $product->stock_quantity, 'low_stock_alert' => $data['low_stock_alert_qty']]
        );

        return back()->with('success', 'Low stock alert quantity updated.');
    }

    public function restore(int $product)
    {
        $record = Product::withTrashed()->where('business_id', auth()->user()->business_id)->findOrFail($product);
        $record->restore();
        $record->update(['status' => 'Active']);

        return back()->with('success', 'Record restored successfully.');
    }

    public function archive(Product $product)
    {
        $this->authorizeBusiness($product->business_id);
        $product->update(['status' => 'Inactive']);
        $product->delete();

        return back()->with('success', 'Record archived successfully.');
    }

    public function bulk()
    {
        return view('business.products.bulk', [
            'categories' => Category::where('business_id', auth()->user()->business_id)->orWhereNull('business_id')->orderBy('name')->get(),
        ]);
    }

    public function bulkStore(BulkStoreProductRequest $request)
    {
        $rows = $request->validated('products');

        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $categoryId = Category::firstOrCreate(['business_id' => auth()->user()->business_id, 'name' => $row['category']], ['type' => 'Product'])->id;
                $product = Product::create([
                    'business_id' => auth()->user()->business_id,
                    'category_id' => $categoryId,
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'purchase_cost' => $row['purchase_cost'],
                    'wholesale_price' => $row['wholesale_price'],
                    'retail_price' => $row['retail_price'] ?? 0,
                    'opening_stock' => 0,
                    'current_stock' => 0,
                    'stock_quantity' => 0,
                    'batch_number' => $row['batch_number'] ?? null,
                    'expiry_date' => filled($row['expiry_date'] ?? null) ? $row['expiry_date'] : null,
                    'has_batch_tracking' => (bool) ($row['has_batch_tracking'] ?? false),
                    'minimum_order_quantity' => 1,
                    'low_stock_alert_qty' => $row['low_stock_alert_qty'] ?? 10,
                    'status' => 'Active',
                    'created_by' => auth()->id(),
                    'added_date' => now(),
                ]);
                $product = $this->barcodes->assign($product);

                Inventory::create([
                    'business_id' => auth()->user()->business_id,
                    'product_id' => $product->id,
                    'available_stock' => $product->stock_quantity,
                    'low_stock_alert' => $product->low_stock_alert_qty,
                ]);
            }
        });

        return redirect()->route('business.products.index')->with('success', 'Bulk products saved.');
    }

    public function csvTemplate()
    {
        return response("Product Name,Category,Unit,Purchase Cost,Wholesale Price,Retail Price,Batch Number,Expiry Date (Optional),Low Stock Alert\n", 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=tradeflow-products-template.csv',
        ]);
    }

    public function export()
    {
        $products = Product::with('category')->where('business_id', auth()->user()->business_id)->get();
        $csv = "Name,Category,Unit,Purchase Cost,Wholesale Price,Retail Price,Stock,Barcode,Status\n";
        foreach ($products as $product) {
            $csv .= implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', [
                $product->name, $product->category?->name, $product->unit, $product->purchase_cost, $product->wholesale_price, $product->retail_price, $product->stock_quantity, $product->barcode, $product->status,
            ]))."\n";
        }

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename=products.csv']);
    }
}
