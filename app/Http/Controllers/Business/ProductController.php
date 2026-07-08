<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $query = Product::with('category')->where('business_id', auth()->user()->business_id);
        if (request('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.request('search').'%')->orWhere('sku', 'like', '%'.request('search').'%')->orWhere('barcode', 'like', '%'.request('search').'%'));
        }
        if (request('status')) {
            $query->where('status', request('status'));
        }

        return view('business.products.index', ['products' => $query->latest()->paginate(15)->withQueryString()]);
    }

    public function create()
    {
        return view('business.products.create', ['categories' => Category::where('business_id', auth()->user()->business_id)->orWhereNull('business_id')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_name' => ['nullable', 'max:255'],
            'name' => ['nullable', 'max:255'],
            'category' => ['nullable', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'product_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'sku' => ['nullable', 'max:100'],
            'barcode' => ['nullable', 'max:100'],
            'batch_number' => ['nullable', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['required', 'numeric', 'min:0'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_quantity' => ['required', 'integer', 'min:1'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_alert_qty' => ['nullable', 'integer', 'min:0'],
            'unit' => ['required', 'in:Piece,Carton,KG,Liter'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        $data['business_id'] = auth()->user()->business_id;
        $data['name'] = $data['product_name'] ?? $data['name'];
        $data['low_stock_alert_qty'] = $data['low_stock_alert_qty'] ?? 10;
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
        $data = $request->validate([
            'product_name' => ['nullable', 'max:255'],
            'name' => ['nullable', 'max:255'],
            'category' => ['nullable', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['required', 'numeric', 'min:0'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_quantity' => ['required', 'integer', 'min:1'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_alert_qty' => ['nullable', 'integer', 'min:0'],
            'unit' => ['required', 'in:Piece,Carton,KG,Liter'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);
        $data['name'] = $data['product_name'] ?? $data['name'];
        $data['low_stock_alert_qty'] = $data['low_stock_alert_qty'] ?? 10;
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

        return redirect()->route('business.products.show', $product)->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $this->authorizeBusiness($product->business_id);
        $product->delete();

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
}
