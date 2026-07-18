<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesQuotation;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesQuotationController extends Controller
{
    public function __construct(private DocumentNumberService $numbers) {}

    public function index()
    {
        $businessId = $this->businessId();
        return view('business.sales.quotations.index', [
            'quotations' => SalesQuotation::with('customer')->where('business_id', $businessId)->latest('quotation_date')->paginate(20),
        ]);
    }

    public function create()
    {
        $businessId = $this->businessId();
        return view('business.sales.quotations.create', [
            'customers' => Customer::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get(),
            'products' => Product::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer'], 'quotation_date' => ['required', 'date'], 'valid_until' => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'status' => ['required', 'in:Draft,Sent,Accepted,Expired'], 'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'integer'], 'items.*.quantity' => ['required', 'integer', 'min:1'], 'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'discount_amount' => ['nullable', 'integer', 'min:0'], 'tax_amount' => ['nullable', 'integer', 'min:0'],
        ]);
        $businessId = $this->businessId();

        DB::transaction(function () use ($data, $businessId) {
            if (!empty($data['customer_id'])) Customer::where('business_id', $businessId)->findOrFail($data['customer_id']);
            $items = collect($data['items'])->groupBy('product_id')->map(fn ($lines, $productId) => ['product_id' => (int) $productId, 'quantity' => $lines->sum('quantity'), 'unit_price' => (float) $lines->last()['unit_price']])->values();
            $subtotal = 0; $prepared = [];
            foreach ($items as $item) {
                $product = Product::where('business_id', $businessId)->findOrFail($item['product_id']);
                $lineTotal = round($item['quantity'] * $item['unit_price'], 2); $subtotal += $lineTotal;
                $prepared[] = compact('product', 'lineTotal') + $item;
            }
            $discount = min($subtotal, (float) ($data['discount_amount'] ?? 0));
            $tax = (float) ($data['tax_amount'] ?? 0);
            $quote = SalesQuotation::create([
                'business_id' => $businessId, 'customer_id' => $data['customer_id'] ?? null, 'created_by' => auth()->id(),
                'quotation_number' => $this->numbers->next('quotation'),
                'quotation_date' => $data['quotation_date'], 'valid_until' => $data['valid_until'] ?? null, 'status' => $data['status'],
                'subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax, 'grand_total' => round($subtotal - $discount + $tax, 2), 'notes' => $data['notes'] ?? null,
            ]);
            foreach ($prepared as $item) $quote->items()->create(['product_id' => $item['product_id'], 'product_name_snapshot' => $item['product']->name, 'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'line_total' => $item['lineTotal']]);
        });

        return redirect()->route('business.sales.quotations.index')->with('success', 'Sales quotation saved. Inventory and accounting are unchanged until a sale is created.');
    }

    private function businessId(): int { return (int) auth()->user()->business_id; }
}
