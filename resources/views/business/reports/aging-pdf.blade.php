@php
    $title = $type === 'customer' ? 'Customer Aging Report' : 'Supplier Aging Report';
    $party = $type === 'customer' ? 'Customer' : 'Supplier';
    $labels = ['current' => 'Current', 'days_1_30' => '1–30 Days', 'days_31_60' => '31–60 Days', 'days_61_90' => '61–90 Days', 'days_90_plus' => '90+ Days'];
    $money = static fn ($amount) => 'Rs '.number_format((float) $amount, 2);
    $asOf = \Illuminate\Support\Carbon::parse($filters['as_of'])->format('n/j/Y');
@endphp
<x-a4-document :business="$business" :footer="$business?->documentFooter" :title="$title" :reference="'As of '.$asOf" :date="$generatedAt->format('n/j/Y, g:i A')" subtitle="Outstanding balances by actual due date.">
    <table class="tf-a4-document__summary"><tr>
        <td><span class="tf-a4-document__summary-label">Total outstanding</span><strong class="tf-a4-document__summary-value">{{ $money($summary['total_outstanding']) }}</strong></td>
        @foreach(['current', 'days_1_30', 'days_31_60', 'days_61_90', 'days_90_plus'] as $bucket)
            <td><span class="tf-a4-document__summary-label">{{ $labels[$bucket] }}</span><strong class="tf-a4-document__summary-value">{{ $money($summary[$bucket]) }}</strong></td>
        @endforeach
    </tr></table>

    <table class="tf-a4-document__table"><thead><tr>
        <th style="width:21%">{{ $party }}</th><th class="tf-a4-document__money" style="width:13%">Outstanding</th><th class="tf-a4-document__money" style="width:11%">Current</th><th class="tf-a4-document__money" style="width:11%">1–30</th><th class="tf-a4-document__money" style="width:11%">31–60</th><th class="tf-a4-document__money" style="width:11%">61–90</th><th class="tf-a4-document__money" style="width:11%">90+</th><th style="width:11%">Oldest due</th>
    </tr></thead><tbody>
        @forelse($rows as $row)
            <tr><td>{{ $row->party_name }}</td><td class="tf-a4-document__money">{{ $money($row->total_outstanding) }}</td><td class="tf-a4-document__money">{{ $money($row->current) }}</td><td class="tf-a4-document__money">{{ $money($row->days_1_30) }}</td><td class="tf-a4-document__money">{{ $money($row->days_31_60) }}</td><td class="tf-a4-document__money">{{ $money($row->days_61_90) }}</td><td class="tf-a4-document__money">{{ $money($row->days_90_plus) }}</td><td>{{ $row->oldest_due ? \Illuminate\Support\Carbon::parse($row->oldest_due)->format('n/j/Y') : '—' }}</td></tr>
        @empty
            <tr><td class="tf-a4-document__empty" colspan="8">No outstanding records.</td></tr>
        @endforelse
    </tbody></table>
</x-a4-document>
