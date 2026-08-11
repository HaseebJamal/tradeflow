<!doctype html>
<html lang="en">
<body style="margin:0;background:#f8fafc;color:#172033;font-family:Arial,sans-serif;">
    <div style="max-width:620px;margin:24px auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:32px;">
        <h1 style="margin:0 0 16px;font-size:23px;">Your Profit Point access renewal</h1>
        <p>Hello {{ $invoice->business?->owner?->name ?: 'there' }},</p>
        <p>Your Profit Point access for <strong>{{ $invoice->business?->business_name }}</strong> is due to expire on <strong>{{ $invoice->access_ends_at->format('d M Y') }}</strong>.</p>
        <p>To continue using your workspace without interruption, please renew your access. Your custom renewal invoice is attached for reference.</p>
        <table style="width:100%;border-collapse:collapse;margin:20px 0;">
            <tr><td style="padding:8px 0;color:#64748b;">Renewal invoice</td><td style="padding:8px 0;text-align:right;"><strong>{{ $invoice->invoice_number }}</strong></td></tr>
            <tr><td style="padding:8px 0;color:#64748b;">Renewal due</td><td style="padding:8px 0;text-align:right;"><strong>{{ $invoice->due_date->format('d M Y') }}</strong></td></tr>
            <tr><td style="padding:8px 0;color:#64748b;">Proposed custom amount</td><td style="padding:8px 0;text-align:right;"><strong>Rs {{ number_format((float) $invoice->amount, 2) }}</strong></td></tr>
        </table>
        <p style="margin-bottom:0;color:#64748b;">Please contact Profit Point support if you need to confirm your renewal amount or access period.</p>
    </div>
</body>
</html>
