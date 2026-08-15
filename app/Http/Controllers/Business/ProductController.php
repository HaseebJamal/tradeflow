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
use App\Services\CompanyPermissionService;
use App\Services\ProductSellingPricePolicy;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductController extends Controller
{
    public function __construct(private BarcodeService $barcodes, private ProductSellingPricePolicy $pricing)
    {
    }

    public function index(Request $request)
    {
        $permissions = app(CompanyPermissionService::class);
        $canUseCategories = $permissions->allowsUser($request->user(), 'categories.view');
        $query = Product::with(['category', 'creator'])->where('business_id', auth()->user()->business_id);
        if (request('status') === 'Archived' || request('archived')) {
            $query->onlyTrashed();
        } elseif (in_array(request('status'), ['Active', 'Inactive'], true)) {
            $query->where('status', request('status'));
        }
        if (request('product_id')) {
            $query->where('id', request('product_id'));
        }
        if ($canUseCategories && request('category_id')) {
            $query->where('category_id', request('category_id'));
        }
        if (request('batch_number')) {
            $query->where('batch_number', 'like', '%' . request('batch_number') . '%');
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
            'products' => $query->latest()->paginate(10)->withQueryString(),

            'categories' => $canUseCategories ? Category::where('business_id', auth()->user()->business_id)
                ->where('type', 'Product')
                ->orderBy('name')
                ->get() : collect(),
            'canUseCategories' => $canUseCategories,

            'productOptions' => Product::where('business_id', auth()->user()->business_id)
                ->select('id', 'name', 'barcode')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function bulkPricing(Request $request)
    {
        abort_unless(app(CompanyPermissionService::class)->allowsUser($request->user(), 'products.edit'), 403);

        $businessId = (int) $request->user()->business_id;
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'pricing_status' => ['nullable', 'in:valid,needs,attention'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);
        $cost = 'COALESCE(latest_purchase_price, average_purchase_price, purchase_cost, 0)';
        $query = Product::with(['category', 'unitRecord', 'acceptedGoodsReceiptItems:id,product_id'])
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where('name', 'like', "%{$value}%"))
            ->when($filters['category_id'] ?? null, fn ($q, $value) => $q->where('category_id', $value))
            ->when($filters['unit_id'] ?? null, fn ($q, $value) => $q->where('unit_id', $value));

        match ($filters['pricing_status'] ?? null) {
            'needs' => $query->where(fn ($q) => $q->where('retail_price', '<=', 0)->orWhere('wholesale_price', '<=', 0)),
            'attention' => $query->whereRaw("{$cost} > 0")->whereRaw("(retail_price <= {$cost} OR wholesale_price <= {$cost})"),
            'valid' => $query->whereRaw("{$cost} > 0")->whereRaw("retail_price > {$cost} AND wholesale_price > {$cost}"),
            default => null,
        };

        return view('business.products.bulk-pricing', [
            'products' => $query->orderBy('name')->paginate($filters['per_page'] ?? 10)->withQueryString(),
            'categories' => Category::where('business_id', $businessId)->where('type', 'Product')->orderBy('name')->get(),
            'units' => Unit::where('business_id', $businessId)->orderBy('unit_name')->get(),
            'filters' => $filters,
        ]);
    }

    public function updateBulkPricing(Request $request)
    {
        abort_unless(app(CompanyPermissionService::class)->allowsUser($request->user(), 'products.edit'), 403);
        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.id' => ['required', 'integer'],
            'rows.*.retail_price' => ['nullable', 'numeric', 'min:0'],
            'rows.*.wholesale_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        $businessId = (int) $request->user()->business_id;

        DB::transaction(function () use ($data, $businessId, $request) {
            $products = Product::with(['acceptedGoodsReceiptItems:id,product_id'])
                ->where('business_id', $businessId)->whereIn('id', collect($data['rows'])->pluck('id'))
                ->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== count($data['rows'])) abort(403);

            foreach ($data['rows'] as $row) {
                $product = $products->get((int) $row['id']);
                $updates = [];
                if (array_key_exists('retail_price', $row) && $row['retail_price'] !== null) $updates['retail_price'] = round((float) $row['retail_price'], 2);
                if (array_key_exists('wholesale_price', $row) && $row['wholesale_price'] !== null) $updates['wholesale_price'] = round((float) $row['wholesale_price'], 2);
                $updates = array_filter($updates, fn ($value, $field) => $value !== (float) $product->{$field}, ARRAY_FILTER_USE_BOTH);
                if (! $updates) continue;

                $newRetail = $updates['retail_price'] ?? (float) $product->retail_price;
                $newWholesale = $updates['wholesale_price'] ?? (float) $product->wholesale_price;

                $violations = $this->pricing->violations(['retail_price' => $newRetail, 'wholesale_price' => $newWholesale], $this->pricing->purchasePrice($product));
                if ($violations) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'pricing.'.$product->id => $product->name.': '.implode(' ', $violations),
                    ]);
                }
                $old = ['retail_price' => (float) $product->retail_price, 'wholesale_price' => (float) $product->wholesale_price];
                $product->update($updates);
                AuditLog::create([
                    'business_id' => $businessId, 'user_id' => $request->user()->id, 'user_name' => $request->user()->name,
                    'role' => $request->user()->role, 'module' => 'Products', 'action' => 'bulk_pricing_updated',
                    'description' => 'Bulk pricing updated for '.$product->name, 'record_type' => Product::class,
                    'record_id' => $product->id, 'old_values' => $old,
                    'new_values' => ['retail_price' => $newRetail, 'wholesale_price' => $newWholesale], 'occurred_at' => now(),
                ]);
            }
        });

        return redirect()->route('business.products.bulk-pricing', $request->query())->with('success', 'Product pricing updated successfully.');
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
        $permissions = app(CompanyPermissionService::class);
        $canUseCategories = $permissions->allowsUser(auth()->user(), 'categories.view');
        $canUseUnits = $permissions->allowsUser(auth()->user(), 'units.view');
        return view('business.products.create', [
            'categories' => $canUseCategories ? Category::where('business_id', auth()->user()->business_id)->where('type', 'Product')->where('status', 'Active')->orderBy('name')->get() : collect(),
            'units' => $canUseUnits ? Unit::where('business_id', auth()->user()->business_id)->where('status', 'Active')->orderBy('unit_name')->get() : collect(),
            'canUseCategories' => $canUseCategories,
            'canUseUnits' => $canUseUnits,
        ]);
    }

    public function store(StoreProductsRequest $request)
    {
        $businessId = (int) $request->user()->business_id;
        $rows = $request->validated('products');
        $existingTokens = Product::query()
            ->where('business_id', $businessId)
            ->whereIn('submission_token', collect($rows)->pluck('submission_token')->all())
            ->pluck('submission_token')
            ->all();
        $newProductCount = collect($rows)
            ->whereNotIn('submission_token', $existingTokens)
            ->count();
        app(SubscriptionLimitService::class)->assertCanCreateProducts($businessId, $newProductCount);
        $storedImages = [];

        try {
            $products = DB::transaction(function () use ($request, $rows, $businessId, &$storedImages) {
                return collect($rows)
                    ->map(function (array $data, $index) use ($request, $businessId, &$storedImages) {
                        $existing = Product::query()
                            ->where('business_id', $businessId)
                            ->where('submission_token', $data['submission_token'])
                            ->first();
                        if ($existing) {
                            return $existing;
                        }

                        $unit = isset($data['unit_id'])
                            ? Unit::where('business_id', $businessId)->findOrFail($data['unit_id'])
                            : null;
                        $image = $request->file("products.{$index}.product_image");
                        $imagePath = $image?->store('products', 'public');
                        $productAttributes = [
                            'business_id' => $businessId,
                            'submission_token' => $data['submission_token'],
                            'category_id' => $data['category_id'] ?? null,
                            'unit_id' => $unit?->id,
                            'name' => $data['product_name'],
                            'unit' => $unit?->short_code,
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
                            // Purchase cost is calculated only from accepted goods
                            // receipts. Product setup controls selling prices only.
                            'purchase_cost' => 0,
                            'wholesale_price' => round((float) ($data['wholesale_price'] ?? 0), 2),
                            'retail_price' => round((float) ($data['retail_price'] ?? 0), 2),
                            'opening_stock' => 0,
                            'current_stock' => 0,
                            'stock_quantity' => 0,
                            'minimum_order_quantity' => 1,
                            'low_stock_alert_qty' => 10,
                            'created_by' => $request->user()->id,
                            'added_date' => now(),
                        ];

                        try {
                            $product = Product::firstOrCreate([
                                'business_id' => $businessId,
                                'submission_token' => $data['submission_token'],
                            ], $productAttributes);
                        } catch (QueryException $exception) {
                            // A concurrent identical request can lose the
                            // unique-token race. Treat it as the same save,
                            // not as a server error or a second product.
                            $product = Product::query()
                                ->where('business_id', $businessId)
                                ->where('submission_token', $data['submission_token'])
                                ->lockForUpdate()
                                ->first();

                            if (! $product) {
                                throw $exception;
                            }
                        }

                        if (! $product->wasRecentlyCreated) {
                            if ($imagePath) {
                                Storage::disk('public')->delete($imagePath);
                            }

                            return $product;
                        }

                        if ($imagePath) {
                            $storedImages[] = $imagePath;
                        }

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
                            'description' => 'Created product ' . $product->name,
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

        $message = 'Product(s) created successfully.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'products' => $products->map(fn(Product $product) => ['id' => $product->id, 'name' => $product->name])->values(),
            ], 201);
        }

        return redirect()->route('business.products.index')
            ->with('success', $products->count() === 1 ? 'Product created.' : $products->count() . ' products created.');
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
        $permissions = app(CompanyPermissionService::class);
        $canUseCategories = $permissions->allowsUser(auth()->user(), 'categories.view');
        $canUseUnits = $permissions->allowsUser(auth()->user(), 'units.view');
        return view('business.products.create', [
            'product' => $product,
            'categories' => $canUseCategories ? Category::where('business_id', auth()->user()->business_id)->where('type', 'Product')
                ->where(fn($query) => $query->where('status', 'Active')->orWhere('id', $product->category_id))->orderBy('name')->get() : collect(),
            'units' => $canUseUnits ? Unit::where('business_id', auth()->user()->business_id)
                ->where(fn($query) => $query->where('status', 'Active')->orWhere('id', $product->unit_id))
                ->orderBy('unit_name')
                ->get() : collect(),
            'canUseCategories' => $canUseCategories,
            'canUseUnits' => $canUseUnits,
        ]);
    }

    public function update(StoreOrUpdateProductRequest $request, Product $product)
    {
        $this->authorizeBusiness($product->business_id);
        $data = $request->validated();
        // A product's identity is established on creation.  Keep it immutable
        // here as well as in the edit UI so a crafted request cannot rename it.
        $data['name'] = $product->name;
        $data['has_batch_tracking'] = $request->boolean('has_batch_tracking');
        $permissions = app(CompanyPermissionService::class);
        if ($permissions->allowsUser($request->user(), 'units.view')) {
            $data['unit'] = Unit::where('business_id', auth()->user()->business_id)->findOrFail($data['unit_id'])->short_code;
        } else {
            unset($data['unit_id']);
        }
        if (! $permissions->allowsUser($request->user(), 'categories.view')) {
            unset($data['category_id']);
        }
        unset($data['product_name'], $data['category'], $data['product_image'], $data['image']);
        $image = $request->file('product_image') ?: $request->file('image');
        if ($image) {
            $data['image'] = $image->store('products', 'public');
        }
        $product->update($data);
        return redirect()->route('business.products.index')->with('success', 'Product updated successfully.');
    }

    /** Update the existing binary catalogue availability without requiring a full product edit. */
    public function updateStatus(Request $request, Product $product)
    {
        $this->authorizeBusiness($product->business_id);
        $data = $request->validate(['status' => ['required', 'in:Active,Inactive']]);
        $oldStatus = $product->status;
        $product->update(['status' => $data['status']]);

        AuditLog::create([
            'business_id' => $product->business_id,
            'module' => 'Products',
            'action' => 'product_'.strtolower($data['status']),
            'description' => ucfirst($data['status']).' product '.$product->name,
            'record_type' => Product::class,
            'record_id' => $product->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $data['status']],
        ]);

        $message = 'Product '.($data['status'] === 'Active' ? 'activated' : 'deactivated').' successfully.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'status' => $product->status]);
        }

        return back()->with('success', $message);
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
            if (!$product->trashed()) {
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
            $csv .= implode(',', array_map(fn($value) => '"' . str_replace('"', '""', (string) $value) . '"', [
                $product->name,
                $product->category?->name,
                $product->unit,
                $product->purchase_cost,
                $product->wholesale_price,
                $product->retail_price,
                $product->stock_quantity,
                $product->barcode,
                $product->status,
            ])) . "\n";
        }

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename=products.csv']);
    }
}
