<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesQuotation;
use App\Services\DocumentNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesQuotationController extends Controller
{
    public function __construct(private DocumentNumberService $numbers) {}

    public function index()
    {
        $businessId = $this->businessId();
        return view('business.sales.quotations.index', [
            'quotations' => SalesQuotation::with('customer')->where('business_id', $businessId)->latest('quotation_date')->paginate(12),
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
            'items.*.discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'items.*.discount_value' => ['nullable', 'integer', 'min:0'],
            'items.*.tax_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'items.*.tax_value' => ['nullable', 'integer', 'min:0'],
        ]);
        $businessId = $this->businessId();

        DB::transaction(function () use ($data, $businessId) {
            if (!empty($data['customer_id'])) Customer::where('business_id', $businessId)->findOrFail($data['customer_id']);
            $items = collect($data['items'])->groupBy('product_id')->map(fn ($lines, $productId) => [
                'product_id' => (int) $productId,
                'quantity' => (int) $lines->sum('quantity'),
                'unit_price' => (float) $lines->last()['unit_price'],
                'discount_type' => $lines->last()['discount_type'] ?? 'fixed',
                'discount_value' => (int) ($lines->last()['discount_value'] ?? 0),
                'tax_type' => $lines->last()['tax_type'] ?? 'fixed',
                'tax_value' => (int) ($lines->last()['tax_value'] ?? 0),
            ])->values();
            $subtotal = 0; $discount = 0; $tax = 0; $prepared = [];
            foreach ($items as $item) {
                $product = Product::where('business_id', $businessId)->findOrFail($item['product_id']);
                ['subtotal' => $lineSubtotal, 'discount' => $lineDiscount, 'tax' => $lineTax, 'total' => $lineTotal] = $this->lineAmounts($item);
                $subtotal += $lineSubtotal;
                $discount += $lineDiscount;
                $tax += $lineTax;
                $prepared[] = compact('product', 'lineTotal', 'lineDiscount', 'lineTax') + $item;
            }
            $quote = SalesQuotation::create([
                'business_id' => $businessId, 'customer_id' => $data['customer_id'] ?? null, 'created_by' => auth()->id(),
                'quotation_number' => $this->numbers->next('quotation'),
                'quotation_date' => $data['quotation_date'], 'valid_until' => $data['valid_until'] ?? null, 'status' => $data['status'],
                'subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax, 'grand_total' => round($subtotal - $discount + $tax, 2), 'notes' => $data['notes'] ?? null,
            ]);
            foreach ($prepared as $item) $quote->items()->create([
                'product_id' => $item['product_id'],
                'product_name_snapshot' => $item['product']->name,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'discount_type' => $item['discount_type'],
                'discount_value' => $item['discount_value'],
                'discount_amount' => $item['lineDiscount'],
                'tax_type' => $item['tax_type'],
                'tax_value' => $item['tax_value'],
                'tax_amount' => $item['lineTax'],
                'line_total' => $item['lineTotal'],
            ]);
        });

        return redirect()->route('business.sales.quotations.index')->with('success', 'Sales quotation saved. Inventory and accounting are unchanged until a sale is created.');
    }

    private function businessId(): int { return (int) auth()->user()->business_id; }

    private function lineAmounts(array $item): array
    {
        $subtotal = round((int) $item['quantity'] * (float) $item['unit_price'], 2);
        $discountValue = (int) ($item['discount_value'] ?? 0);
        $taxValue = (int) ($item['tax_value'] ?? 0);

        if ($item['discount_type'] === 'percentage' && $discountValue > 100) {
            throw ValidationException::withMessages(['items' => 'Discount percentage cannot exceed 100.']);
        }
        if ($item['tax_type'] === 'percentage' && $taxValue > 100) {
            throw ValidationException::withMessages(['items' => 'Tax percentage cannot exceed 100.']);
        }

        $discount = $item['discount_type'] === 'percentage'
            ? round($subtotal * $discountValue / 100, 2)
            : (float) $discountValue;
        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['items' => 'Discount cannot exceed the item base amount.']);
        }

        $taxable = $subtotal - $discount;
        $tax = $item['tax_type'] === 'percentage'
            ? round($taxable * $taxValue / 100, 2)
            : (float) $taxValue;
        $total = round($taxable + $tax, 2);

        if ($total < 0) {
            throw ValidationException::withMessages(['items' => 'Item total cannot be negative.']);
        }

        return compact('subtotal', 'discount', 'tax', 'total');
    }
}
