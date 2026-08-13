<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Business\StorePosSaleRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\HeldPosSale;
use App\Models\Order;
use App\Models\PosRegister;
use App\Models\Product;
use App\Services\CompanyPermissionService;
use App\Services\PosSaleService;
use App\Services\PosDraftCartService;
use App\Services\ThermalDocumentService;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function __construct(
        private PosSaleService $pos,
        private ThermalDocumentService $thermal,
        private PosDraftCartService $drafts,
    ) {}

    public function index(Request $request)
    {
        $businessId = $request->user()->business_id;
        $permissions = app(CompanyPermissionService::class);
        $canViewCustomers = $permissions->allowsUser($request->user(), 'customers.view');
        $canViewCategories = $permissions->allowsUser($request->user(), 'categories.view');

        return view('business.pos.index', [
            'register' => PosRegister::where('business_id', $businessId)->where('user_id', $request->user()->id)->where('status', 'Open')->latest('opened_at')->first(),
            'products' => $this->availableProducts($businessId)->take(60)->get(),
            'categories' => $canViewCategories
                ? Category::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get(['id', 'name'])
                : collect(),
            'customers' => $canViewCustomers
                ? Customer::where('business_id', $businessId)->where('status', 'Active')->orderBy('name')->get(['id', 'name', 'phone'])
                : collect(),
            'canViewCustomers' => $canViewCustomers,
            'canUseCustomPrice' => $permissions->allowsUser($request->user(), 'pos.custom_price'),
            'canCreateCustomer' => $permissions->allowsUser($request->user(), 'customers.create'),
        ]);
    }

    public function products(Request $request)
    {
        $businessId = $request->user()->business_id;
        $query = trim((string) $request->input('q'));
        $categoryId = $request->integer('category_id') ?: null;
        $products = $this->availableProducts($businessId)
            ->when($categoryId, fn ($builder) => $builder->where('category_id', $categoryId))
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($match) use ($query) {
                    $match->where('name', 'like', "%{$query}%")
                        ->orWhere('barcode', $query)
                        ->orWhere('brand', 'like', "%{$query}%")
                        ->orWhere('manufacturer', 'like', "%{$query}%");
                });
            })
            ->take(60)
            ->get();

        return response()->json(['products' => $products]);
    }

    public function barcode(Request $request)
    {
        $code = trim((string) $request->validate(['barcode' => ['required', 'string', 'max:120']])['barcode']);
        $product = $this->availableProducts($request->user()->business_id)->where('barcode', $code)->first();
        if (! $product) return response()->json(['message' => 'Product not found.'], 404);
        return response()->json(['product' => $product]);
    }

    public function openRegister(Request $request)
    {
        $data = $request->validate(['opening_cash' => ['required', 'integer', 'min:0'], 'opening_note' => ['nullable', 'string', 'max:500']]);
        $register = $this->pos->openRegister($request->user()->business_id, $request->user()->id, $data);
        return $this->respond($request, ['register' => $register], 'Register opened.');
    }

    public function closeRegister(Request $request, PosRegister $register)
    {
        abort_unless($register->business_id === $request->user()->business_id && $register->user_id === $request->user()->id, 403);
        $data = $request->validate(['closing_cash' => ['required', 'integer', 'min:0'], 'closing_note' => ['nullable', 'string', 'max:500']]);
        $register = $this->pos->closeRegister($register, $request->user()->business_id, $request->user()->id, $data);
        $this->drafts->clear($request->session(), $request->user()->business_id, $request->user()->id, $register->id);

        return $this->respond($request, ['register' => $register], 'Register closed.');
    }

    public function store(StorePosSaleRequest $request)
    {
        $order = $this->pos->complete($request->user()->business_id, $request->user()->id, $request->validated());
        $invoice = $this->receiptReference($order);
        $register = PosRegister::query()
            ->where('business_id', $request->user()->business_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'Open')
            ->latest('opened_at')
            ->first();

        if ($register) {
            $this->drafts->clear($request->session(), $request->user()->business_id, $request->user()->id, $register->id);
        }

        return $this->respond($request, [
            'order' => $order,
            'receipt_url' => route('business.pos.receipt.view', ['invoice' => $invoice]),
            'receipt_download_url' => route('business.pos.receipt.download', ['invoice' => $invoice]),
            'receipt_print_url' => route('business.pos.receipt.print', ['invoice' => $invoice]),
            'history_url' => route('business.pos.history'),
        ], 'Sale completed successfully.');
    }

    public function hold(Request $request)
    {
        $data = $request->validate([
            'register_id' => ['required', 'integer'],
            'cart' => ['required', 'array', 'min:1'],
            'checkout' => ['nullable', 'array'],
            'hold_number' => ['nullable', 'string', 'max:32'],
            'held_sale_id' => ['nullable', 'integer'],
        ]);
        $register = PosRegister::where('business_id', $request->user()->business_id)->where('user_id', $request->user()->id)->where('status', 'Open')->findOrFail($data['register_id']);
        $held = $this->pos->hold($register, $request->user()->business_id, $request->user()->id, $data['cart'], $data['checkout'] ?? [], $data['hold_number'] ?? null, $data['held_sale_id'] ?? null);
        $this->drafts->clear($request->session(), $request->user()->business_id, $request->user()->id, $register->id);
        return $this->respond($request, ['held_sale' => $held], 'Sale held successfully.');
    }

    public function syncDraft(Request $request)
    {
        $data = $request->validate([
            'register_id' => ['required', 'integer'],
            'cart' => ['required', 'array'],
        ]);

        $register = PosRegister::query()
            ->where('business_id', $request->user()->business_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'Open')
            ->findOrFail($data['register_id']);

        $this->drafts->sync($request->session(), $request->user()->business_id, $request->user()->id, $register->id, $data['cart']);

        return response()->json(['item_count' => count($data['cart'])]);
    }

    public function searchHeldSales(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $term = trim((string) ($data['q'] ?? ''));
        if ($term === '') return response()->json(['held_sales' => []]);

        $holdNumber = $this->pos->normalizeHoldNumber($term);
        $hold = $this->pos->holdsForBusiness($request->user()->business_id)
            ->with('customer:id,name,business_name')
            ->where('hold_number', $holdNumber)
            ->first(['id', 'hold_number', 'customer_id', 'status']);

        if (! $hold) {
            return response()->json(['held_sales' => []]);
        }
        if ($hold->status === 'Completed') {
            return response()->json(['held_sales' => [], 'message' => 'This held sale has already been completed.']);
        }
        if (! $this->pos->resumableHoldsForBusiness($request->user()->business_id)
            ->whereKey($hold->getKey())
            ->exists()) {
            return response()->json(['held_sales' => [], 'message' => 'This held sale is no longer available.']);
        }

        return response()->json(['held_sales' => [[
            'id' => $hold->id,
            'hold_number' => $hold->hold_number,
            'customer_name' => $hold->customer?->display_name ?? 'Walk-in Customer',
        ]]]);
    }

    public function searchInvoices(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $term = trim((string) ($data['q'] ?? ''));
        if ($term === '') return response()->json(['invoices' => []]);

        $orders = Order::query()
            ->with(['customer:id,name,business_name', 'invoice:id,order_id,invoice_number'])
            ->where('business_id', $request->user()->business_id)
            ->where('sale_channel', 'pos')
            ->where('status', 'Completed')
            ->where(function ($query) use ($term) {
                $query->where('order_number', 'like', "%{$term}%")
                    ->orWhereHas('invoice', fn ($invoices) => $invoices->where('invoice_number', 'like', "%{$term}%"));
            })
            ->latest('order_date')
            ->limit(7)
            ->get(['id', 'order_number', 'customer_id', 'grand_total', 'total']);

        return response()->json(['invoices' => $orders->map(function (Order $order) {
            $number = $this->receiptReference($order);
            return [
                'number' => $number,
                'customer_name' => $order->customer?->display_name ?? 'Walk-in Customer',
                'amount' => (int) ($order->grand_total ?: $order->total),
                'url' => route('business.pos.receipt.view', ['invoice' => $number]),
            ];
        })->values()]);
    }

    public function resume(Request $request, HeldPosSale $heldSale)
    {
        $register = PosRegister::query()
            ->where('business_id', $request->user()->business_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'Open')
            ->latest('opened_at')
            ->first();

        if ($register && $this->drafts->hasItems($request->session(), $request->user()->business_id, $request->user()->id, $register->id)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'current_sale' => 'Please hold or clear the current cart before resuming another sale.',
            ]);
        }

        $held = $this->pos->resume($heldSale->id, $request->user()->business_id);
        if ($register) {
            $this->drafts->sync($request->session(), $request->user()->business_id, $request->user()->id, $register->id, $held->cart_payload ?? []);
        }
        return $this->respond($request, ['held_sale' => $held], 'Held sale resumed.');
    }

    public function history(Request $request)
    {
        $orders = Order::with(['customer', 'invoice'])
            ->where('business_id', $request->user()->business_id)
            ->where('sale_channel', 'pos')
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('business.pos.history', [
            'orders' => $orders,
        ]);
    }

    public function receipt(Request $request, Order $order)
    {
        $order = $this->posReceiptOrder($request, $order);
        $route = $request->boolean('print') ? 'business.pos.receipt.print' : 'business.pos.receipt.view';

        return redirect()->route($route, ['invoice' => $this->receiptReference($order)]);
    }

    public function receiptPdf(Request $request, Order $order)
    {
        $order = $this->posReceiptOrder($request, $order);

        return redirect()->route('business.pos.receipt.download', ['invoice' => $this->receiptReference($order)]);
    }

    public function receiptView(Request $request, string $invoice)
    {
        $order = $this->posReceiptOrderByInvoice($request, $invoice);
        $paper = $this->thermal->width($request);

        return view('business.pos.receipt', compact('order', 'paper'));
    }

    public function receiptPrint(Request $request, string $invoice)
    {
        $order = $this->posReceiptOrderByInvoice($request, $invoice);
        $paper = $this->thermal->width($request);

        return view('business.pos.receipt-print', compact('order', 'paper'));
    }

    public function receiptDownload(Request $request, string $invoice)
    {
        $order = $this->posReceiptOrderByInvoice($request, $invoice);
        $paper = $this->thermal->width($request);
        $receiptNumber = $this->receiptReference($order);
        $filename = 'Receipt-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $receiptNumber).'.pdf';

        return $this->thermal->loadPdf('business.pos.receipt-pdf', compact('order', 'paper'), $paper)
            ->download($filename);
    }

    private function availableProducts(int $businessId)
    {
        return Product::query()->where('business_id', $businessId)->where('status', 'Active')->where('stock_quantity', '>', 0)
            ->with('category:id,name')
            ->orderBy('name')
            ->select(['id', 'category_id', 'name', 'barcode', 'image', 'brand', 'manufacturer', 'unit', 'retail_price', 'wholesale_price', 'stock_quantity']);
    }

    private function posReceiptOrder(Request $request, Order $order): Order
    {
        abort_unless(
            (int) $order->business_id === (int) $request->user()->business_id
                && $order->sale_channel === 'pos',
            403
        );

        return $order->load(['business.documentFooter', 'business.owner:id,email', 'customer', 'creator', 'items.product', 'payments', 'invoice']);
    }

    private function posReceiptOrderByInvoice(Request $request, string $invoice): Order
    {
        $order = Order::query()
            ->with(['business.documentFooter', 'business.owner:id,email', 'customer', 'creator', 'items.product', 'payments', 'invoice'])
            ->where('sale_channel', 'pos')
            ->where(function ($query) use ($invoice) {
                $query->where('order_number', $invoice)
                    ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_number', $invoice));
            })
            ->firstOrFail();

        abort_unless((int) $order->business_id === (int) $request->user()->business_id, 403);

        return $order;
    }

    private function receiptReference(Order $order): string
    {
        return (string) ($order->invoice?->invoice_number ?: $order->order_number);
    }

    private function respond(Request $request, array $payload, string $message)
    {
        if ($request->expectsJson()) return response()->json(['message' => $message] + $payload);
        return back()->with('success', $message);
    }
}
