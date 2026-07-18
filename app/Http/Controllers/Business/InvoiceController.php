<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Services\AccountingService;
use App\Services\BusinessActivityService;
use App\Services\FinanceCalculator;
use App\Services\DocumentNumberService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(private FinanceCalculator $finance, private AccountingService $accounting, private BusinessActivityService $activity, private DocumentNumberService $numbers) {}

    public function index()
    {
        return view('business.invoices.index', [
            'orders' => Order::with(['customer', 'invoice'])->where('business_id', auth()->user()->business_id)->latest()->paginate(20),
        ]);
    }

    public function show(Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id || auth()->user()->role === 'retailer', 403);
        $order = $this->finance->syncOrderTotals($order);
        $invoice = $this->syncInvoice($order);
        return view('business.invoices.show', ['invoice' => $invoice->load('order.customer', 'order.business', 'order.items.product', 'items', 'creditNotes'), 'order' => $order->load(['customer', 'business', 'items.product'])]);
    }

    public function pdf(Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id || auth()->user()->role === 'retailer', 403);
        $order = $this->finance->syncOrderTotals($order);
        $invoice = $this->syncInvoice($order);
        $pdf = Pdf::loadView('business.invoices.pdf', ['invoice' => $invoice->load('items'), 'order' => $order->load(['customer', 'business', 'items.product'])]);

        return $pdf->stream($invoice->invoice_number.'.pdf');
    }

    public function downloadPdf(Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id || auth()->user()->role === 'retailer', 403);
        $order = $this->finance->syncOrderTotals($order);
        $invoice = $this->syncInvoice($order);

        return Pdf::loadView('business.invoices.pdf', [
            'invoice' => $invoice->load('items'),
            'order' => $order->load(['customer', 'business', 'items.product']),
        ])->download($invoice->invoice_number.'.pdf');
    }

    public function update(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->business_id === auth()->user()->business_id, 403);
        abort_unless($invoice->status === 'Draft', 403);
        $data = $request->validate([
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        $invoice->update($data);
        $this->activity->record($invoice->business_id, 'Sales', 'Draft invoice updated: '.$invoice->invoice_number, $invoice->id, null, $data);

        return back()->with('success', 'Draft invoice updated.');
    }

    public function issue(Invoice $invoice)
    {
        abort_unless($invoice->business_id === auth()->user()->business_id, 403);
        if ($invoice->status !== 'Draft') {
            return back()->withErrors(['status' => 'Only draft invoices can be issued.']);
        }
        $invoice->update(['status' => $invoice->balance <= 0 ? 'Paid' : 'Issued', 'issued_by' => auth()->id(), 'issued_at' => now()]);
        $this->activity->record($invoice->business_id, 'Sales', 'Invoice issued: '.$invoice->invoice_number, $invoice->id, null, ['status' => $invoice->status]);

        return back()->with('success', 'Invoice issued.');
    }

    public function void(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->business_id === auth()->user()->business_id, 403);
        $data = $request->validate(['void_reason' => ['required', 'string', 'max:1000']]);
        $invoice->update(['status' => 'Void', 'voided_by' => auth()->id(), 'voided_at' => now(), 'void_reason' => $data['void_reason']]);
        $this->activity->record($invoice->business_id, 'Sales', 'Invoice voided: '.$invoice->invoice_number, $invoice->id, null, ['reason' => $data['void_reason']]);

        return back()->with('success', 'Invoice voided.');
    }

    public function reissue(Invoice $invoice)
    {
        abort_unless($invoice->business_id === auth()->user()->business_id, 403);
        if (!in_array($invoice->status, ['Void', 'Cancelled'], true)) {
            return back()->withErrors(['status' => 'Only voided invoices can be reissued.']);
        }

        $order = Order::where('business_id', $invoice->business_id)->findOrFail($invoice->order_id);
        $order = $this->finance->syncOrderTotals($order);
        $invoice->update([
            'subtotal' => $order->subtotal,
            'discount_percentage' => $order->discount_percentage ?? $order->discount ?? 0,
            'discount_amount' => $order->discount_amount ?? 0,
            'grand_total' => $order->grand_total ?: $order->total,
            'paid_amount' => $order->paid_amount,
            'balance' => $order->balance,
            'payment_status' => $order->payment_status ?? 'Pending',
            'status' => ($order->balance ?? 0) <= 0 ? 'Paid' : 'Issued',
            'issued_by' => auth()->id(),
            'issued_at' => now(),
            'voided_by' => null,
            'voided_at' => null,
            'void_reason' => null,
        ]);
        $this->activity->record($invoice->business_id, 'Sales', 'Invoice reissued: '.$invoice->invoice_number, $invoice->id, null, ['status' => $invoice->status]);

        return back()->with('success', 'Invoice reissued using the current order totals.');
    }

    public function creditNote(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->business_id === auth()->user()->business_id, 403);
        abort_if($invoice->status === 'Draft', 403);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.$invoice->grand_total],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($invoice, $data) {
            $note = CreditNote::create([
                'business_id' => $invoice->business_id,
                'invoice_id' => $invoice->id,
                'credit_note_number' => $this->numbers->next('credit_note'),
                'date' => now()->toDateString(),
                'reason' => $data['reason'],
                'amount' => $data['amount'],
                'status' => 'Posted',
                'created_by' => auth()->id(),
            ]);

            $this->accounting->ensureDefaultAccounts($invoice->business_id);
            $discount = Account::where('business_id', $invoice->business_id)->where('name', 'Discount Allowed')->first();
            $ar = Account::where('business_id', $invoice->business_id)->where('name', 'Accounts Receivable')->first();
            if ($discount && $ar) {
                $this->accounting->post($invoice->business_id, [
                    'voucher_number' => 'CN-JV-'.$note->id.'-'.now()->format('His'),
                    'entry_date' => now()->toDateString(),
                    'reference_type' => 'credit_note',
                    'reference_id' => $note->id,
                    'description' => 'Credit note '.$note->credit_note_number,
                ], [
                    ['account_id' => $discount->id, 'customer_id' => $invoice->customer_id, 'debit' => $note->amount, 'credit' => 0, 'description' => $note->reason],
                    ['account_id' => $ar->id, 'customer_id' => $invoice->customer_id, 'debit' => 0, 'credit' => $note->amount, 'description' => $note->reason],
                ]);
            }
        });
        $this->activity->record($invoice->business_id, 'Sales', 'Credit note posted for '.$invoice->invoice_number, $invoice->id, null, ['amount' => $data['amount'], 'reason' => $data['reason']]);

        return back()->with('success', 'Credit note posted.');
    }

    private function syncInvoice(Order $order): Invoice
    {
        $invoice = Invoice::updateOrCreate(
            ['order_id' => $order->id],
            [
                'business_id' => $order->business_id,
                'customer_id' => $order->customer_id,
                'invoice_number' => $order->invoice?->invoice_number ?? $this->numbers->next('sales'),
                'invoice_date' => $order->order_date ?? now()->toDateString(),
                'subtotal' => $order->subtotal,
                'discount_percentage' => $order->discount_percentage ?? $order->discount ?? 0,
                'discount_amount' => $order->discount_amount ?? 0,
                'grand_total' => $order->grand_total ?: $order->total,
                'paid_amount' => $order->paid_amount,
                'balance' => $order->balance,
                'payment_status' => $order->payment_status ?? 'Pending',
                'status' => $order->invoice?->status ?? 'Draft',
            ]
        );

        if (in_array($invoice->status, ['Draft'], true)) {
            $invoice->items()->delete();
            foreach ($order->items as $item) {
                $invoice->items()->create([
                    'product_id' => $item->product_id,
                    'product_name_snapshot' => $item->product_name_snapshot ?: $item->product?->name ?: 'Product',
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price ?: $item->price,
                    'line_total' => $item->line_total ?: $item->total,
                ]);
            }
        }

        return $invoice->fresh();
    }
}
