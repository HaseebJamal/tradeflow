<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Receipt {{ $payment->reference_number ?: '#'.$payment->id }}</title>
    <style>
        @page { margin: 34px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.45; }
        .header { border-bottom: 2px solid #315bea; padding-bottom: 16px; margin-bottom: 22px; }
        .brand { color: #172033; font-size: 22px; font-weight: bold; margin: 0; }
        .subtle { color: #64748b; margin: 4px 0 0; }
        .receipt-meta { float: right; text-align: right; }
        .receipt-id { color: #315bea; font-size: 13px; font-weight: bold; }
        .clear { clear: both; }
        .title { font-size: 17px; font-weight: bold; margin: 0 0 16px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #dbe3ef; padding: 10px 8px; text-align: left; vertical-align: top; }
        th { color: #64748b; font-weight: bold; width: 38%; }
        .amount { color: #172033; font-size: 16px; font-weight: bold; }
        .status { background: #dcfce7; border-radius: 12px; color: #047857; display: inline-block; font-size: 10px; font-weight: bold; padding: 4px 8px; }
        .footer { color: #64748b; font-size: 9px; margin-top: 26px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div class="receipt-meta">
            <div class="receipt-id">{{ $payment->reference_number ?: '#'.$payment->id }}</div>
            <div class="subtle">Issued {{ ($payment->verified_at ?? $payment->paid_at ?? $payment->submitted_at)?->format('n/j/Y, g:i A') ?? '—' }}</div>
        </div>
        <h1 class="brand">{{ $platformName }}</h1>
        <p class="subtle">Verified payment receipt</p>
        <div class="clear"></div>
    </div>

    <h2 class="title">Payment Receipt</h2>
    <table>
        <tr><th>Business</th><td>{{ $business?->business_name ?: '—' }}</td></tr>
        <tr><th>Payment status</th><td><span class="status">Verified / Paid</span></td></tr>
        <tr><th>Agreed amount</th><td class="amount">Rs {{ number_format($payment->amount, 2) }}</td></tr>
        <tr><th>Payment method</th><td>{{ $payment->method ?: '—' }}</td></tr>
        <tr><th>Payment reference</th><td>{{ $payment->transaction_reference ?: $payment->reference_number ?: '—' }}</td></tr>
        <tr><th>Access period</th><td>{{ $payment->period_starts_at?->format('n/j/Y') ?? '—' }} – {{ $payment->period_ends_at?->format('n/j/Y') ?? '—' }}</td></tr>
        <tr><th>Recorded by</th><td>{{ $payment->recordedBy?->name ?: '—' }}</td></tr>
        <tr><th>Verified by</th><td>{{ $payment->verifiedBy?->name ?: '—' }}</td></tr>
    </table>

    <p class="footer">This receipt confirms the recorded payment and does not alter the business's access period.</p>
</body>
</html>
