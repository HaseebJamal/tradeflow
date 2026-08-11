<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><style>
body { font-family: DejaVu Sans, sans-serif; color:#172033; font-size:11px; line-height:1.5; }
.paper { padding:30px 36px; } .brand { color:#2563eb; font-size:22px; font-weight:bold; } .muted { color:#64748b; }
.heading { margin-top:28px; font-size:22px; font-weight:bold; } table { width:100%; border-collapse:collapse; margin-top:18px; }
td { padding:9px 0; border-bottom:1px solid #e2e8f0; } td:last-child { text-align:right; font-weight:bold; }
.notice { margin-top:24px; padding:14px; background:#eff6ff; border-left:4px solid #2563eb; }
.total { font-size:16px; } .footer { margin-top:42px; color:#64748b; font-size:9px; }
</style></head>
<body><div class="paper">
    <div class="brand">{{ $platformName }}</div>
    <div class="muted">Custom access renewal invoice</div>
    <div class="heading">Renewal Invoice</div>
    <div class="muted">{{ $invoice->invoice_number }} · Created {{ $invoice->created_at->format('d M Y, g:i A') }}</div>
    <div class="notice">Your Profit Point access is due to expire on <strong>{{ $invoice->access_ends_at->format('d M Y') }}</strong>. To continue using your workspace without interruption, please renew your access.</div>
    <table>
        <tr><td>Business</td><td>{{ $business?->business_name }}</td></tr>
        <tr><td>Business owner</td><td>{{ $owner?->name ?: '—' }}</td></tr>
        <tr><td>Registration email</td><td>{{ $owner?->email ?: '—' }}</td></tr>
        <tr><td>Business phone</td><td>{{ $business?->phone ?: ($owner?->phone ?: '—') }}</td></tr>
        <tr><td>Current paid access</td><td>{{ $invoice->access_starts_at?->format('d M Y') ?: '—' }} – {{ $invoice->access_ends_at->format('d M Y') }}</td></tr>
        <tr><td>Days remaining</td><td>{{ max(0, today()->diffInDays($invoice->access_ends_at, false)) }}</td></tr>
        <tr><td>Renewal due date</td><td>{{ $invoice->due_date->format('d M Y') }}</td></tr>
        <tr><td>Last payment method</td><td>{{ $invoice->last_payment_method ?: '—' }}</td></tr>
        <tr class="total"><td>Proposed custom renewal amount</td><td>Rs {{ number_format((float) $invoice->amount, 2) }}</td></tr>
    </table>
    <p class="footer">This is a renewal invoice/reminder, not a payment receipt. Payment is recorded only after the agreed custom amount and new access dates are confirmed.</p>
</div></body></html>
