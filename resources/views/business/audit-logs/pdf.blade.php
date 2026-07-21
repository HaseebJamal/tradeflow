<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h1 { color: #0B1F3A; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f8fafc; }
    </style>
</head>
<body>
    <h1>TradeFlow Business Audit Logs</h1>
    <p>Generated {{ now()->timezone(config('app.timezone'))->format('d M, Y h:i A') }}</p>

    <table>
        <thead>
            <tr><th>When</th><th>User</th><th>Role</th><th>Module</th><th>Action</th><th>IP</th></tr>
        </thead>
        <tbody>
        @foreach($logs as $log)
            <tr>
                <td>{{ ($log->occurred_at ?? $log->created_at) ? \Carbon\Carbon::parse($log->occurred_at ?? $log->created_at)->timezone(config('app.timezone'))->format('d M, Y h:i A') : '—' }}</td>
                <td>{{ $log->user_name ?: $log->user?->name ?: 'System' }}</td>
                <td>{{ $log->role ?: $log->actor_role }}</td>
                <td><x-activity-label :activity="$log" field="module" /></td>
                <td><x-activity-label :activity="$log" /></td>
                <td>{{ \App\Services\AuditIpResolver::display($log->ip_address) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
