<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111827; }
        h1 { color: #0B1F3A; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #e5e7eb; padding: 4px; text-align: left; vertical-align: top; overflow-wrap: break-word; }
        th { background: #f8fafc; }
        .when { width: 14%; } .company { width: 14%; } .user { width: 14%; }
        .role { width: 12%; } .module { width: 14%; } .action { width: 20%; } .ip { width: 12%; }
    </style>
</head>
<body>
    <h1>TradeFlow Platform Audit Logs</h1>
    <p>Generated {{ now()->timezone(config('app.timezone'))->format('d M, Y h:i A') }}. This PDF contains up to {{ $pdfRowLimit }} matching records; use CSV for the complete export.</p>
    <table>
        <thead>
            <tr><th class="when">When</th><th class="company">Company</th><th class="user">User</th><th class="role">Role</th><th class="module">Module</th><th class="action">Action</th><th class="ip">IP</th></tr>
        </thead>
        <tbody>
        @foreach($logs as $log)
            <tr>
                <td>{{ ($log->occurred_at ?? $log->created_at) ? \Carbon\Carbon::parse($log->occurred_at ?? $log->created_at)->timezone(config('app.timezone'))->format('d M, Y h:i A') : '-' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($log->business?->business_name ?: 'Platform', 40) }}</td>
                <td>{{ \Illuminate\Support\Str::limit($log->user_name ?: $log->user?->name ?: 'System', 35) }}</td>
                <td>{{ \Illuminate\Support\Str::limit($log->role ?: $log->actor_role, 30) }}</td>
                <td><x-activity-label :activity="$log" field="module" /></td>
                <td><x-activity-label :activity="$log" /></td>
                <td>{{ \Illuminate\Support\Str::limit(\App\Services\AuditIpResolver::display($log->ip_address), 40) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
