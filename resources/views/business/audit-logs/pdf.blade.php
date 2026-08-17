<x-a4-document :business="$business" :footer="$business?->documentFooter" title="Business Audit Logs" :reference="number_format($logs->count()).' records'" :date="now()->timezone(config('app.timezone'))->format('n/j/Y, g:i A')" subtitle="Business activity recorded for the selected period.">
    <table class="tf-a4-document__table"><thead><tr>
        <th style="width:18%">When</th><th style="width:16%">User</th><th style="width:12%">Role</th><th style="width:14%">Module</th><th style="width:28%">Action</th><th style="width:12%">IP</th>
    </tr></thead><tbody>
        @forelse($logs as $log)
            <tr>
                <td>{{ ($log->occurred_at ?? $log->created_at) ? \Carbon\Carbon::parse($log->occurred_at ?? $log->created_at)->timezone(config('app.timezone'))->format('n/j/Y, g:i A') : '—' }}</td>
                <td>{{ $log->user_name ?: $log->user?->name ?: 'System' }}</td>
                <td>{{ $log->role ?: $log->actor_role ?: '—' }}</td>
                <td><x-activity-label :activity="$log" field="module" /></td>
                <td><x-activity-label :activity="$log" /></td>
                <td>{{ \App\Services\AuditIpResolver::display($log->ip_address) }}</td>
            </tr>
        @empty
            <tr><td class="tf-a4-document__empty" colspan="6">No audit activity is available for the selected period.</td></tr>
        @endforelse
    </tbody></table>
</x-a4-document>
