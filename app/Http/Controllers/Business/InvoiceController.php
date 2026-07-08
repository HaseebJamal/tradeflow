<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\FinanceCalculator;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function __construct(private FinanceCalculator $finance) {}

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
        $invoice = Invoice::updateOrCreate(
            ['order_id' => $order->id],
            ['invoice_number' => $order->invoice?->invoice_number ?? 'INV-'.now()->format('ymdHis'), 'paid_amount' => $order->paid_amount, 'balance' => $order->balance]
        );
        return view('business.invoices.show', ['invoice' => $invoice->load('order.customer', 'order.business', 'order.items.product'), 'order' => $order->load(['customer', 'business', 'items.product'])]);
    }

    public function pdf(Order $order)
    {
        abort_unless($order->business_id === auth()->user()->business_id || auth()->user()->role === 'retailer', 403);
        $order = $this->finance->syncOrderTotals($order);
        $invoice = Invoice::updateOrCreate(
            ['order_id' => $order->id],
            ['invoice_number' => $order->invoice?->invoice_number ?? 'INV-'.now()->format('ymdHis'), 'paid_amount' => $order->paid_amount, 'balance' => $order->balance]
        );
        $pdf = Pdf::loadView('business.invoices.pdf', ['invoice' => $invoice, 'order' => $order->load(['customer', 'business', 'items.product'])]);

        return $pdf->stream($invoice->invoice_number.'.pdf');
    }
}
