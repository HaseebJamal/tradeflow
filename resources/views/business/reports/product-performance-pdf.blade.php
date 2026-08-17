@php
    $money = static fn ($amount) => 'Rs '.number_format((float) $amount, 2);
    $quantity = static fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
@endphp
<x-a4-document :business="$business" :footer="$business?->documentFooter" title="Product Performance Report" :reference="$filters['from']->format('n/j/Y').' – '.$filters['to']->format('n/j/Y')" :date="$generatedAt->format('n/j/Y, g:i A')" subtitle="Net sales, cost of goods sold, gross profit, and returns use the current canonical report values.">
    <table class="tf-a4-document__summary"><tr>
        <td><span class="tf-a4-document__summary-label">Net sales</span><strong class="tf-a4-document__summary-value">{{ $money($summary['net_sales']) }}</strong></td>
        <td><span class="tf-a4-document__summary-label">Gross profit</span><strong class="tf-a4-document__summary-value {{ $summary['gross_profit'] < 0 ? 'tf-a4-document__negative' : 'tf-a4-document__positive' }}">{{ $money($summary['gross_profit']) }}</strong></td>
        <td><span class="tf-a4-document__summary-label">Average margin</span><strong class="tf-a4-document__summary-value">{{ $summary['average_margin'] === null ? '—' : number_format($summary['average_margin'], 2).'%' }}</strong></td>
        <td><span class="tf-a4-document__summary-label">Return value</span><strong class="tf-a4-document__summary-value">{{ $money($summary['return_value']) }}</strong></td>
    </tr></table>

    <table class="tf-a4-document__table"><thead><tr>
        <th style="width:25%">Product</th><th class="tf-a4-document__quantity" style="width:8%">Sold</th><th class="tf-a4-document__quantity" style="width:8%">Returns</th><th class="tf-a4-document__money" style="width:13%">Net sales</th><th class="tf-a4-document__money" style="width:12%">COGS</th><th class="tf-a4-document__money" style="width:13%">Gross profit</th><th class="tf-a4-document__money" style="width:10%">Margin</th><th class="tf-a4-document__money" style="width:11%">Return rate</th>
    </tr></thead><tbody>
        @forelse($rows as $row)
            <tr><td>{{ $row->name }}<br><span class="tf-a4-document__muted">{{ $row->category }} · {{ $row->unit }}</span></td><td class="tf-a4-document__quantity">{{ $quantity($row->qty_sold) }}</td><td class="tf-a4-document__quantity">{{ $quantity($row->qty_returned) }}</td><td class="tf-a4-document__money">{{ $money($row->net_sales) }}</td><td class="tf-a4-document__money">{{ $money($row->cogs) }}</td><td class="tf-a4-document__money {{ $row->gross_profit < 0 ? 'tf-a4-document__negative' : '' }}">{{ $money($row->gross_profit) }}</td><td class="tf-a4-document__money">{{ $row->gross_margin === null ? '—' : number_format($row->gross_margin, 2).'%' }}</td><td class="tf-a4-document__money">{{ $row->return_rate === null ? '—' : number_format($row->return_rate, 2).'%' }}</td></tr>
        @empty
            <tr><td class="tf-a4-document__empty" colspan="8">No completed sales or recorded returns for this period.</td></tr>
        @endforelse
    </tbody></table>
</x-a4-document>
