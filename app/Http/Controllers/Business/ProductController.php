<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        $query = Product::with(['category', 'creator'])->where('business_id', auth()->user()->business_id);
        if (request('archived')) {
            $query->onlyTrashed();
        }
        if (request('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.request('search').'%')->orWhere('sku', 'like', '%'.request('search').'%')->orWhere('barcode', 'like', '%'.request('search').'%'));
        }
        if (request('status')) {
            $query->where('status', request('status'));
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
            'categories' => Category::where('business_id', auth()->user()->business_id)->orWhereNull('business_id')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('business.products.create', ['categories' => Category::where('business_id', auth()->user()->business_id)->orWhereNull('business_id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_name' => ['required_without:name', 'max:255'],
            'name' => ['required_without:product_name', 'max:255'],
            'category' => ['required_without:category_id', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'product_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sku' => ['nullable', 'max:100'],
            'barcode' => ['nullable', 'max:100', Rule::unique('products', 'barcode')->where('business_id', auth()->user()->business_id)->whereNull('deleted_at')],
            'batch_number' => ['nullable', 'max:100'],
            'manufacturing_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'expiry_alert_days' => ['nullable', 'integer', 'min:0'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['required', 'numeric', 'min:0'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'opening_stock' => ['nullable', 'integer', 'min:0'],
            'minimum_order_quantity' => ['nullable', 'integer', 'min:1'],
            'stock_quantity' => ['required_without:opening_stock', 'integer', 'min:0'],
            'low_stock_alert_qty' => ['nullable', 'integer', 'min:0'],
            'unit' => ['required', 'in:Piece,Carton,KG,Liter'],
            'status' => ['required', 'in:Active,Inactive'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'max:100'],
            'manufacturer' => ['nullable', 'max:100'],
            'warehouse_location' => ['nullable', 'max:150'],
            'has_batch_tracking' => ['nullable', 'boolean'],
        ]);

        $data['business_id'] = auth()->user()->business_id;
        $data['name'] = $data['product_name'] ?? $data['name'];
        $data['opening_stock'] = $data['opening_stock'] ?? $data['stock_quantity'] ?? 0;
        $data['stock_quantity'] = $data['opening_stock'];
        $data['current_stock'] = $data['stock_quantity'];
        $data['minimum_order_quantity'] = $data['minimum_order_quantity'] ?? 1;
        $data['low_stock_alert_qty'] = $data['low_stock_alert_qty'] ?? 10;
        $data['has_batch_tracking'] = $request->boolean('has_batch_tracking');
        $data['created_by'] = auth()->id();
        $data['added_date'] = now();
        if (!empty($data['category']) && empty($data['category_id'])) {
            $data['category_id'] = Category::firstOrCreate(['business_id' => $data['business_id'], 'name' => $data['category']], ['type' => 'Product'])->id;
        }
        unset($data['product_name'], $data['category'], $data['product_image']);
        $image = $request->file('product_image') ?: $request->file('image');
        if ($image) {
            $data['image'] = $image->store('products', 'public');
        }

        $product = Product::create($data);
        Inventory::create([
            'business_id' => $data['business_id'],
            'product_id' => $product->id,
            'available_stock' => $product->stock_quantity,
            'low_stock_alert' => $product->low_stock_alert_qty,
        ]);
        StockMovement::create(['business_id' => $data['business_id'], 'product_id' => $product->id, 'type' => 'added', 'quantity' => $product->stock_quantity, 'reason' => 'Opening Stock', 'note' => 'Opening Stock', 'user_id' => auth()->id(), 'created_by' => auth()->id()]);
        InventoryMovement::create(['business_id' => $data['business_id'], 'product_id' => $product->id, 'type' => 'ADD_STOCK', 'quantity' => $product->stock_quantity, 'previous_stock' => 0, 'new_stock' => $product->stock_quantity, 'note' => 'Opening Stock', 'created_by' => auth()->id(), 'movement_date' => now()]);

        return redirect()->route('business.products.show', $product)->with('success', 'Product created.');
    }

    public function show(Product $product)
    {
        $this->authorizeBusiness($product->business_id);
        return view('business.products.show', ['product' => $product->load(['category', 'inventory', 'movements'])]);
    }

    public function edit(Product $product)
    {
        $this->authorizeBusiness($product->business_id);
        return view('business.products.create', ['product' => $product, 'categories' => Category::where('business_id', auth()->user()->business_id)->orWhereNull('business_id')->get()]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeBusiness($product->business_id);
        $previousStock = (int) $product->stock_quantity;
        $data = $request->validate([
            'product_name' => ['required_without:name', 'max:255'],
            'name' => ['required_without:product_name', 'max:255'],
            'category' => ['required_without:category_id', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'sku' => ['nullable', 'max:100'],
            'barcode' => ['nullable', 'max:100', Rule::unique('products', 'barcode')->where('business_id', auth()->user()->business_id)->whereNull('deleted_at')->ignore($product->id)],
            'batch_number' => ['nullable', 'max:100'],
            'manufacturing_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'expiry_alert_days' => ['nullable', 'integer', 'min:0'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['required', 'numeric', 'min:0'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'opening_stock' => ['nullable', 'integer', 'min:0'],
            'minimum_order_quantity' => ['nullable', 'integer', 'min:1'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_alert_qty' => ['nullable', 'integer', 'min:0'],
            'unit' => ['required', 'in:Piece,Carton,KG,Liter'],
            'status' => ['required', 'in:Active,Inactive'],
            'description' => ['nullable', 'string'],
            'brand' => ['nullable', 'max:100'],
            'manufacturer' => ['nullable', 'max:100'],
            'warehouse_location' => ['nullable', 'max:150'],
            'has_batch_tracking' => ['nullable', 'boolean'],
        ]);
        $data['name'] = $data['product_name'] ?? $data['name'];
        $data['current_stock'] = $data['stock_quantity'];
        $data['minimum_order_quantity'] = $data['minimum_order_quantity'] ?? 1;
        $data['low_stock_alert_qty'] = $data['low_stock_alert_qty'] ?? 10;
        $data['has_batch_tracking'] = $request->boolean('has_batch_tracking');
        if (!empty($data['category']) && empty($data['category_id'])) {
            $data['category_id'] = Category::firstOrCreate(['business_id' => auth()->user()->business_id, 'name' => $data['category']], ['type' => 'Product'])->id;
        }
        unset($data['product_name'], $data['category']);
        $product->update($data);
        $inventory = $product->inventory()->firstOrCreate(
            ['business_id' => auth()->user()->business_id],
            [
                'available_stock' => $product->stock_quantity,
                'low_stock_alert' => $product->low_stock_alert_qty,
            ]
        );
        $inventory->update(['available_stock' => $product->stock_quantity]);

        $newStock = (int) $product->stock_quantity;
        if ($newStock !== $previousStock) {
            $change = abs($newStock - $previousStock);
            InventoryMovement::create([
                'business_id' => auth()->user()->business_id,
                'product_id' => $product->id,
                'type' => 'ADJUSTMENT',
                'quantity' => $change,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'note' => 'Stock updated from product edit',
                'created_by' => auth()->id(),
                'movement_date' => now(),
            ]);
            StockMovement::create([
                'business_id' => auth()->user()->business_id,
                'product_id' => $product->id,
                'type' => 'adjustment',
                'quantity' => $change,
                'reason' => 'Product stock edit',
                'note' => 'Stock changed from '.$previousStock.' to '.$newStock,
                'user_id' => auth()->id(),
                'created_by' => auth()->id(),
            ]);
        }

        return redirect()->route('business.products.show', $product)->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $this->authorizeBusiness($product->business_id);
        $hasHistory = OrderItem::where('product_id', $product->id)->exists()
            || StockMovement::where('product_id', $product->id)->where('reason', '!=', 'Opening Stock')->exists();

        if ($hasHistory) {
            $product->update(['status' => 'Inactive']);
            $product->delete();

            return redirect()->route('business.products.index')->with('success', 'This product has historical transactions and cannot be permanently deleted. It has been archived instead.');
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

        return back()->with('success', 'Product restored.');
    }

    public function archive(Product $product)
    {
        $this->authorizeBusiness($product->business_id);
        $product->update(['status' => 'Inactive']);
        $product->delete();

        return back()->with('success', 'Product archived.');
    }

    public function bulk()
    {
        return view('business.products.bulk', [
            'categories' => Category::where('business_id', auth()->user()->business_id)->orWhereNull('business_id')->orderBy('name')->get(),
        ]);
    }

    public function bulkStore(Request $request)
    {
        $rows = $request->validate([
            'products' => ['required', 'array', 'min:1'],
            'products.*.name' => ['required', 'max:255'],
            'products.*.category' => ['required', 'max:255'],
            'products.*.unit' => ['required', 'in:Piece,Carton,KG,Liter'],
            'products.*.purchase_cost' => ['required', 'numeric', 'min:0'],
            'products.*.wholesale_price' => ['required', 'numeric', 'min:0'],
            'products.*.retail_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.opening_stock' => ['required', 'integer', 'min:0'],
            'products.*.sku' => ['nullable', 'max:100'],
            'products.*.barcode' => ['nullable', 'max:100'],
            'products.*.batch_number' => ['nullable', 'max:100'],
            'products.*.expiry_date' => ['nullable', 'date'],
            'products.*.low_stock_alert_qty' => ['nullable', 'integer', 'min:0'],
        ])['products'];

        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                if (!empty($row['barcode']) && Product::where('business_id', auth()->user()->business_id)->where('barcode', $row['barcode'])->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(["products.$index.barcode" => 'Row '.($index + 1).': barcode already exists for this business.']);
                }

                $categoryId = Category::firstOrCreate(['business_id' => auth()->user()->business_id, 'name' => $row['category']], ['type' => 'Product'])->id;
                $product = Product::create([
                    'business_id' => auth()->user()->business_id,
                    'category_id' => $categoryId,
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'purchase_cost' => $row['purchase_cost'],
                    'wholesale_price' => $row['wholesale_price'],
                    'retail_price' => $row['retail_price'] ?? 0,
                    'opening_stock' => $row['opening_stock'],
                    'current_stock' => $row['opening_stock'],
                    'stock_quantity' => $row['opening_stock'],
                    'sku' => $row['sku'] ?? null,
                    'barcode' => $row['barcode'] ?? null,
                    'batch_number' => $row['batch_number'] ?? null,
                    'expiry_date' => $row['expiry_date'] ?? null,
                    'minimum_order_quantity' => 1,
                    'low_stock_alert_qty' => $row['low_stock_alert_qty'] ?? 10,
                    'status' => 'Active',
                    'created_by' => auth()->id(),
                    'added_date' => now(),
                ]);

                Inventory::create([
                    'business_id' => auth()->user()->business_id,
                    'product_id' => $product->id,
                    'available_stock' => $product->stock_quantity,
                    'low_stock_alert' => $product->low_stock_alert_qty,
                ]);
                StockMovement::create(['business_id' => auth()->user()->business_id, 'product_id' => $product->id, 'type' => 'added', 'quantity' => $product->stock_quantity, 'reason' => 'Opening Stock', 'note' => 'Bulk opening stock', 'user_id' => auth()->id(), 'created_by' => auth()->id()]);
                InventoryMovement::create(['business_id' => auth()->user()->business_id, 'product_id' => $product->id, 'type' => 'ADD_STOCK', 'quantity' => $product->stock_quantity, 'previous_stock' => 0, 'new_stock' => $product->stock_quantity, 'note' => 'Bulk opening stock', 'created_by' => auth()->id(), 'movement_date' => now()]);
            }
        });

        return redirect()->route('business.products.index')->with('success', 'Bulk products saved.');
    }

    public function csvTemplate()
    {
        return response("Product Name,Category,Unit,Purchase Cost,Wholesale Price,Retail Price,Opening Stock,SKU,Barcode,Batch Number,Expiry Date,Low Stock Alert\n", 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=tradeflow-products-template.csv',
        ]);
    }

    public function export()
    {
        $products = Product::with('category')->where('business_id', auth()->user()->business_id)->get();
        $csv = "Name,Category,Unit,Purchase Cost,Wholesale Price,Retail Price,Stock,SKU,Barcode,Status\n";
        foreach ($products as $product) {
            $csv .= implode(',', array_map(fn ($value) => '"'.str_replace('"', '""', (string) $value).'"', [
                $product->name, $product->category?->name, $product->unit, $product->purchase_cost, $product->wholesale_price, $product->retail_price, $product->stock_quantity, $product->sku, $product->barcode, $product->status,
            ]))."\n";
        }

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename=products.csv']);
    }
}
