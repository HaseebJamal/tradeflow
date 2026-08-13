<!doctype html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:24px;font-family:Arial,Helvetica,sans-serif;color:#172033;line-height:1.55;">
    <h1 style="margin:0 0 18px;font-size:20px;">New public contact inquiry</h1>
    <table role="presentation" style="border-collapse:collapse;margin-bottom:20px;">
        <tr><td style="padding:3px 16px 3px 0;color:#64748b;">Name</td><td style="padding:3px 0;">{{ $inquiry['name'] }}</td></tr>
        <tr><td style="padding:3px 16px 3px 0;color:#64748b;">Phone</td><td style="padding:3px 0;">{{ $inquiry['phone'] }}</td></tr>
        <tr><td style="padding:3px 16px 3px 0;color:#64748b;">Email</td><td style="padding:3px 0;"><a href="mailto:{{ $inquiry['email'] }}">{{ $inquiry['email'] }}</a></td></tr>
        <tr><td style="padding:3px 16px 3px 0;color:#64748b;">Submitted</td><td style="padding:3px 0;">{{ $inquiry['submitted_at'] }}</td></tr>
        <tr><td style="padding:3px 16px 3px 0;color:#64748b;">Source</td><td style="padding:3px 0;">Public Contact Form</td></tr>
    </table>
    <h2 style="margin:0 0 8px;font-size:16px;">Message</h2>
    <div style="white-space:pre-wrap;">{{ $inquiry['message'] }}</div>
</body>
</html>
