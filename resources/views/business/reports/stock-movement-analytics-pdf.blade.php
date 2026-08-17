@php
    $money = static fn ($amount) => 'Rs '.number_format((float) $amount, 2);
    $quantity = static fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
@endphp
<x-a4-document :business="$business" :footer="$business?->documentFooter" title="Stock Movement Analysis" :reference="$filters['from']->format('n/j/Y').' – '.$filters['to']->format('n/j/Y')" :date="$generatedAt->format('n/j/Y, g:i A')" :subtitle="'Dead-stock threshold: '.$filters['dead_threshold'].' days.'">
    <table class="tf-a4-document__summary"><tr>
        <td><span class="tf-a4-document__summary-label">Fast moving</span><strong class="tf-a4-document__summary-value tf-a4-document__positive">{{ number_format($summary['fast']) }}</strong></td>
        <td><span class="tf-a4-document__summary-label">Slow moving</span><strong class="tf-a4-document__summary-value tf-a4-document__warning">{{ number_format($summary['slow']) }}</strong></td>
        <td><span class="tf-a4-document__summary-label">Dead stock</span><strong class="tf-a4-document__summary-value tf-a4-document__negative">{{ number_format($summary['dead']) }}</strong></td>
        <td><span class="tf-a4-document__summary-label">Dead stock value</span><strong class="tf-a4-document__summary-value tf-a4-document__negative">{{ $money($summary['dead_value']) }}</strong></td>
    </tr></table>

    <table class="tf-a4-document__table"><thead><tr>
        <th style="width:27%">Product</th><th class="tf-a4-document__quantity" style="width:10%">Stock</th><th class="tf-a4-document__quantity" style="width:11%">Qty sold</th><th style="width:13%">Last sale</th><th class="tf-a4-document__quantity" style="width:12%">Days since</th><th class="tf-a4-document__money" style="width:15%">Inventory value</th><th style="width:12%">Status</th>
    </tr></thead><tbody>
        @forelse($rows as $row)
            @php($statusClass = match ($row->movement_status) { 'Fast Moving' => 'tf-a4-document__positive', 'Slow Moving' => 'tf-a4-document__warning', 'Dead Stock' => 'tf-a4-document__negative', default => '' })
            <tr><td>{{ $row->name }}<br><span class="tf-a4-document__muted">{{ $row->category }} · {{ $row->unit }}</span></td><td class="tf-a4-document__quantity">{{ $quantity($row->current_stock) }}</td><td class="tf-a4-document__quantity">{{ $quantity($row->qty_sold) }}</td><td>{{ $row->last_sale_at?->format('n/j/Y') ?? '—' }}</td><td class="tf-a4-document__quantity">{{ $row->days_since_sale ?? '—' }}</td><td class="tf-a4-document__money">{{ $money($row->inventory_value) }}</td><td class="{{ $statusClass }}">{{ $row->movement_status }}</td></tr>
        @empty
            <tr><td class="tf-a4-document__empty" colspan="7">No product analytics match the selected filters.</td></tr>
        @endforelse
    </tbody></table>
</x-a4-document>
