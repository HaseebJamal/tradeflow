<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 25mm 14mm 18mm; }
        * { box-sizing: border-box; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.45; margin: 0; }
        .report-header { border-bottom: 2px solid #1f5eff; margin-bottom: 14px; padding-bottom: 10px; }
        .brand { color: #0b1f3a; font-size: 20px; font-weight: bold; letter-spacing: .2px; margin: 0 0 3px; }
        .business-meta { color: #52627a; font-size: 8.5px; margin: 0; }
        .report-type { color: #1f5eff; font-size: 10px; font-weight: bold; letter-spacing: 1.1px; margin: 0 0 3px; text-align: right; text-transform: uppercase; }
        .period { color: #52627a; font-size: 8.5px; margin: 7px 0 0; }
        .header-table { width: 100%; border: 0; border-collapse: collapse; margin: 0; }
        .header-table td { border: 0; padding: 0; vertical-align: top; }
        .header-table td:last-child { width: 42%; }
        h2 { color: #0b1f3a; font-size: 12px; margin: 16px 0 7px; }
        .summary-grid { border-collapse: separate; border-spacing: 6px; margin: 0 -6px; width: calc(100% + 12px); }
        .summary-grid td { background: #f6f8fc; border: 1px solid #dde5f0; border-radius: 5px; padding: 8px 9px; vertical-align: top; width: 33.333%; }
        .summary-label { color: #64748b; display: block; font-size: 8px; margin-bottom: 3px; }
        .summary-value { color: #0b1f3a; display: block; font-size: 12px; font-weight: bold; }
        table.data-table { border-collapse: collapse; margin-top: 7px; table-layout: fixed; width: 100%; }
        .data-table thead { display: table-header-group; }
        .data-table th { background: #edf3ff; border-bottom: 1px solid #bfd2ff; color: #1749bf; font-size: 7.5px; font-weight: bold; letter-spacing: .3px; padding: 6px 5px; text-align: left; text-transform: uppercase; }
        .data-table td { border-bottom: 1px solid #e4e9f1; color: #25334a; overflow-wrap: break-word; padding: 6px 5px; vertical-align: top; }
        .data-table tr { page-break-inside: avoid; }
        .money, .qty { text-align: right !important; white-space: nowrap; }
        .muted { color: #718096; }
        .status { border-radius: 8px; color: #31517d; font-size: 7.5px; padding: 2px 5px; white-space: nowrap; }
        .empty-state { background: #f7f9fc; border: 1px dashed #cbd5e1; color: #64748b; margin-top: 7px; padding: 14px; text-align: center; }
        .footer { bottom: -12mm; color: #718096; font-size: 7.5px; left: 0; position: fixed; right: 0; }
        .footer table { border-collapse: collapse; margin: 0; width: 100%; }
        .footer td { border-top: 1px solid #dce3ed; padding-top: 5px; }
        .footer td:nth-child(2) { text-align: center; }
        .footer td:last-child { text-align: right; }
        .page-number:after { content: counter(page) " of " counter(pages); }
    </style>
</head>
<body>
@php
    $money = static function ($value): string {
        $amount = (float) $value;
        return 'Rs '.number_format($amount, floor($amount) === $amount ? 0 : 2);
    };
    $quantity = static fn ($value): string => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.');
    $reportTitles = [
        'sales' => 'Business Sales Report',
        'inventory' => 'Inventory Position Report',
        'expense' => 'Expense Report',
        'profit-loss' => 'Profitability Report',
        'supplier-payables' => 'Supplier Payables Report',
        'complete' => 'Complete Business Report',
    ];
    $reportTitle = $reportTitles[$type] ?? 'Business Report';
    $businessName = $business?->business_name ?: 'Profit Point';
    $hasSalesRows = $orders->isNotEmpty();
    $hasInventoryRows = $products->isNotEmpty();
    $hasExpenseRows = $expenses->isNotEmpty();
    $hasPayableRows = $payables->isNotEmpty();
    $showSales = in_array($type, ['sales', 'complete'], true);
    $showInventory = in_array($type, ['inventory', 'complete'], true);
    $showProfitability = in_array($type, ['profit-loss', 'complete'], true);
    $showExpenses = $type === 'expense';
    $showPayables = in_array($type, ['supplier-payables', 'complete'], true);
@endphp

<header class="report-header">
    <table class="header-table">
        <tr>
            <td>
                <h1 class="brand">{{ $businessName }}</h1>
                <p class="business-meta">
                    Profit Point
                    @if($business?->phone)
                        &middot; {{ $business->phone }}
                    @endif
                    @if($business?->owner?->email)
                        &middot; {{ $business->owner->email }}
                    @endif
                </p>
            </td>
            <td>
                <p class="report-type">{{ $reportTitle }}</p>
                <p class="business-meta" style="text-align:right">Generated: {{ $generatedAt->format('n/j/Y, g:i A') }}</p>
            </td>
        </tr>
    </table>
    <p class="period">Reporting Period: <strong>{{ $filters['from']->format('n/j/Y') }} &ndash; {{ $filters['to']->format('n/j/Y') }}</strong></p>
</header>

@if($showSales)
    <h2>Sales Summary</h2>
    <table class="summary-grid"><tr>
        <td><span class="summary-label">Gross Sales</span><span class="summary-value">{{ $money($summary['subtotal']) }}</span></td>
        <td><span class="summary-label">Discounts</span><span class="summary-value">{{ $money($summary['discount_amount']) }}</span></td>
        <td><span class="summary-label">Net Sales</span><span class="summary-value">{{ $money($summary['net_sales']) }}</span></td>
    </tr><tr>
        <td><span class="summary-label">Received</span><span class="summary-value">{{ $money($summary['paid_amount']) }}</span></td>
        <td><span class="summary-label">Outstanding</span><span class="summary-value">{{ $money($summary['balance']) }}</span></td>
        <td><span class="summary-label">Sales Returns</span><span class="summary-value">{{ $money($summary['sales_returns']) }}</span></td>
    </tr></table>

    <h2>Sales Transactions</h2>
    @if($hasSalesRows)
        <table class="data-table"><thead><tr><th style="width:12%">Invoice</th><th style="width:11%">Date</th><th style="width:17%">Customer</th><th style="width:20%">Products / Items</th><th class="qty" style="width:7%">Qty</th><th class="money" style="width:9%">Subtotal</th><th class="money" style="width:8%">Discount</th><th class="money" style="width:9%">Net Amount</th><th class="money" style="width:7%">Paid</th><th class="money" style="width:7%">Balance</th><th style="width:8%">Status</th></tr></thead><tbody>
            @foreach($orders as $order)
                @php($items = $order->items)
                <tr><td>{{ $order->order_number ?: '#'.$order->id }}</td><td>{{ ($order->order_date ?: $order->created_at)?->format('n/j/Y') }}</td><td>{{ $order->customer?->business_name ?: $order->customer?->name ?: 'Walk-in Customer' }}</td><td>{{ $items->pluck('product.name')->filter()->implode(', ') ?: '—' }}</td><td class="qty">{{ $quantity($items->sum('quantity')) }}</td><td class="money">{{ $money($order->subtotal) }}</td><td class="money">{{ $money($order->discount_amount) }}</td><td class="money">{{ $money($order->grand_total ?: $order->total) }}</td><td class="money">{{ $money($order->paid_amount) }}</td><td class="money">{{ $money($order->balance) }}</td><td><span class="status">{{ $order->status }}</span></td></tr>
            @endforeach
        </tbody></table>
    @else
        <div class="empty-state">No transactions were recorded for the selected period.</div>
    @endif
@endif

@if($showInventory)
    <h2>Inventory Position</h2>
    <table class="summary-grid"><tr><td><span class="summary-label">Stock Value</span><span class="summary-value">{{ $money($summary['stock_value']) }}</span></td><td><span class="summary-label">Low Stock</span><span class="summary-value">{{ number_format($summary['low_stock_count']) }}</span></td><td><span class="summary-label">Out of Stock</span><span class="summary-value">{{ number_format($summary['out_of_stock_count']) }}</span></td></tr></table>
    @if($type === 'inventory')
        @if($hasInventoryRows)
            <table class="data-table"><thead><tr><th style="width:31%">Product</th><th style="width:20%">Category</th><th style="width:12%">Unit</th><th class="qty" style="width:12%">Qty</th><th class="money" style="width:12%">Average Cost</th><th class="money" style="width:13%">Stock Value</th></tr></thead><tbody>@foreach($products as $product)<tr><td>{{ $product->name }}</td><td>{{ $product->category?->name ?: '—' }}</td><td>{{ $product->unitRecord?->name ?: $product->unit ?: '—' }}</td><td class="qty">{{ $quantity($product->stock_quantity) }}</td><td class="money">{{ $money($product->average_purchase_price ?: $product->purchase_cost ?: $product->wholesale_price) }}</td><td class="money">{{ $money((float) $product->stock_quantity * (float) ($product->purchase_cost ?: $product->wholesale_price)) }}</td></tr>@endforeach</tbody></table>
        @else
            <div class="empty-state">No products are available for the selected filters.</div>
        @endif
    @endif
@endif

@if($showProfitability)
    <h2>Profitability Summary</h2>
    <table class="summary-grid"><tr><td><span class="summary-label">Gross Sales</span><span class="summary-value">{{ $money($profitability['gross_sales']) }}</span></td><td><span class="summary-label">Sales Returns</span><span class="summary-value">-{{ $money($profitability['sales_returns']) }}</span></td><td><span class="summary-label">Invoice Discounts</span><span class="summary-value">-{{ $money($profitability['invoice_discounts']) }}</span></td></tr><tr><td><span class="summary-label">Net Sales</span><span class="summary-value">{{ $money($profitability['net_sales']) }}</span></td><td><span class="summary-label">COGS</span><span class="summary-value">-{{ $money($profitability['cogs']) }}</span></td><td><span class="summary-label">Gross Profit</span><span class="summary-value">{{ $money($profitability['gross_profit']) }}</span></td></tr><tr><td><span class="summary-label">Operating Expenses</span><span class="summary-value">-{{ $money($profitability['expenses']) }}</span></td><td><span class="summary-label">{{ $profitability['net_profit'] < 0 ? 'Net Loss' : 'Net Profit' }}</span><span class="summary-value">{{ $money($profitability['net_profit']) }}</span></td><td><span class="summary-label">Gross Margin</span><span class="summary-value">{{ $profitability['gross_margin'] === null ? '—' : number_format($profitability['gross_margin'], 2).'%' }}</span></td></tr></table>
    <h2>Expense Breakdown</h2>
    @if($profitability['expense_categories']->isNotEmpty())<table class="data-table"><thead><tr><th>Category</th><th class="money">Amount</th></tr></thead><tbody>@foreach($profitability['expense_categories'] as $expense)<tr><td>{{ $expense->category }}</td><td class="money">{{ $money($expense->amount) }}</td></tr>@endforeach</tbody></table>@else<div class="empty-state">No operating expenses were recorded for the selected period.</div>@endif
@endif

@if($showExpenses)
    <h2>Expenses</h2>
    @if($hasExpenseRows)
        <table class="data-table"><thead><tr><th style="width:18%">Date</th><th style="width:48%">Description</th><th style="width:20%">Category</th><th class="money" style="width:14%">Amount</th></tr></thead><tbody>@foreach($expenses as $expense)<tr><td>{{ $expense->expense_date?->format('n/j/Y') }}</td><td>{{ $expense->title ?: $expense->description ?: 'Expense' }}</td><td>{{ $expense->category ?: '—' }}</td><td class="money">{{ $money($expense->amount) }}</td></tr>@endforeach</tbody></table>
    @else
        <div class="empty-state">No expense transactions were recorded for the selected period.</div>
    @endif
@endif

@if($showPayables)
    <h2>Supplier Exposure</h2>
    <table class="summary-grid"><tr><td><span class="summary-label">Supplier Payables</span><span class="summary-value">{{ $money($summary['supplier_payables']) }}</span></td><td><span class="summary-label">Due Today</span><span class="summary-value">{{ $money($summary['due_today']) }}</span></td><td><span class="summary-label">Due Soon</span><span class="summary-value">{{ $money($summary['due_soon']) }}</span></td></tr><tr><td><span class="summary-label">Overdue</span><span class="summary-value">{{ $money($summary['overdue']) }}</span></td><td colspan="2"><span class="summary-label">Selected Period</span><span class="summary-value">{{ $filters['from']->format('n/j/Y') }} &ndash; {{ $filters['to']->format('n/j/Y') }}</span></td></tr></table>
    @if($type === 'supplier-payables')
        @if($hasPayableRows)
            <table class="data-table"><thead><tr><th style="width:23%">Supplier</th><th style="width:15%">Invoice</th><th style="width:13%">Purchase Date</th><th style="width:13%">Due Date</th><th class="money" style="width:12%">Total</th><th class="money" style="width:12%">Paid</th><th class="money" style="width:12%">Balance</th></tr></thead><tbody>@foreach($payables as $purchase)<tr><td>{{ $purchase->supplier?->supplier_name ?: '—' }}</td><td>{{ $purchase->supplier_invoice_number ?: $purchase->purchase_number }}</td><td>{{ $purchase->purchase_date?->format('n/j/Y') }}</td><td>{{ $purchase->due_date?->format('n/j/Y') ?: '—' }}</td><td class="money">{{ $money($purchase->grand_total) }}</td><td class="money">{{ $money($purchase->paid_amount) }}</td><td class="money">{{ $money($purchase->balance) }}</td></tr>@endforeach</tbody></table>
        @else
            <div class="empty-state">No outstanding supplier payables were recorded for the selected period.</div>
        @endif
    @endif
@endif

<footer class="footer"><table><tr><td>Generated by Profit Point</td><td>{{ $businessName }}</td><td>Page <span class="page-number"></span></td></tr></table></footer>
</body>
</html>
