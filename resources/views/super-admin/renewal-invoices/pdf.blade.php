<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Renewal Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 30px 34px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 10.5px; line-height: 1.5; }
        table { border-collapse: collapse; width: 100%; }
        .header { border-bottom: 2px solid #315bea; padding-bottom: 17px; }
        .brand-cell { vertical-align: top; width: 54%; }
        .invoice-cell { text-align: right; vertical-align: top; }
        .brand-lockup { display: table; }
        .brand-mark { background: #315bea; border-radius: 9px; color: #ffffff; display: table-cell; font-size: 12px; font-weight: bold; height: 36px; text-align: center; vertical-align: middle; width: 36px; }
        .brand-logo { border-radius: 9px; display: block; height: 36px; object-fit: cover; width: 36px; }
        .brand-copy { display: table-cell; padding-left: 9px; vertical-align: middle; }
        .brand-name { font-size: 19px; font-weight: bold; line-height: 1.15; }
        .brand-detail { color: #64748b; display: block; font-size: 9px; margin-top: 3px; }
        .invoice-kicker { color: #315bea; font-size: 10px; font-weight: bold; letter-spacing: 1.1px; text-transform: uppercase; }
        .invoice-title { font-size: 25px; font-weight: bold; line-height: 1.12; margin: 2px 0 4px; }
        .invoice-number { color: #315bea; font-size: 11px; font-weight: bold; }
        .invoice-date { color: #64748b; font-size: 9px; margin-top: 3px; }
        .status { border-radius: 10px; display: inline-block; font-size: 8.5px; font-weight: bold; margin-top: 6px; padding: 3px 8px; }
        .status-generated { background: #e8f0ff; color: #2455bf; }
        .status-pending { background: #fff4d6; color: #a45c00; }
        .status-paid { background: #dcfce7; color: #067647; }
        .status-overdue { background: #fee2e2; color: #b42318; }
        .status-cancelled { background: #fef2f2; color: #b42318; }
        .status-superseded { background: #eef2f6; color: #475569; }
        .notice { background: #eff6ff; border-left: 4px solid #315bea; margin: 20px 0; padding: 12px 14px; }
        .notice-title { color: #1e3a8a; font-size: 11px; font-weight: bold; margin-bottom: 3px; }
        .columns { margin-top: 0; table-layout: fixed; }
        .column-left { padding-right: 9px; vertical-align: top; width: 54%; }
        .column-right { padding-left: 9px; vertical-align: top; width: 46%; }
        .detail-card, .amount-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 13px; }
        .section-title { color: #334155; font-size: 11px; font-weight: bold; margin: 0 0 9px; }
        .detail-row { border-top: 1px solid #eef2f7; padding: 6px 0; }
        .detail-row.first { border-top: 0; padding-top: 0; }
        .detail-label { color: #64748b; display: inline-block; width: 39%; }
        .detail-value { color: #172033; display: inline-block; font-weight: bold; max-width: 60%; overflow-wrap: anywhere; text-align: right; vertical-align: top; }
        .amount-card { background: #f8fbff; border-color: #cbdaf9; margin-top: 13px; text-align: right; }
        .amount-label { color: #64748b; font-size: 9px; text-transform: uppercase; }
        .amount-value { color: #172033; font-size: 23px; font-weight: bold; line-height: 1.15; margin-top: 3px; }
        .amount-caption { color: #64748b; font-size: 8.5px; margin-top: 4px; }
    </style>
</head>
<body>
@php
    $isRenewalSecured = (bool) ($renewalSecured ?? false);
    $nextPaidCycle = $upcomingPaidCycle ?? null;
    $statusClass = match ($invoice->status) {
        'Paid' => 'status-paid',
        'Pending Payment' => 'status-pending',
        'Overdue' => 'status-overdue',
        'Cancelled' => 'status-cancelled',
        'Superseded' => 'status-superseded',
        default => 'status-generated',
    };
    $daysRemaining = $invoice->access_ends_at
        ? max(0, (int) now(config('app.timezone'))->diffInDays($invoice->access_ends_at->copy()->endOfDay(), false))
        : null;
@endphp
<div class="header">
    <table>
        <tr>
            <td class="brand-cell">
                <div class="brand-lockup">
                    @if($platformLogoDataUri)
                        <span class="brand-mark"><img class="brand-logo" src="{{ $platformLogoDataUri }}" alt=""></span>
                    @else
                        <span class="brand-mark">PP</span>
                    @endif
                    <span class="brand-copy"><span class="brand-name">{{ $platformName }}</span><span class="brand-detail">Workspace billing &amp; access</span></span>
                </div>
            </td>
            <td class="invoice-cell">
                <div class="invoice-kicker">Renewal Invoice</div>
                <div class="invoice-title">RENEWAL INVOICE</div>
                <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                <div class="invoice-date">Created {{ $invoice->created_at?->format('d M Y, g:i A') ?: '—' }}</div>
                <span class="status {{ $statusClass }}">{{ $invoice->status }}</span>
            </td>
        </tr>
    </table>
</div>

<div class="notice">
    @if($isRenewalSecured)
        <div class="notice-title">Renewal Payment Received</div>
        Your current {{ $platformName }} access remains active through <strong>{{ $invoice->access_ends_at?->format('d M Y') ?: 'the current access end date' }}</strong>, and the next paid access period is scheduled from <strong>{{ $nextPaidCycle?->period_starts_at?->format('d M Y') ?: 'the scheduled start date' }}</strong> to <strong>{{ $nextPaidCycle?->period_ends_at?->format('d M Y') ?: 'the scheduled end date' }}</strong> without interruption.
    @else
        <div class="notice-title">Access Renewal Notice</div>
        Your {{ $platformName }} workspace access expires on <strong>{{ $invoice->access_ends_at?->format('d M Y') ?: 'the configured access end date' }}</strong>.
        Please renew before the due date to avoid interruption.
    @endif
</div>

<table class="columns">
    <tr>
        <td class="column-left">
            <div class="detail-card">
                <div class="section-title">Business Details</div>
                <div class="detail-row first"><span class="detail-label">Business</span><span class="detail-value">{{ $business?->business_name ?: '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Owner</span><span class="detail-value">{{ $owner?->name ?: '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">{{ $owner?->email ?: '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value">{{ $business?->phone ?: ($owner?->phone ?: '—') }}</span></div>
            </div>
        </td>
        <td class="column-right">
            <div class="detail-card">
                <div class="section-title">Access Details</div>
                <div class="detail-row first"><span class="detail-label">Current access</span><span class="detail-value">{{ $invoice->access_starts_at?->format('d M Y') ?: '—' }} &ndash; {{ $invoice->access_ends_at?->format('d M Y') ?: '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Days remaining</span><span class="detail-value">{{ $daysRemaining === null ? '—' : $daysRemaining.' day'.($daysRemaining === 1 ? '' : 's') }}</span></div>
                <div class="detail-row"><span class="detail-label">Renewal due</span><span class="detail-value">{{ $invoice->due_date?->format('d M Y') ?: '—' }}</span></div>
                <div class="detail-row"><span class="detail-label">Last payment</span><span class="detail-value">{{ $invoice->last_payment_method ?: '—' }}</span></div>
                @if($isRenewalSecured)
                    <div class="detail-row"><span class="detail-label">Renewal status</span><span class="detail-value">Paid / Secured</span></div>
                    <div class="detail-row"><span class="detail-label">Upcoming access</span><span class="detail-value">{{ $nextPaidCycle?->period_starts_at?->format('d M Y') ?: '—' }} &ndash; {{ $nextPaidCycle?->period_ends_at?->format('d M Y') ?: '—' }}</span></div>
                @endif
            </div>
            <div class="amount-card">
                <div class="amount-label">Renewal Amount</div>
                <div class="amount-value">Rs {{ number_format((float) $invoice->amount, 2) }}</div>
                <div class="amount-caption">Custom negotiated renewal</div>
            </div>
        </td>
    </tr>
</table>

</body>
</html>
