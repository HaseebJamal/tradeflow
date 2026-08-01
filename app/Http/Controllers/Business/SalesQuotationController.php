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
        return view('business.sales.quotations.index', [
            'quotations' => SalesQuotation::with('customer')
                ->where('business_id', $this->businessId())
                ->latest('quotation_date')
                ->paginate(12),
        ]);
    }

    public function create()
    {
        return $this->formView();
    }

    public function show(SalesQuotation $quotation)
    {
        $quotation = $this->scoped($quotation)->load(['customer', 'items.product']);

        return view('business.sales.quotations.show', compact('quotation'));
    }

    public function edit(SalesQuotation $quotation)
    {
        $quotation = $this->editable($quotation);

        return $this->formView($quotation);
    }

    public function store(Request $request)
    {
        $quotation = DB::transaction(fn () => $this->persist($this->validatedData($request)));

        return redirect()->route('business.sales.quotations.index')->with('success', 'Sales quotation '.$quotation->quotation_number.' saved. Inventory and accounting are unchanged until it is converted into a sale.');
    }

    public function update(Request $request, SalesQuotation $quotation)
    {
        $quotation = $this->editable($quotation);
        $quotation = DB::transaction(fn () => $this->persist($this->validatedData($request), $quotation));

        return redirect()->route('business.sales.quotations.index')->with('success', 'Sales quotation '.$quotation->quotation_number.' updated.');
    }

    public function convert(SalesQuotation $quotation)
    {
        $quotation = $this->scoped($quotation)->load('items');
        if (! in_array($quotation->status, ['Draft', 'Sent', 'Accepted'], true)) {
            return back()->withErrors(['quotation' => 'This quotation is not available for conversion.']);
        }
        if ($quotation->valid_until?->isPast()) {
            return back()->withErrors(['quotation' => 'This quotation has expired and cannot be converted.']);
        }
        if (! $quotation->customer_id || $quotation->items->isEmpty()) {
            return back()->withErrors(['quotation' => 'A customer and at least one quotation item are required before conversion.']);
        }
        if ($quotation->items->contains(fn ($item) => ($item->discount_type === 'fixed' && (int) $item->discount_value > 0) || ($item->tax_type === 'fixed' && (int) $item->tax_value > 0))) {
            return back()->withErrors(['quotation' => 'This quotation contains fixed adjustments and must be updated before conversion to POS.']);
        }

        $productIds = $quotation->items->pluck('product_id')->unique();
        $products = Product::where('business_id', $quotation->business_id)->whereIn('id', $productIds)->get();
        if ($products->count() !== $productIds->count()) {
            return back()->withErrors(['quotation' => 'One or more quoted products are no longer available for this business.']);
        }

        return redirect()->route('business.pos.index', ['quotation' => $quotation->id])->with('success', 'Quotation '.$quotation->quotation_number.' is loaded in POS. Complete the sale to convert it.');
    }

    public function destroy(SalesQuotation $quotation)
    {
        $quotation = $this->scoped($quotation);
        if (! in_array($quotation->status, ['Draft', 'Sent'], true)) {
            return back()->withErrors(['quotation' => 'Only draft or sent quotations can be deleted.']);
        }
        $number = $quotation->quotation_number;
        $quotation->delete();

        return back()->with('success', 'Sales quotation '.$number.' deleted.');
    }

    private function formView(?SalesQuotation $quotation = null)
    {
        $businessId = $this->businessId();

        return view('business.sales.quotations.create', [
            'quotation' => $quotation?->load('items'),
            'customers' => Customer::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get(),
            'products' => Product::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get(),
        ]);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'status' => ['required', Rule::in(['Draft', 'Sent'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'items.*.discount_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'items.*.discount_value' => ['nullable', 'integer', 'min:0'],
            'items.*.tax_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'items.*.tax_value' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function persist(array $data, ?SalesQuotation $quotation = null): SalesQuotation
    {
        $businessId = $this->businessId();
        if (! empty($data['customer_id'])) {
            Customer::where('business_id', $businessId)->findOrFail($data['customer_id']);
        }
        $items = collect($data['items'])->groupBy('product_id')->map(fn ($lines, $productId) => [
            'product_id' => (int) $productId,
            'quantity' => (int) $lines->sum('quantity'),
            'unit_price' => (int) $lines->last()['unit_price'],
            'discount_type' => $lines->last()['discount_type'] ?? 'fixed',
            'discount_value' => (int) ($lines->last()['discount_value'] ?? 0),
            'tax_type' => $lines->last()['tax_type'] ?? 'fixed',
            'tax_value' => (int) ($lines->last()['tax_value'] ?? 0),
        ])->values();

        $subtotal = 0; $discount = 0; $tax = 0; $prepared = [];
        foreach ($items as $item) {
            $product = Product::where('business_id', $businessId)->findOrFail($item['product_id']);
            ['subtotal' => $lineSubtotal, 'discount' => $lineDiscount, 'tax' => $lineTax, 'total' => $lineTotal] = $this->lineAmounts($item);
            $subtotal += $lineSubtotal; $discount += $lineDiscount; $tax += $lineTax;
            $prepared[] = compact('product', 'lineTotal', 'lineDiscount', 'lineTax') + $item;
        }

        $attributes = [
            'customer_id' => $data['customer_id'] ?? null,
            'quotation_date' => $data['quotation_date'],
            'valid_until' => $data['valid_until'] ?? null,
            'status' => $data['status'],
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'grand_total' => array_sum(array_column($prepared, 'lineTotal')),
            'notes' => $data['notes'] ?? null,
        ];
        if (! $quotation) {
            $quotation = SalesQuotation::create($attributes + [
                'business_id' => $businessId,
                'created_by' => auth()->id(),
                'quotation_number' => $this->numbers->next('quotation'),
            ]);
        } else {
            $quotation->update($attributes);
            $quotation->items()->delete();
        }
        foreach ($prepared as $item) {
            $quotation->items()->create([
                'product_id' => $item['product_id'], 'product_name_snapshot' => $item['product']->name,
                'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'],
                'discount_type' => $item['discount_type'], 'discount_value' => $item['discount_value'], 'discount_amount' => $item['lineDiscount'],
                'tax_type' => $item['tax_type'], 'tax_value' => $item['tax_value'], 'tax_amount' => $item['lineTax'], 'line_total' => $item['lineTotal'],
            ]);
        }

        return $quotation->fresh('items');
    }

    private function scoped(SalesQuotation $quotation): SalesQuotation
    {
        abort_unless((int) $quotation->business_id === $this->businessId(), 404);
        return $quotation;
    }

    private function editable(SalesQuotation $quotation): SalesQuotation
    {
        $quotation = $this->scoped($quotation);
        abort_unless(in_array($quotation->status, ['Draft', 'Sent'], true), 403, 'Converted quotations cannot be edited.');
        return $quotation;
    }

    private function businessId(): int { return (int) auth()->user()->business_id; }

    private function lineAmounts(array $item): array
    {
        $subtotal = round((int) $item['quantity'] * (int) $item['unit_price'], 2);
        $discountValue = (int) ($item['discount_value'] ?? 0);
        $taxValue = (int) ($item['tax_value'] ?? 0);
        if ($item['discount_type'] === 'percentage' && $discountValue > 100) throw ValidationException::withMessages(['items' => 'Discount percentage cannot exceed 100.']);
        if ($item['tax_type'] === 'percentage' && $taxValue > 100) throw ValidationException::withMessages(['items' => 'Tax percentage cannot exceed 100.']);
        $discount = $item['discount_type'] === 'percentage' ? round($subtotal * $discountValue / 100, 2) : $discountValue;
        if ($discount > $subtotal) throw ValidationException::withMessages(['items' => 'Discount cannot exceed the item base amount.']);
        $taxable = $subtotal - $discount;
        $tax = $item['tax_type'] === 'percentage' ? round($taxable * $taxValue / 100, 2) : $taxValue;
        $total = (float) round($taxable + $tax, 0, PHP_ROUND_HALF_UP);
        if ($total < 0) throw ValidationException::withMessages(['items' => 'Item total cannot be negative.']);
        return compact('subtotal', 'discount', 'tax', 'total');
    }
}
